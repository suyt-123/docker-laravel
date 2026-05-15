<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProgressLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'dispatch_id',
        'worker_id',
        'created_by',
        'work_date',
        'weather',
        'worker_count',
        'progress_percent',
        'work_items',
        'description',
        'issue',
        'voice_text',
        'latitude',
        'longitude',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'worker_count' => 'integer',
            'progress_percent' => 'integer',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function dispatch(): BelongsTo
    {
        return $this->belongsTo(Dispatch::class);
    }

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(ProgressPhoto::class);
    }
}
