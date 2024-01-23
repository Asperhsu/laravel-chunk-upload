<?php

namespace App\Uploader;

use ReflectionClass;
use Pion\Laravel\ChunkUpload\Receiver\FileReceiver;
use Pion\Laravel\ChunkUpload\Handler\HandlerFactory;
use Pion\Laravel\ChunkUpload\Handler\ChunksInRequestUploadHandler;
use Pion\Laravel\ChunkUpload\Exceptions\UploadMissingFileException;
use Illuminate\Http\UploadedFile;
use Exception;

class S3Uploader extends AbstractUploader
{
    protected $client;
    protected $bucket;
    protected $currentChunk;
    protected $totalChunk;

    protected $Key;
    protected $UploadId;

    public function handle()
    {
        $this->getKey();    // check key is set
        $this->getUploadId();    // check UploadId is set

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
        $result = $this->uploadPartToS3($file);

        return [
            'status' => true,
            'part' => $result,
        ];
    }

    public function complete(array $parts)
    {
        $client = $this->getClient();
        $parts = collect($parts)->sortBy('PartNumber')->values()->toArray();

        $result = $client->completeMultipartUpload([
            'Bucket' => $this->getBucket(),
            'Key' => $this->getKey(),
            'MultipartUpload' => [
                'Parts' => $parts,
            ],
            'UploadId' => $this->getUploadId(),
        ]);

        return [
            'status' => true,
            'path' => $result['Location'],
        ];
    }

    public function setKey($key)
    {
        $root = $this->getDiskConfig('root');

        $this->Key = $root ? $root . '/' . $key : $key;
        return $this;
    }

    public function getKey()
    {
        if (!$this->Key) {
            throw new Exception('Key invalid');
        }
        return $this->Key;
    }

    public function setUploadId($id)
    {
        $this->UploadId = $id;
        return $this;
    }

    public function getUploadId()
    {
        if (!$this->UploadId) {
            throw new Exception('UploadId invalid');
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

    public function requestUploadId()
    {
        $client = $this->getClient();
        $result = $client->createMultipartUpload([
            'Bucket' => $this->getBucket(),
            'Key' => $this->getKey(),
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

        return [
            'ETag' => $result['ETag'],
            'PartNumber' => $this->currentChunk,
        ];
    }

    protected function getProtectedValue($obj, $prop)
    {
        $reflection = new ReflectionClass($obj);
        $property = $reflection->getProperty($prop);
        $property->setAccessible(true);
        return $property->getValue($obj);
    }
}
