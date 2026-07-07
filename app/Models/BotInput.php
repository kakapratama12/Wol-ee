<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BotInput extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'tenant_id',
        'telegram_user_id',
        'entity_type',
        'entity_id',
        'raw_input',
        'parsed_data',
        'status',
    ];

    protected $casts = [
        'parsed_data' => 'array',
        'telegram_user_id' => 'integer',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the related entity model.
     */
    public function entity(): ?Model
    {
        return match ($this->entity_type) {
            'product' => Product::find($this->entity_id),
            'ingredient' => Ingredient::find($this->entity_id),
            'transaction' => Transaction::find($this->entity_id),
            'sale' => Sale::find($this->entity_id),
            'invoice' => Invoice::find($this->entity_id),
            'partner' => Partner::find($this->entity_id),
            'expense' => Expense::find($this->entity_id),
            default => null,
        };
    }

    /**
     * Get a human-readable summary of what was created.
     */
    public function summary(): string
    {
        $data = $this->parsed_data ?? [];
        
        return match ($this->entity_type) {
            'product' => "Produk: " . ($data['name'] ?? '-'),
            'ingredient' => "Bahan: " . ($data['name'] ?? '-'),
            'recipe' => "Resep: " . ($data['product_name'] ?? '-'),
            'transaction' => "Pembelian: " . ($data['ingredient'] ?? '-'),
            'sale' => "Penjualan: " . ($data['product'] ?? '-'),
            'invoice' => "Invoice: " . ($data['partner_name'] ?? '-'),
            'partner' => ($data['type'] ?? 'Mitra') . ": " . ($data['name'] ?? '-'),
            'expense' => "Pengeluaran: " . ($data['description'] ?? '-'),
            default => ucfirst($this->entity_type),
        };
    }
}
