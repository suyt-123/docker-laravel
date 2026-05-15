<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuotationTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'status',
        'profit_rate',
        'tax',
        'discount',
        'parameter_definitions',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'profit_rate' => 'decimal:2',
            'parameter_definitions' => 'array',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuotationTemplateItem::class)->orderBy('sort_order')->orderBy('id');
    }
}
