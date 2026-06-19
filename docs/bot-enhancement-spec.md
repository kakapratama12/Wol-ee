# Bot Enhancement Spec: Batch Entry + Item Not Found Flow

> **Status:** Ready for implementation
> **Priority:** P1 (UX + Data Quality)

---

## 1. Item Not Found Flow

### Rule:
**NO AUTO-CREATE for all items (products, ingredients, partners)**

When user mentions an item that doesn't exist:
1. List available items
2. Provide direct link to dashboard page
3. Do NOT map to "paling mirip" (causes wrong data)

### Product not found:
```
User: "Jual Matcha Latte 10"
Bot: "Produk 'Matcha Latte' belum ada.

Produk yang tersedia:
- Matcha
- Croissant
- Latte

Tambah produk baru: https://{APP_URL}/products"
```

### Ingredient not found:
```
User: "Beli tepung organik 5kg Rp 50rb"
Bot: "Bahan 'tepung organik' belum ada.

Bahan yang tersedia:
- Tepung terigu
- Tepung beras
- Gula pasir

Tambah bahan baru: https://{APP_URL}/inventory"
```

### Partner not found:
```
User: "Beli dari Pak Budi 5kg Rp 50rb"
Bot: "'Pak Budi' belum ada di daftar partner.

Partner yang tersedia:
- CV Tepung Sejahtera (supplier)
- Kantor Pak Joko (customer)

Tambah partner baru: https://{APP_URL}/partners"
```

### Implementation:
- APP_URL from env/config
- Link format: https://{APP_URL}/{page}
- Always show available items before link

---

## 2. Batch Entry (Multi-Item Sales)

### Use case:
B2C businesses (cafe/bakery) - admin receives daily sales report from barista, then copas to bot.

### Flow:
```
1. Barista kirim laporan ke admin via WA
2. Admin copas ke bot
3. Bot parse semua item
4. Bot tampilkan ringkasan + total
5. Admin konfirmasi (y/n)
6. Bot simpan semua sekaligus
```

### Supported formats:

**Format 1: Comma-separated**
```
matcha 10, croissant 5, latte 8
```

**Format 2: Multi-line**
```
matcha 10
croissant 5
latte 8
```

**Format 3: Mixed (with prices)**
```
matcha 10 250000, croissant 5 175000
```

**Format 4: List with labels**
```
Matcha: 10
Croissant: 5
Latte: 8
```

### Parser logic:
```
1. Split input by: comma, newline, semicolon
2. For each item:
   a. Extract product name (letters)
   b. Extract quantity (numbers)
   c. Extract price if provided (optional)
3. Match product name to catalog (EXACT match only)
4. Calculate subtotal (qty × selling_price from catalog)
5. Sum all subtotals
```

### Response format:
```
Bot: "Laporan hari ini:

✅ Matcha Latte: 10 × Rp 25.000 = Rp 250.000
✅ Croissant: 5 × Rp 35.000 = Rp 175.000
✅ Latte: 8 × Rp 20.000 = Rp 160.000

Total: Rp 585.000

Betul? (y/n/batal)"
```

### Error handling:

**Item not found:**
```
User: "matcha 10, croissant 5, roti 3"
Bot: "Laporan hari ini:

✅ Matcha Latte: 10 × Rp 25.000 = Rp 250.000
✅ Croissant: 5 × Rp 35.000 = Rp 175.000
❌ 'roti' - produk tidak ditemukan

Produk yang tersedia: Matcha, Croissant, Latte

Lanjut tanpa 'roti'? (y/n)"
```

**Quantity missing:**
```
User: "matcha, croissant 5"
Bot: "⚠️ 'matcha' - jumlah tidak jelas.
Ketik jumlah untuk 'matcha' (contoh: 10)"
```

**All items not found:**
```
User: "es krim 10, sushi 5"
Bot: "❌ Tidak ada produk yang cocok.

Produk yang tersedia:
- Matcha (Rp 25.000)
- Croissant (Rp 35.000)
- Latte (Rp 20.000)

Tambah produk baru: https://{APP_URL}/products"
```

### Implementation:
- Product matching: EXACT match only (lowercase comparison)
- No fuzzy matching (prevents wrong mapping)
- Batch insert to database (atomic)
- Rollback if any critical error

---

## 3. Batch Entry for Purchases

### Similar to sales, but for ingredients:

```
User: "Beli: tepung 10kg 100000, gula 5kg 50000"
Bot: "Pembelian:

✅ Tepung terigu: 10kg × Rp 10.000 = Rp 100.000
✅ Gula pasir: 5kg × Rp 10.000 = Rp 50.000

Total: Rp 150.000
Stok bertambah semua. Betul? (y/n)"
```

### Partner in batch:
```
User: "Beli dari CV Tepung: tepung 10kg 100000, gula 5kg 50000"
Bot: "Pembelian dari CV Tepung Sejahtera:

✅ Tepung terigu: 10kg × Rp 10.000 = Rp 100.000
✅ Gula pasir: 5kg × Rp 10.000 = Rp 50.000

Total: Rp 150.000
Betul? (y/n)"
```

---

## 4. Daily Summary (Optional - less accurate)

### For businesses that don't track per-product:
```
User: "Hari ini jual 20jt"
Bot: "⚠️ Tanpa detail produk, inventory tidak terupdate.
Stok tidak berkurang, COGS tidak dihitung.

Lanjut? (y/n)"

User: "n"
Bot: "Ketik detail: matcha 10, croissant 5, ..."
```

### Recommendation:
- Discourage daily summary without details
- Always suggest batch entry instead
- Only allow if user insists

---

## 5. Copy Token Button Fix

### Issue:
Copy button on Settings > Bot Integration doesn't work.

### Fix:
```javascript
const copyToken = async () => {
  if (!plainToken) return;
  try {
    await navigator.clipboard.writeText(plainToken);
    // Show success toast
  } catch (err) {
    // Fallback: select input + execCommand
    inputRef.current?.select();
    document.execCommand('copy');
  }
};
```

### Also fix:
- Mobile: clipboard API might not work
- Add visual feedback (checkmark, "Copied!")

---

## Testing:

- [ ] Product not found → shows list + link
- [ ] Ingredient not found → shows list + link
- [ ] Partner not found → shows list + link
- [ ] Batch sale: comma-separated
- [ ] Batch sale: multi-line
- [ ] Batch sale: mixed format
- [ ] Batch sale: partial error (some found, some not)
- [ ] Batch sale: all not found
- [ ] Batch purchase: with partner
- [ ] Copy token button works on mobile
- [ ] Copy token shows success feedback

---

*Created: 18 June 2026*
