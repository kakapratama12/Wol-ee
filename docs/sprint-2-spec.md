# Sprint 2 Spec: Partner & Invoice

> **Status:** Ready for implementation
> **Depends on:** Sprint 1.1 (multi-tenant) ✅

---

## 1. Database Schema

### 1.1 partners table

```php
Schema::create('partners', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
    $table->string('name');
    $table->enum('type', ['customer', 'supplier']); // customer = jual ke, supplier = beli dari
    $table->string('contact')->nullable(); // nama PIC
    $table->string('phone')->nullable();
    $table->string('email')->nullable();
    $table->text('address')->nullable();
    $table->timestamps();

    $table->unique(['tenant_id', 'name']); // nama partner unik per tenant
});
```

### 1.2 invoices table

```php
Schema::create('invoices', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
    $table->foreignId('partner_id')->constrained()->cascadeOnDelete();
    $table->string('invoice_number'); // auto-generated: INV-YYYYMM-XXX
    $table->decimal('amount', 15, 2); // total tagihan
    $table->decimal('paid_amount', 15, 2)->default(0); // sudah dibayar
    $table->date('due_date'); // jatuh tempo
    $table->enum('status', ['outstanding', 'partial', 'paid'])->default('outstanding');
    $table->text('note')->nullable();
    $table->timestamp('paid_at')->nullable();
    $table->timestamps();

    $table->unique(['tenant_id', 'invoice_number']); // nomor invoice unik per tenant
});
```

---

## 2. Models

### 2.1 Partner Model

```php
// app/Models/Partner.php

use App\Models\Concerns\BelongsToTenant;

class Partner extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'name', 'type', 'contact', 'phone', 'email', 'address'
    ];

    // Relations
    public function invoices(): HasMany { ... }
    public function transactions(): HasMany { ... } // pembelian dari supplier
    public function sales(): HasMany { ... } // penjualan ke customer
}
```

### 2.2 Invoice Model

```php
// app/Models/Invoice.php

use App\Models\Concerns\BelongsToTenant;

class Invoice extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'partner_id', 'invoice_number', 'amount',
        'paid_amount', 'due_date', 'status', 'note', 'paid_at'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'due_date' => 'date',
        'paid_at' => 'datetime',
    ];

    // Relations
    public function partner(): BelongsTo { ... }
}
```

---

## 3. Business Rules

### 3.1 Invoice Number Format
```
Format: INV-YYYYMM-XXX
Contoh: INV-202606-001, INV-202606-002

XXX = auto-increment per tenant per bulan
Reset setiap bulan
```

### 3.2 Invoice Status Logic
```
status = 'outstanding'  → paid_amount == 0
status = 'partial'      → 0 < paid_amount < amount
status = 'paid'         → paid_amount >= amount
```

### 3.3 Aging Calculation
```
Hari sejak due_date:
0-30 hari   → "Current"
31-60 hari  → "1-2 months overdue"
61-90 hari  → "2-3 months overdue"
90+ hari    → "3+ months overdue"

Note: Aging dihitung dari due_date, BUKAN dari invoice date
```

### 3.4 Partner Type Rules
```
type = 'customer':
  → Bisa punya invoices (tagihan ke customer)
  → Bisa punya sales (penjualan)

type = 'supplier':
  → Bisa punya transactions (pembelian dari supplier)
  → Gak perlu invoices (biasanya langsung bayar)
```

---

## 4. API Endpoints

### 4.1 Partners

```
GET    /api/partners              → List semua partner (filter by type)
GET    /api/partners/{id}         → Detail partner + history
POST   /api/partners              → Create partner
PUT    /api/partners/{id}         → Update partner
DELETE /api/partners/{id}         → Delete partner (soft check, ada invoice?)
GET    /api/partners/{id}/aging   → Aging summary untuk partner ini
```

**Request POST /api/partners:**
```json
{
  "name": "CV Maju Jaya",
  "type": "customer",
  "contact": "Budi",
  "phone": "08123456789",
  "email": "budi@maju.com",
  "address": "Jl. Sudirman 123"
}
```

