<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Quotation extends Model
{
    use HasFactory;

    protected $fillable = [
        'quotation_no',
        'customer_id',
        'project_id',
        'quotation_template_id',
        'created_by',
        'approved_by',
        'status',
        'customer_confirmation_status',
        'customer_sent_at',
        'customer_confirmed_at',
        'customer_confirmed_by_name',
        'locked_at',
        'voided_at',
        'void_reason',
        'reopened_from_id',
        'superseded_by_id',
        'subtotal',
        'tax',
        'discount',
        'total',
        'profit_rate',
        'valid_until',
        'items_json',
        'template_inputs',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'profit_rate' => 'decimal:2',
            'valid_until' => 'date',
            'customer_sent_at' => 'datetime',
            'customer_confirmed_at' => 'datetime',
            'locked_at' => 'datetime',
            'voided_at' => 'datetime',
            'items_json' => 'array',
            'template_inputs' => 'array',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(QuotationTemplate::class, 'quotation_template_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuotationItem::class);
    }

    public function documentVersions(): MorphMany
    {
        return $this->morphMany(DocumentVersion::class, 'document');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(DocumentAttachment::class, 'attachable');
    }

    public function reopenedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reopened_from_id');
    }

    public function supersededBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'superseded_by_id');
    }
}
