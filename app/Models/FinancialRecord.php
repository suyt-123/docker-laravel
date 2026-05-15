<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'project_change_order_id',
        'type',
        'title',
        'amount',
        'due_date',
        'paid_date',
        'status',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'paid_date' => 'date',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function projectChangeOrder(): BelongsTo
    {
        return $this->belongsTo(ProjectChangeOrder::class);
    }
}
