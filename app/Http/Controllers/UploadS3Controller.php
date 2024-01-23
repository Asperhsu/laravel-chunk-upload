<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Uploader\UploaderFactory;

class UploadS3Controller
{
    public function index()
    {
        return view('upload-s3');
    }

    public function prepare(Request $request)
    {
        $fileInfo = pathinfo($request->input('filename'));
        $key = sprintf(
            '%s_%s.%s',
            $fileInfo['filename'],
            md5(time()),
            $fileInfo['extension']
        );

        return [
            'Key' => $key,
            'UploadId' => resolve(UploaderFactory::class)->makeForDisk('s3')->setKey($key)->requestUploadId(),
        ];
    }

    public function store(Request $request)
    {
        $key = $request->query('Key');
        $uploadId = $request->query('UploadId');

        $result = resolve(UploaderFactory::class)
            ->makeForDisk('s3')
            ->setKey($key)
            ->setUploadId($uploadId)
            ->handle();
        return response()->json($result);
    }

    public function complete(Request $request)
    {
        $key = $request->input('Key');
        $uploadId = $request->input('UploadId');
        $parts = $request->input('parts');

        $result = resolve(UploaderFactory::class)
            ->makeForDisk('s3')
            ->setKey($key)
            ->setUploadId($uploadId)
            ->complete($parts);
        return response()->json($result);
    }
}
