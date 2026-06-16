<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'unit', 'selling_price', 'is_active'];

    protected $casts = [
        'selling_price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function recipeItems(): HasMany
    {
        return $this->hasMany(RecipeItem::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }
}
