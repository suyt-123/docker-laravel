<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkCrew extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'leader_name',
        'phone',
        'specialties',
        'daily_rate',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'specialties' => 'array',
        ];
    }

    public function workers(): HasMany
    {
        return $this->hasMany(Worker::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function dispatches(): HasMany
    {
        return $this->hasMany(Dispatch::class);
    }
}
