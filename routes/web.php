<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::prefix('upload/local')
    ->name('upload.local.')
    ->group(function () {
        Route::get('/', 'UploadLocalController@index')->name('index');
        Route::post('/', 'UploadLocalController@store')->name('store');
    });

Route::prefix('upload/s3')
    ->name('upload.s3.')
    ->group(function () {
        Route::get('/', 'UploadS3Controller@index')->name('index');
        Route::post('/prepare', 'UploadS3Controller@prepare')->name('prepare');
        Route::post('/', 'UploadS3Controller@store')->name('store');
        Route::post('/complete', 'UploadS3Controller@complete')->name('complete');
    });
