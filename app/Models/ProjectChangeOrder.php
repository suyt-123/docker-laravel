<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectChangeOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'financial_record_id',
        'quotation_id',
        'created_by',
        'approved_by',
        'title',
        'description',
        'amount',
        'requires_formal_quotation',
        'requested_date',
        'submitted_at',
        'approved_date',
        'approved_at',
        'customer_confirmed_at',
        'due_date',
        'status',
        'customer_note',
        'internal_note',
        'converted_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'requires_formal_quotation' => 'boolean',
            'requested_date' => 'date',
            'submitted_at' => 'datetime',
            'approved_date' => 'date',
            'approved_at' => 'datetime',
            'customer_confirmed_at' => 'datetime',
            'due_date' => 'date',
            'converted_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function financialRecord(): BelongsTo
    {
        return $this->belongsTo(FinancialRecord::class);
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
