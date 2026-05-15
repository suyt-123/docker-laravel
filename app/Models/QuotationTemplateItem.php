<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuotationTemplateItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'quotation_template_id',
        'material_id',
        'name',
        'spec',
        'unit',
        'unit_price',
        'cost_price',
        'waste_rate',
        'formula_type',
        'formula_params',
        'note',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'waste_rate' => 'decimal:2',
            'formula_params' => 'array',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(QuotationTemplate::class, 'quotation_template_id');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }
}
