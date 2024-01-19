<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Uploader\UploaderFactory;
use Ramsey\Uuid\Uuid;

Route::get('/', function () {
    return view('welcome');
});

Route::get('upload-local', function () {
    return view('upload-local');
})->name('upload.local');
Route::post('upload-local', function () {
    $result = resolve(UploaderFactory::class)->makeForDisk('local')->handle();
    return response()->json($result);
})->name('upload.local');

Route::get('upload-s3', function () {
    // Uuid
    // $key = 'my-file';
    // $uploadId = resolve(UploaderFactory::class)->makeForDisk('s3')->requestUploadId();

    return view('upload-s3');
})->name('upload.s3');
Route::post('upload-s3', function (Request $request) {
    $fileInfo = pathinfo($request->query('resumableFilename'));
    $key = sprintf(
        '%s_%s.%s',
        $fileInfo['filename'],
        md5($request->query('resumableIdentifier')),
        $fileInfo['extension']
    );
    logger('upload', ['key' => $key]);

    $result = resolve(UploaderFactory::class)
        ->makeForDisk('s3')
        ->setKey($key)
        ->handle();
    return response()->json($result);
})->name('upload.s3');

// Route::get('upload', 'UploadController@index')->name('upload.index');
// Route::post('upload', 'UploadController@store')->name('upload.store');
// Route::get('singature', 'UploadController@singature')->name('upload.singature');
