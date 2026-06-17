<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceHistory extends Model
{
    use BelongsToTenant, HasFactory;

    protected $table = 'price_histories';

    protected $fillable = ['ingredient_id', 'unit_price', 'recorded_at', 'tenant_id'];

    protected $casts = [
        'unit_price' => 'decimal:4',
        'recorded_at' => 'date',
    ];

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }
}
