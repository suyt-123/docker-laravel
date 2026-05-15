<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EquipmentTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'equipment_id',
        'project_id',
        'worker_id',
        'work_crew_id',
        'handled_by',
        'type',
        'occurred_at',
        'due_at',
        'condition_before',
        'condition_after',
        'from_location',
        'to_location',
        'photo_path',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'due_at' => 'datetime',
        ];
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }

    public function workCrew(): BelongsTo
    {
        return $this->belongsTo(WorkCrew::class);
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}
