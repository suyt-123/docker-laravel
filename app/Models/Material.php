<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Material extends Model
{
    use HasFactory;

    protected $fillable = [
        'material_category_id',
        'name',
        'spec',
        'unit',
        'length',
        'width',
        'thickness',
        'weight',
        'cost_price',
        'sale_price',
        'safe_stock',
        'current_stock',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'length' => 'decimal:3',
            'width' => 'decimal:3',
            'thickness' => 'decimal:3',
            'weight' => 'decimal:3',
            'safe_stock' => 'decimal:3',
            'current_stock' => 'decimal:3',
            'metadata' => 'array',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(MaterialCategory::class, 'material_category_id');
    }

    public function quotationItems(): HasMany
    {
        return $this->hasMany(QuotationItem::class);
    }

    public function quotationTemplateItems(): HasMany
    {
        return $this->hasMany(QuotationTemplateItem::class);
    }

    public function purchaseOrderItems(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function inventoryTransactions(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class);
    }
}
