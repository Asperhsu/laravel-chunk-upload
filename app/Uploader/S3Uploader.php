<?php

namespace App\Uploader;

use ReflectionClass;
use Pion\Laravel\ChunkUpload\Receiver\FileReceiver;
use Pion\Laravel\ChunkUpload\Handler\HandlerFactory;
use Pion\Laravel\ChunkUpload\Handler\ChunksInRequestUploadHandler;
use Pion\Laravel\ChunkUpload\Exceptions\UploadMissingFileException;
use Illuminate\Support\Arr;
use Illuminate\Http\UploadedFile;
use Exception;

class S3Uploader extends AbstractUploader
{
    const SESSION_KEY_PREFIX = 's3uploader';

    protected $client;
    protected $bucket;
    protected $currentChunk;
    protected $totalChunk;
    protected $finishChunk = 0;

    protected $Key;
    protected $UploadId;

    public function handle()
    {
        $this->getKey();    // check key is set

        $request = request();
        $receiver = new FileReceiver("file", $request, HandlerFactory::classFromRequest($request));

        // check if the upload is success, throw exception or return response you need
        if ($receiver->isUploaded() === false) {
            throw new UploadMissingFileException();
        }

        $handler = $this->getProtectedValue($receiver, 'handler');
        $isChunkMode = is_a($handler, ChunksInRequestUploadHandler::class);
        $this->currentChunk = $handler->getCurrentChunk();
        $this->totalChunk = $handler->getTotalChunks();
        // logger('initial ', [
        //     'isChunkMode' => $isChunkMode,
        //     'getCurrentChunk' => $handler->getCurrentChunk(),
        //     'getTotalChunks' => $handler->getTotalChunks(),
        // ]);

        // single file mode
        if (!$isChunkMode || ($isChunkMode && ($handler->getTotalChunks() == 1))) {
            // receive the file
            $save = $receiver->receive();
            if ($save->isFinished()) {
                return $this->uploadToS3($save->getFile());
            }

            return [
                "done" => $handler->getPercentageDone(),
                'status' => true
            ];
        }


        $file = $this->getProtectedValue($receiver, 'file');
        $this->uploadPartToS3($file);
        logger('uploadPart', [
            'parts' => $this->getParts(),
            'getRealPath' => $file->getRealPath(),
            'size' => $file->getFileInfo()->getSize(),
        ]);

        if ($this->finishChunk == $this->totalChunk) {
            logger('compelete', [
                //
            ]);
            $name = $file->getClientOriginalName();
            $mime = str_replace('/', '-', $file->getMimeType());
            $result = $this->compeleteUpload();
            return [
                'path' => $result['Location'],
                'name' => $name,
                'mime_type' => $mime,
            ];
        }

        return [
            "done" => ceil($this->finishChunk / $this->totalChunk * 100),
            'status' => true
        ];
    }

    public function setKey($key)
    {
        $this->Key = $key;
        return $this;
    }

    public function getKey()
    {
        if (!$this->Key) {
            throw new Exception('Key invalid');
        }
        return $this->Key;
    }

    public function getSessionKey($keys)
    {
        $keys = is_array($keys) ? $keys : func_get_args();

        return sprintf(
            '%s-%s.%s',
            static::SESSION_KEY_PREFIX,
            md5($this->getKey()),
            implode('.', $keys)
        );
    }

    public function getUploadId()
    {
        if (!$this->UploadId) {
            $sessionKey = $this->getSessionKey('UploadId');
            if (!($id = session($sessionKey))) {
                $id = $this->requestUploadId();
                session()->put($sessionKey, $id);
            }

            $this->UploadId = $id;
        }

        return $this->UploadId;
    }

    public function getClient()
    {
        if (!$this->client) {
            $filesystem = app('filesystem')->disk($this->getDisk());
            $driver = $filesystem->getDriver();
            $adapter = $driver->getAdapter();
            $this->client = $adapter->getClient();
        }
        return $this->client;
    }

    public function getBucket()
    {
        if (!$this->bucket) {
            $filesystem = app('filesystem')->disk($this->getDisk());
            $driver = $filesystem->getDriver();
            $adapter = $driver->getAdapter();
            $this->bucket = $adapter->getBucket();
        }
        return $this->bucket;
    }

    public function requestUploadId(string $key = null)
    {
        $client = $this->getClient();
        $result = $client->createMultipartUpload([
            'Bucket' => $this->getBucket(),
            'Key' => $key ?: $this->getKey(),
        ]);
        return $result->get('UploadId');
    }

    public function uploadToS3(UploadedFile $file)
    {
        $disk = app('filesystem')->disk($this->getDisk());
        $path = $disk->putFileAs(
            pathinfo($this->getKey(), PATHINFO_DIRNAME),
            $file,
            $fileName = pathinfo($this->getKey(), PATHINFO_BASENAME)
        );
        $mime = str_replace('/', '-', $file->getMimeType());
        unlink($file->getPathname());

        return [
            'path' => $disk->url($path),
            'name' => $fileName,
            'mime_type' => $mime
        ];
    }

    public function uploadPartToS3(UploadedFile $file)
    {
        $client = $this->getClient();

        $result = $client->uploadPart([
            'Bucket'     => $this->getBucket(),
            'Key'        => $this->getKey(),
            'UploadId'   => $this->getUploadId(),
            'PartNumber' => $this->currentChunk,
            'Body'       => fopen($file->getRealPath(), 'rb'),
        ]);
        logger('uploadPartToS3', [
            $result['ETag'],
            $this->currentChunk,
        ]);
        $this->pushPart($result['ETag'], $this->currentChunk);
    }

    public function compeleteUpload()
    {
        $client = $this->getClient();
        logger('compeleteUpload before', $this->getParts());

        $result = $client->completeMultipartUpload([
            'Bucket' => $this->getBucket(), // REQUIRED
            // 'ChecksumCRC32' => '<string>',
            // 'ChecksumCRC32C' => '<string>',
            // 'ChecksumSHA1' => '<string>',
            // 'ChecksumSHA256' => '<string>',
            // 'ExpectedBucketOwner' => '<string>',
            'Key' => $this->getKey(), // REQUIRED
            'MultipartUpload' => [
                'Parts' => $this->getParts(),
            ],
            // 'RequestPayer' => 'requester',
            // 'SSECustomerAlgorithm' => '<string>',
            // 'SSECustomerKey' => '<string>',
            // 'SSECustomerKeyMD5' => '<string>',
            'UploadId' => $this->getUploadId(), // REQUIRED
        ]);

        logger('compeleteUpload after', Arr::wrap($result));

        return $result;
    }

    protected function pushPart(string $ETag, int $partNumber)
    {
        $sessionKey = $this->getSessionKey('parts');
        $parts = collect(session()->get($sessionKey));

        if (!$parts->contains('PartNumber', $partNumber)) {
            $parts->push([
                'ETag' => $ETag,
                'PartNumber' => $partNumber,
            ]);
        }

        $this->finishChunk = $parts->count();
        session()->put($sessionKey, $parts->sortBy('PartNumber')->values()->toArray());

        return $this;
    }

    protected function getParts()
    {
        $sessionKey = $this->getSessionKey('parts');
        return session()->get($sessionKey) ?: [];
    }

    protected function getProtectedValue($obj, $prop)
    {
        $reflection = new ReflectionClass($obj);
        $property = $reflection->getProperty($prop);
        $property->setAccessible(true);
        return $property->getValue($obj);
    }
}
