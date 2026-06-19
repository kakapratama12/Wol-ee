# Sprint 2 Frontend Spec: Partner & Invoice Pages

> **Status:** Pending - need implementation
> **Backend:** Ready (Sprint 2 API)
> **Framework:** React (Inertia.js + TypeScript)

---

## 1. Pages to Create

### 1.1 Partner List Page
URL: /partners

Layout:
```
+------------------------------------------+
| Partners                          [+ Add] |
+------------------------------------------+
| Filter: [All] [Customer] [Supplier]      |
+------------------------------------------+
| Name          | Type     | Contact | ...  |
| CV Tepung     | Supplier | Pak Budi| ...  |
| Kantor Pak J  | Customer | Pak Joko| ...  |
+------------------------------------------+
```

Features:
- Table list partners
- Filter by type (customer/supplier)
- Search by name
- Click row → detail page
- Add button → modal/form

### 1.2 Partner Detail Page
URL: /partners/{id}

Layout:
```
+------------------------------------------+
| CV Tepung Sejahtera            [Edit]    |
+------------------------------------------+
| Type: Supplier                           |
| Contact: Pak Budi                        |
| Phone: 081234567890                      |
+------------------------------------------+
| Outstanding Invoices                     |
+------------------------------------------+
| INV-202606-001 | Rp 5M  | 30 hari | ...  |
| INV-202606-002 | Rp 3M  | 15 hari | ...  |
+------------------------------------------+
| Aging Summary                            |
+------------------------------------------+
| Current: Rp 5M                           |
| 1-2 months: Rp 3M                        |
| 2-3 months: Rp 0                         |
| 3+ months: Rp 0                          |
+------------------------------------------+
| Transaction History                      |
+------------------------------------------+
| Date       | Type    | Amount            |
| 2026-06-15 | Beli    | Rp 500K           |
+------------------------------------------+
```

### 1.3 Invoice List Page
URL: /invoices

Layout:
```
+------------------------------------------+
| Invoices                       [+ Add]   |
+------------------------------------------+
| Filter: [All] [Outstanding] [Paid]       |
+------------------------------------------+
| Number      | Partner  | Amount | Due    |
| INV-202606  | Pak Joko | Rp 5M  | 15 Jul |
| INV-202606  | CV A     | Rp 3M  | 20 Jul |
+------------------------------------------+
```

### 1.4 Invoice Detail / Pay Page
URL: /invoices/{id}

Layout:
```
+------------------------------------------+
| INV-202606-001                           |
+------------------------------------------+
| Partner: Kantor Pak Joko                 |
| Amount: Rp 5,000,000                     |
| Paid: Rp 2,000,000                       |
| Remaining: Rp 3,000,000                  |
| Due: 15 Juli 2026                        |
| Status: Partial                          |
+------------------------------------------+
| [Record Payment]                         |
+------------------------------------------+
| Amount: [        ]                       |
| Date:   [        ]                       |
| [Submit]                                 |
+------------------------------------------+
| Payment History                          |
+------------------------------------------+
| 2026-06-20 | Rp 2,000,000               |
+------------------------------------------+
```

---

## 2. Components to Create/Reuse

- Table component (reuse existing)
- Modal/Form component (reuse existing)
- Status badge (outstanding/partial/paid)
- Aging bucket display
- Amount formatter (Rp X.XXX.XXX)

---

## 3. Navigation (Sidebar)

Add to sidebar:
```
Dashboard
Inventory
Transactions
Sales
Products
Expenses
Reports
├── P&L
├── Tax Simulator
├── Margin
├── Partners     <-- NEW
├── Invoices     <-- NEW
Settings
├── Bot Integration
```

---

## 4. Backend Routes (already exist)

```
GET    /partners              → PartnerController@index
GET    /partners/{id}         → PartnerController@show
POST   /partners              → PartnerController@store
PUT    /partners/{id}         → PartnerController@update
DELETE /partners/{id}         → PartnerController@destroy

GET    /invoices              → InvoiceController@index
GET    /invoices/{id}         → InvoiceController@show
POST   /invoices              → InvoiceController@store
POST   /invoices/{id}/pay     → InvoiceController@pay
```

---

## 5. Notes

- Use existing design system (colors, components)
- Mobile responsive
- Owner only: create/edit/delete
- Admin: view only

---

*Created: 18 June 2026*
