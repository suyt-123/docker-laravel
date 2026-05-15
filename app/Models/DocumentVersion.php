<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class DocumentVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_type',
        'document_id',
        'category',
        'version_number',
        'status',
        'file_path',
        'file_name',
        'mime_type',
        'size',
        'file_hash',
        'generated_at',
        'generated_by',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
            'size' => 'integer',
            'generated_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function document(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'document_type', 'document_id');
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
