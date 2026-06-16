<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ingredient extends Model
{
    use HasFactory;

    public const STATUS_SAFE = 'aman';
    public const STATUS_LOW = 'menipis';
    public const STATUS_CRITICAL = 'kritis';

    protected $fillable = [
        'name',
        'unit_type',
        'base_unit',
        'unit_price',
        'current_stock',
        'minimum_stock',
        'supplier_id',
    ];

    protected $casts = [
        'unit_price' => 'decimal:4',
        'current_stock' => 'decimal:4',
        'minimum_stock' => 'decimal:4',
    ];

    protected $appends = ['stock_status'];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function recipeItems(): HasMany
    {
        return $this->hasMany(RecipeItem::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function priceHistories(): HasMany
    {
        return $this->hasMany(PriceHistory::class);
    }

    public function getStockStatusAttribute(): string
    {
        $stock = (float) $this->current_stock;
        $min = (float) $this->minimum_stock;

        if ($min > 0 && $stock < $min * 0.5) {
            return self::STATUS_CRITICAL;
        }

        if ($stock <= $min) {
            return self::STATUS_LOW;
        }

        return self::STATUS_SAFE;
    }
}
