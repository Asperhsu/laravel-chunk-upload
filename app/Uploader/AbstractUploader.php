<?php

namespace App\Uploader;

use Illuminate\Support\Arr;

abstract class AbstractUploader
{
    protected $disk;

    public function __construct(string $disk)
    {
        $this->disk = $disk;
    }

    public function getDisk()
    {
        return $this->disk;
    }

    public function getDiskConfig($key = null, $default = null)
    {
        $config = config('filesystems.disks.' . $this->disk) ?: [];
        return $key ? Arr::get($config, $key, $default) : $config;
    }

    abstract public function handle();
}
