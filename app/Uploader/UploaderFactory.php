<?php

namespace App\Uploader;

use Exception;
use Illuminate\Support\Str;

class UploaderFactory
{
    public static function makeForDisk(string $disk)
    {
        $driver = config(sprintf('filesystems.disks.%s.driver', $disk));
        $classname = __NAMESPACE__ . '\\' . Str::studly($driver) . 'Uploader';

        if (!class_exists($classname)) {
            throw new Exception($classname . ' not exists');
        }
        return new $classname($disk);
    }
}
