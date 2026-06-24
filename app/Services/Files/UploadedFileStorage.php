<?php

namespace App\Services\Files;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class UploadedFileStorage
{
    /**
     * @return array{path: string, original_name: string, mime_type: string|null, size: int}
     */
    public function storePublic(UploadedFile $file, string $directory): array
    {
        return [
            'path' => $file->store($directory, 'public'),
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize() ?: 0,
        ];
    }

    /**
     * @param  string|array<int, string|null>|null  $paths
     */
    public function deletePublic(string|array|null $paths): void
    {
        $paths = collect(is_array($paths) ? $paths : [$paths])
            ->filter()
            ->values()
            ->all();

        if ($paths === []) {
            return;
        }

        Storage::disk('public')->delete($paths);
    }

    public function publicUrl(?string $path): ?string
    {
        return $path ? Storage::disk('public')->url($path) : null;
    }
}
