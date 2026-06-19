<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = ['name', 'contact', 'note', 'tenant_id'];

    public function ingredients(): HasMany
    {
        return $this->hasMany(Ingredient::class);
    }
}
