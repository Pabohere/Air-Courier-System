<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;

class StoredDraftPhoto extends UploadedFile
{
    public function __construct(string $path, public readonly string $sourceSha256)
    {
        parent::__construct($path, basename($path), null, null, true);
    }
}
