<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Outlet extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'type',
        'address',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function productionRuns(): HasMany
    {
        return $this->hasMany(ProductionRun::class);
    }

    public function cashEntries(): HasMany
    {
        return $this->hasMany(CashEntry::class);
    }

    public function distributionsFrom(): HasMany
    {
        return $this->hasMany(Distribution::class, 'from_outlet_id');
    }

    public function distributionsTo(): HasMany
    {
        return $this->hasMany(Distribution::class, 'to_outlet_id');
    }

    public function inventory(): HasMany
    {
        return $this->hasMany(OutletInventory::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
