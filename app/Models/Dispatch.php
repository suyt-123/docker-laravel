<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Dispatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'work_crew_id',
        'created_by',
        'work_item',
        'status',
        'scheduled_date',
        'start_time',
        'end_time',
        'address',
        'instructions',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_date' => 'date',
            'start_time' => 'datetime:H:i',
            'end_time' => 'datetime:H:i',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function workCrew(): BelongsTo
    {
        return $this->belongsTo(WorkCrew::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function workers(): BelongsToMany
    {
        return $this->belongsToMany(Worker::class)
            ->withPivot(['hours', 'wage', 'note'])
            ->withTimestamps();
    }

    public function progressLogs(): HasMany
    {
        return $this->hasMany(ProgressLog::class);
    }

    public function progressPhotos(): HasMany
    {
        return $this->hasMany(ProgressPhoto::class);
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }
}
