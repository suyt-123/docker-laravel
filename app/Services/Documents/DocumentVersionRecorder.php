<?php

namespace App\Services\Documents;

use App\Models\DocumentVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;

class DocumentVersionRecorder
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function record(Model $document, string $category, string $path, string $fileName, array $metadata = [], ?User $generatedBy = null): ?DocumentVersion
    {
        $documentType = $document::class;
        $documentId = $document->getKey();
        $hash = File::exists($path) ? hash_file('sha256', $path) : null;

        $existing = DocumentVersion::query()
            ->where('document_type', $documentType)
            ->where('document_id', $documentId)
            ->where('category', $category)
            ->where('file_hash', $hash)
            ->first();

        if ($existing) {
            return null;
        }

        DocumentVersion::query()
            ->where('document_type', $documentType)
            ->where('document_id', $documentId)
            ->where('category', $category)
            ->where('status', 'active')
            ->update(['status' => 'superseded']);

        $nextVersion = ((int) DocumentVersion::query()
            ->where('document_type', $documentType)
            ->where('document_id', $documentId)
            ->where('category', $category)
            ->max('version_number')) + 1;

        return DocumentVersion::create([
            'document_type' => $documentType,
            'document_id' => $documentId,
            'category' => $category,
            'version_number' => $nextVersion,
            'status' => 'active',
            'file_path' => $path,
            'file_name' => $fileName,
            'size' => File::exists($path) ? File::size($path) : 0,
            'file_hash' => $hash,
            'generated_at' => now(),
            'generated_by' => $generatedBy?->id,
            'metadata' => $metadata,
        ]);
    }
}
