<?php

namespace App\Uploader;

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

    abstract public function handle();
}
