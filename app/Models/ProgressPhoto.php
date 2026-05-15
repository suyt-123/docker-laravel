<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgressPhoto extends Model
{
    use HasFactory;

    protected $fillable = [
        'progress_log_id',
        'project_id',
        'dispatch_id',
        'uploaded_by',
        'file_path',
        'original_name',
        'mime_type',
        'size',
        'caption',
        'taken_at',
        'latitude',
        'longitude',
        'watermark_text',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'taken_at' => 'datetime',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public function progressLog(): BelongsTo
    {
        return $this->belongsTo(ProgressLog::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function dispatch(): BelongsTo
    {
        return $this->belongsTo(Dispatch::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
