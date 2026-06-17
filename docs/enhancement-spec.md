# Enhancement Spec: Sidebar + COGS Fix

> **Status:** Ready for implementation
> **Priority:** P1 (UX + Data Accuracy)

---

## 1. Sidebar Kategorisasi

### Current (flat list):
```
Dashboard
Inventory
Pembelian
Penjualan
Produk & Resep
Tax Simulator
Laporan P&L
Biaya
Partners
Invoices
Margin Protection
Bot Integration
```

### New (categorized):
```
🏠 Dashboard

💰 Transaksi
├── Pembelian (Transactions)
├── Penjualan (Sales)
└── Biaya (Expenses)

📦 Inventory
├── Stok Bahan (Inventory/Ingredients)
└── Produk & Resep (Products)

📊 Laporan
├── Laporan P&L
├── Tax Simulator
├── Margin Protection
└── Aging Report (placeholder, hide if no invoices)

👥 Partner
├── Daftar Partner
└── Invoices

⚙️ Settings
└── Bot Integration
```

### Implementation:

File: `resources/js/Layouts/AppLayout.tsx` (or sidebar component)

```typescript
const navigation = [
  { name: 'Dashboard', href: '/dashboard', icon: HomeIcon },
  {
    name: 'Transaksi',
    children: [
      { name: 'Pembelian', href: '/transactions' },
      { name: 'Penjualan', href: '/sales' },
      { name: 'Biaya', href: '/expenses' },
    ]
  },
  {
    name: 'Inventory',
    children: [
      { name: 'Stok Bahan', href: '/inventory' },
      { name: 'Produk & Resep', href: '/products' },
    ]
  },
  {
    name: 'Laporan',
    children: [
      { name: 'P&L Report', href: '/pnl' },
      { name: 'Tax Simulator', href: '/tax' },
      { name: 'Margin Protection', href: '/margin' },
      { name: 'Aging Report', href: '/reports/aging', hideIfNoInvoices: true },
    ]
  },
  {
    name: 'Partner',
    children: [
      { name: 'Daftar Partner', href: '/partners' },
      { name: 'Invoices', href: '/invoices' },
    ]
  },
  {
    name: 'Settings',
    children: [
      { name: 'Bot Integration', href: '/settings/bot' },
    ]
  },
]
```

### Notes:
- Aging Report: hide jika tenant gak punya invoice (B2C)
- Collapsible sidebar sections
- Mobile: hamburger menu dengan grouping yang sama
- Icon per kategori

---

## 2. COGS Weighted Average + Snapshot

### Problem:
```
Current:
├── unit_price UPDATE ke harga terakhir saat beli
├── COGS pakai harga terakhir
├── Historical margin BERUBAH kalau harga berubah
└── Reporting gak reliable
```

### Solution:
```
Weighted Average + Snapshot:
├── Saat beli: hitung weighted average, simpan di ingredient
├── Saat jual: snapshot COGS di sale record
├── Historical data gak berubah
└── Reporting akurat
```

### Database Changes:

**ingredients table tambah:**
```php
Schema::table('ingredients', function (Blueprint $table) {
    $table->decimal('weighted_avg_price', 12, 4)->default(0)->after('unit_price');
});
```

**sales table tambah:**
```php
Schema::table('sales', function (Blueprint $table) {
    $table->decimal('cogs_per_unit', 12, 4)->default(0)->after('total');
    $table->decimal('total_cogs', 12, 2)->default(0)->after('cogs_per_unit');
    $table->decimal('margin_percent', 5, 2)->default(0)->after('total_cogs');
});
```

### Logic Update:

**InventoryService.recordPurchase():**
```php
// AFTER saving transaction, UPDATE weighted average
$ingredient->refresh();

$totalValue = ($oldStock * $oldWeightedAvg) + ($quantity * $unitPrice);
$newStock = $ingredient->current_stock; // already updated

$ingredient->weighted_avg_price = $newStock > 0
    ? round($totalValue / $newStock, 4)
    : $unitPrice;
$ingredient->save();
```

**SaleService (new logic):**
```php
// When recording sale, SNAPSHOT COGS
$ingredient = $product->recipeItems->first()->ingredient;
$cogsPerUnit = $ingredient->weighted_avg_price * $recipeQuantity;
$totalCogs = $cogsPerUnit * $saleQuantity;
$marginPercent = $sellingPrice > 0
    ? round((($sellingPrice - $totalCogs) / $sellingPrice) * 100, 2)
    : 0;

Sale::create([
    // ... existing fields
    'cogs_per_unit' => $cogsPerUnit,
    'total_cogs' => $totalCogs,
    'margin_percent' => $marginPercent,
]);
```

### Migration:
```
1. Add weighted_avg_price to ingredients
2. Add cogs_per_unit, total_cogs, margin_percent to sales
3. Backfill: calculate weighted_avg for existing ingredients
4. Backfill: calculate COGS for existing sales (using current unit_price)
```

### Verification:
```
1. Buy tepung 10kg @10K → weighted_avg = 10K
2. Buy tepung 5kg @12K → weighted_avg = 10.67K
3. Jual product (butuh 1kg tepung) → COGS = 10.67K
4. Buy tepung 3kg @15K → weighted_avg = 11.5K
5. Cek laporan bulan lalu → COGS tetap 10.67K (gak berubah)
```

---

## Testing:

- [ ] Sidebar: all categories render correctly
- [ ] Sidebar: Aging hidden when no invoices
- [ ] Sidebar: mobile responsive
- [ ] COGS: weighted_avg updates correctly after each purchase
- [ ] COGS: sale snapshot is correct
- [ ] COGS: historical sales don't change when new purchase
- [ ] P&L report: uses snapshotted COGS

---

*Created: 18 June 2026*
