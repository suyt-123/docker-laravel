<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Worker extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'work_crew_id',
        'name',
        'phone',
        'role',
        'daily_rate',
        'certifications',
        'insurance_expires_at',
        'is_active',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'certifications' => 'array',
            'insurance_expires_at' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function workCrew(): BelongsTo
    {
        return $this->belongsTo(WorkCrew::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function dispatches(): BelongsToMany
    {
        return $this->belongsToMany(Dispatch::class)
            ->withPivot(['hours', 'wage', 'note'])
            ->withTimestamps();
    }

    public function progressLogs(): HasMany
    {
        return $this->hasMany(ProgressLog::class);
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }
}
