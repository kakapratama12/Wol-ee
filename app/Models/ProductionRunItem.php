<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionRunItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'production_run_id',
        'ingredient_id',
        'quantity_used',
        'unit_cost_snapshot',
    ];

    protected $casts = [
        'quantity_used' => 'decimal:4',
        'unit_cost_snapshot' => 'decimal:4',
    ];

    public function productionRun(): BelongsTo
    {
        return $this->belongsTo(ProductionRun::class);
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }

    /**
     * Calculate total cost for this item
     */
    public function getTotalCost(): float
    {
        return $this->quantity_used * $this->unit_cost_snapshot;
    }
}
