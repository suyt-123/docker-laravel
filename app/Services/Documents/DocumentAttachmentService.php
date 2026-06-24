<?php

namespace App\Services\Documents;

use App\Models\DocumentAttachment;
use App\Models\User;
use App\Services\Files\UploadedFileStorage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;

class DocumentAttachmentService
{
    public function __construct(private readonly UploadedFileStorage $storage) {}

    public function store(Model $attachable, UploadedFile $file, ?User $uploadedBy, ?string $description = null): DocumentAttachment
    {
        $stored = $this->storage->storePublic($file, 'quotation-attachments/'.now()->format('Y/m'));

        /** @var DocumentAttachment $attachment */
        $attachment = $attachable->attachments()->create([
            'uploaded_by' => $uploadedBy?->id,
            'file_path' => $stored['path'],
            'original_name' => $stored['original_name'],
            'mime_type' => $stored['mime_type'],
            'size' => $stored['size'],
            'description' => $description,
        ]);

        return $attachment;
    }

    public function delete(DocumentAttachment $attachment): void
    {
        $this->storage->deletePublic($attachment->file_path);
        $attachment->delete();
    }

    public function url(DocumentAttachment $attachment): ?string
    {
        return $this->storage->publicUrl($attachment->file_path);
    }
}
