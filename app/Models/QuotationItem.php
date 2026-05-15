<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuotationItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'quotation_id',
        'material_id',
        'name',
        'spec',
        'unit',
        'quantity',
        'unit_price',
        'cost_price',
        'waste_rate',
        'subtotal',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'waste_rate' => 'decimal:2',
        ];
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }
}
