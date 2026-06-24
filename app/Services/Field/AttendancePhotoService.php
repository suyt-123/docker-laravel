<?php

namespace App\Services\Field;

use App\Services\Files\UploadedFileStorage;
use Illuminate\Http\UploadedFile;

class AttendancePhotoService
{
    public function __construct(private readonly UploadedFileStorage $storage) {}

    public function store(?UploadedFile $file): ?string
    {
        if (! $file) {
            return null;
        }

        return $this->storage->storePublic($file, 'attendance-photos/'.now()->format('Y/m'))['path'];
    }

    public function delete(?string $path): void
    {
        $this->storage->deletePublic($path);
    }

    public function url(?string $path): ?string
    {
        return $this->storage->publicUrl($path);
    }
}
