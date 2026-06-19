<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = ['name', 'unit', 'selling_price', 'recipe_type', 'estimated_yield_per_batch', 'is_active', 'tenant_id'];

    protected $casts = [
        'selling_price' => 'decimal:2',
        'estimated_yield_per_batch' => 'integer',
        'is_active' => 'boolean',
    ];

    // Constants for recipe_type
    const RECIPE_UNIT = 'unit';
    const RECIPE_BATCH = 'batch';

    public function recipeItems(): HasMany
    {
        return $this->hasMany(RecipeItem::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function productionRuns(): HasMany
    {
        return $this->hasMany(ProductionRun::class);
    }

    /**
     * Check if this product uses batch recipe
     */
    public function isBatch(): bool
    {
        return $this->recipe_type === self::RECIPE_BATCH;
    }

    /**
     * Get yield per batch from recipe (sum of all recipe items doesn't apply here,
     * yield is determined at production run time)
     * For unit recipes: always 1 per sale
     * For batch recipes: variable, set at production run
     */
    public function getYieldLabel(): string
    {
        if ($this->isBatch()) {
            return 'Bervariasi per batch';
        }
        return '1 porsi = 1 produk';
    }
}
