<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductionRun extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'product_id',
        'batch_count',
        'yield_actual',
        'waste_count',
        'total_cost',
        'notes',
        'produced_at',
    ];

    protected $casts = [
        'batch_count' => 'integer',
        'yield_actual' => 'integer',
        'waste_count' => 'integer',
        'total_cost' => 'decimal:4',
        'produced_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProductionRunItem::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * Calculate yield per batch
     */
    public function getYieldPerBatch(): float
    {
        if ($this->batch_count === 0) {
            return 0;
        }
        return $this->yield_actual / $this->batch_count;
    }

    /**
     * Calculate cost per unit (pcs)
     */
    public function getCostPerUnit(): float
    {
        if ($this->yield_actual === 0) {
            return 0;
        }
        return $this->total_cost / $this->yield_actual;
    }

    /**
     * Calculate waste percentage
     */
    public function getWastePercentage(): float
    {
        $total = $this->yield_actual + $this->waste_count;
        if ($total === 0) {
            return 0;
        }
        return ($this->waste_count / $total) * 100;
    }
}
