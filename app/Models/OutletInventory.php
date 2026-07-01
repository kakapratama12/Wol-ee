<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OutletInventory extends Model
{
    use BelongsToTenant, HasFactory;

    protected $table = 'outlet_inventory';

    protected $fillable = [
        'tenant_id',
        'outlet_id',
        'product_id',
        'ingredient_id',
        'quantity',
        'unit',
        'last_updated',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'last_updated' => 'datetime',
    ];

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function addQuantity(float $amount): self
    {
        $this->quantity += $amount;
        $this->last_updated = now();
        $this->save();

        return $this;
    }

    public function subtractQuantity(float $amount): self
    {
        $this->quantity -= $amount;
        $this->last_updated = now();
        $this->save();

        return $this;
    }
}
