<?php

namespace App\Services\Field;

use App\Models\ProgressLog;
use App\Models\ProgressPhoto;
use App\Models\User;
use App\Services\Files\UploadedFileStorage;
use Illuminate\Http\UploadedFile;

class ProgressPhotoService
{
    public function __construct(private readonly UploadedFileStorage $storage) {}

    /**
     * @param  array<int, UploadedFile>  $files
     */
    public function storeForLog(ProgressLog $log, array $files, ?User $uploadedBy): void
    {
        if (! config('features.progress_photos') || $files === []) {
            return;
        }

        $log->loadMissing('project', 'dispatch', 'creator');

        foreach ($files as $file) {
            $stored = $this->storage->storePublic($file, 'progress-photos/'.now()->format('Y/m'));

            $log->photos()->create([
                'project_id' => $log->project_id,
                'dispatch_id' => $log->dispatch_id,
                'uploaded_by' => $uploadedBy?->id,
                'file_path' => $stored['path'],
                'original_name' => $stored['original_name'],
                'mime_type' => $stored['mime_type'],
                'size' => $stored['size'],
                'taken_at' => now(),
                'latitude' => $log->latitude,
                'longitude' => $log->longitude,
                'watermark_text' => $this->watermarkText($log),
            ]);
        }
    }

    public function delete(ProgressPhoto $photo): void
    {
        $this->storage->deletePublic($photo->file_path);
        $photo->delete();
    }

    public function deleteForLog(ProgressLog $log): void
    {
        $photos = $log->photos()->get(['id', 'file_path']);
        $this->storage->deletePublic($photos->pluck('file_path')->all());
    }

    public function url(ProgressPhoto $photo): ?string
    {
        return $this->storage->publicUrl($photo->file_path);
    }

    private function watermarkText(ProgressLog $log): string
    {
        return collect([
            $log->project?->name,
            $log->dispatch?->work_item,
            $log->work_date?->format('Y-m-d'),
            $log->creator?->name,
            ($log->latitude && $log->longitude) ? "{$log->latitude}, {$log->longitude}" : null,
        ])->filter()->implode(' / ');
    }
}
