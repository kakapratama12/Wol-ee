<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ingredient extends Model
{
    use BelongsToTenant, HasFactory;

    public const STATUS_SAFE = 'aman';
    public const STATUS_LOW = 'menipis';
    public const STATUS_CRITICAL = 'kritis';

    // Constants for item_type
    const ITEM_RAW_MATERIAL = 'raw_material';
    const ITEM_PREP = 'prep';
    const ITEM_FINISHED_GOODS = 'finished_goods';

    protected $fillable = [
        'name',
        'item_type',
        'unit_type',
        'base_unit',
        'unit_price',
        'weighted_avg_price',
        'current_stock',
        'minimum_stock',
        'supplier_id',
        'tenant_id',
    ];

    protected $casts = [
        'unit_price' => 'decimal:4',
        'weighted_avg_price' => 'decimal:4',
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

    public function productionRunItems(): HasMany
    {
        return $this->hasMany(ProductionRunItem::class);
    }

    /**
     * Check if this is a raw material (bahan baku)
     */
    public function isRawMaterial(): bool
    {
        return $this->item_type === self::ITEM_RAW_MATERIAL;
    }

    /**
     * Check if this is a finished goods (produk jadi)
     */
    public function isFinishedGoods(): bool
    {
        return $this->item_type === self::ITEM_FINISHED_GOODS;
    }

    /**
     * Check if this is a prep item (bahan setengah jadi)
     */
    public function isPrep(): bool
    {
        return $this->item_type === self::ITEM_PREP;
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
