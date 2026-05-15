<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Equipment extends Model
{
    use HasFactory;

    protected $table = 'equipment';

    protected $fillable = [
        'equipment_no',
        'equipment_category_id',
        'current_project_id',
        'current_worker_id',
        'current_work_crew_id',
        'name',
        'brand',
        'model',
        'serial_no',
        'asset_tag',
        'status',
        'condition',
        'purchase_date',
        'purchase_price',
        'warranty_until',
        'last_maintenance_at',
        'next_maintenance_at',
        'note',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'purchase_date' => 'date',
            'purchase_price' => 'integer',
            'warranty_until' => 'date',
            'last_maintenance_at' => 'datetime',
            'next_maintenance_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(EquipmentCategory::class, 'equipment_category_id');
    }

    public function currentProject(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'current_project_id');
    }

    public function currentWorker(): BelongsTo
    {
        return $this->belongsTo(Worker::class, 'current_worker_id');
    }

    public function currentWorkCrew(): BelongsTo
    {
        return $this->belongsTo(WorkCrew::class, 'current_work_crew_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(EquipmentTransaction::class);
    }
}
