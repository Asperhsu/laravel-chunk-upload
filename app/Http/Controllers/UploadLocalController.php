<?php

namespace App\Http\Controllers;

use App\Uploader\UploaderFactory;

class UploadLocalController
{
    public function index()
    {
        return view('upload-local');
    }

    public function store()
    {
        $result = resolve(UploaderFactory::class)->makeForDisk('local')->handle();

        return response()->json($result);
    }
}
