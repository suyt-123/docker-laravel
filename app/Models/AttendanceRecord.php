<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'dispatch_id',
        'project_id',
        'worker_id',
        'user_id',
        'type',
        'worked_minutes',
        'recorded_at',
        'latitude',
        'longitude',
        'distance_meters',
        'is_within_range',
        'is_duplicate',
        'requires_attention',
        'anomaly_reason',
        'photo_path',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'recorded_at' => 'datetime',
            'worked_minutes' => 'integer',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'is_within_range' => 'boolean',
            'is_duplicate' => 'boolean',
            'requires_attention' => 'boolean',
        ];
    }

    public function dispatch(): BelongsTo
    {
        return $this->belongsTo(Dispatch::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