**Response GET /api/partners/{id}:**
```json
{
  "id": 1,
  "name": "CV Maju Jaya",
  "type": "customer",
  "contact": "Budi",
  "phone": "08123456789",
  "email": "budi@maju.com",
  "address": "Jl. Sudirman 123",
  "outstanding_invoices": 3,
  "total_outstanding": 15000000,
  "aging": {
    "current": 8000000,
    "1-2_months": 5000000,
    "2-3_months": 1500000,
    "3_plus": 500000
  }
}
```

### 4.2 Invoices

```
GET    /api/invoices              → List semua invoice (filter by status)
GET    /api/invoices/{id}         → Detail invoice
POST   /api/invoices              → Create invoice
PUT    /api/invoices/{id}         → Update invoice
POST   /api/invoices/{id}/pay     → Record pembayaran
GET    /api/invoices/outstanding  → List invoice outstanding
```

**Request POST /api/invoices:**
```json
{
  "partner_id": 1,
  "amount": 5000000,
  "due_date": "2026-07-15",
  "note": "Tagihan bulan Juni"
}
```

**Request POST /api/invoices/{id}/pay:**
```json
{
  "amount": 2000000, // jumlah yang dibayar
  "paid_at": "2026-06-20" // optional, default now
}
```

**Response POST /api/invoices/{id}/pay:**
```json
{
  "message": "Pembayaran tercatat.",
  "invoice": {
    "id": 1,
    "invoice_number": "INV-202606-001",
    "amount": 5000000,
    "paid_amount": 2000000,
    "remaining": 3000000,
    "status": "partial"
  }
}
```

### 4.3 Aging Report

```
GET /api/reports/aging
```

**Response:**
```json
{
  "summary": {
    "total_outstanding": 18000000,
    "total_partners": 5
  },
  "by_partner": [
    {
      "partner_id": 1,
      "partner": "CV Maju Jaya",
      "total": 15000000,
      "current": 8000000,
      "1-2_months": 5000000,
      "2-3_months": 1500000,
      "3_plus": 500000
    }
  ],
  "by_aging": {
    "current": 10000000,
    "1-2_months": 5000000,
    "2-3_months": 2000000,
    "3_plus": 1000000
  }
}
```

---

## 5. Controller Logic

### 5.1 PartnerController

```php
// Store: validate, create, return partner
// Update: validate, update, return partner
// Destroy: check if partner has outstanding invoices
//   → if yes: return error "Partner masih punya invoice outstanding"
//   → if no: delete
// Aging: calculate from invoices, return aging summary
```

### 5.2 InvoiceController

```php
// Store: auto-generate invoice_number, create invoice
// Pay: validate amount <= remaining, update paid_amount, update status
// Outstanding: query where status != 'paid'
```

### 5.3 AgingService

```php
// Calculate aging per partner
// Group by aging buckets (0-30, 31-60, 61-90, 90+)
// Return summary + detail
```

---

## 6. Migration Notes

### 6.1 Run Order
```bash
php artisan migrate
# Run: 2026_06_17_200000_create_partners_table.php
# Run: 2026_06_17_200001_create_invoices_table.php
```

### 6.2 Seeder
```php
// Create sample partners for kafe-contoh tenant
$tenant = Tenant::where('slug', 'kafe-contoh')->first();

Partner::create([
    'tenant_id' => $tenant->id,
    'name' => 'CV Tepung Sejahtera',
    'type' => 'supplier',
    'contact' => 'Pak Budi',
    'phone' => '081234567890',
]);

Partner::create([
    'tenant_id' => $tenant->id,
    'name' => 'Kantor Pak Joko',
    'type' => 'customer',
    'contact' => 'Pak Joko',
    'phone' => '081234567891',
]);
```

---

## 7. Edge Cases

| Case | Handling |
|------|----------|
| Partner punya invoice outstanding | Gak bisa delete → return error |
| Bayar melebihi amount | Gak boleh → return error "Melebihi tagihan" |
| Invoice number conflict | Auto-generate, handle conflict |
| Aging hari ini < due_date | Status "current" (belum jatuh tempo) |
| Partner type customer tapi input transaksi beli | Gak boleh → validasi di controller |

---

## 8. Dashboard Pages (Future)

> **Note:** Dashboard UI untuk Sprint 2 nanti. Yang penting API ready.

- Partner list page
- Partner detail page (info + aging + history)
- Invoice list page (filter by status)
- Invoice detail page + form bayar
- Aging report page

---

*Created: 17 June 2026*
*Author: Sena*
