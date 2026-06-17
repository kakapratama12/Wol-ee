<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Partner extends Model
{
    use BelongsToTenant, HasFactory;

    public const TYPE_CUSTOMER = 'customer';

    public const TYPE_SUPPLIER = 'supplier';

    protected $fillable = [
        'tenant_id',
        'name',
        'type',
        'contact',
        'phone',
        'email',
        'address',
    ];

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }
}
