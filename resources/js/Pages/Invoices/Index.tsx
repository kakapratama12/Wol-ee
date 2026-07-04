import { useState } from 'react';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Archive, ArrowLeft, Plus, Trash2 } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import InvoiceStatusBadge from '@/Components/InvoiceStatusBadge';
import Modal from '@/Components/ui/modal';
import { Button } from '@/Components/ui/button';
import { CurrencyInput } from '@/Components/ui/currency-input';
import CreatableCombobox from '@/Components/CreatableCombobox';
import InvoiceFormFields, { type FeeRow } from '@/Components/InvoiceFormFields';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { MobileCardTable } from '@/Components/ui/mobile-card-table';
import { formatDate, formatRupiah } from '@/lib/format';
import type { PageProps } from '@/types';

interface Invoice {
    id: number;
    invoice_number: string;
    po_number: string | null;
    partner: string | null;
    amount: number;
    paid_amount: number;
    remaining: number;
    due_date: string;
    status: string;
}

interface Customer {
    id: number;
    name: string;
}

interface Props {
    invoices: Invoice[];
    customers: Customer[];
    filters: { status: string; archived: boolean };
}

export default function InvoicesIndex({ invoices, customers: initialCustomers, filters }: Props) {
    const { props } = usePage<PageProps>();
    const isOwner = props.auth.user.role === 'pengelola';
    const [formOpen, setFormOpen] = useState(false);
    const [customers, setCustomers] = useState(initialCustomers);

    const form = useForm<{
        partner_id: string;
        po_number: string;
        amount: string;
        due_date: string;
        note: string;
        idempotency_key: string;
        status: string;
        items: { description: string; quantity: string; unit_price: string }[];
        fees: FeeRow[];
    }>({
        partner_id: '',
        po_number: '',
        amount: '',
        due_date: '',
        note: '',
        idempotency_key: '',
        status: '',
        items: [],
        fees: [],
    });

    const [useItems, setUseItems] = useState(false);

    const addItem = () => {
        form.setData('items', [
            ...form.data.items,
            { description: '', quantity: '1', unit_price: '' },
        ]);
    };
    const removeItem = (i: number) => {
        form.setData(
            'items',
            form.data.items.filter((_, idx) => idx !== i),
        );
    };
    const updateItem = (i: number, key: string, value: string) => {
        form.setData(
            'items',
            form.data.items.map((row, idx) => (idx === i ? { ...row, [key]: value } : row)),
        );
    };

    const addFee = () => {
        form.setData('fees', [...form.data.fees, { name: '', type: 'fixed', value: '' }]);
    };
    const removeFee = (i: number) => {
        form.setData(
            'fees',
            form.data.fees.filter((_, idx) => idx !== i),
        );
    };
    const updateFee = (i: number, key: keyof FeeRow, value: string) => {
        form.setData(
            'fees',
            form.data.fees.map((row, idx) => (idx === i ? { ...row, [key]: value } : row)),
        );
    };

    const setFilter = (status: string) => {
        router.get('/invoices', { ...filters, status }, { preserveState: true });
    };

    const toggleArchived = () => {
        router.get('/invoices', { ...filters, archived: !filters.archived }, { preserveState: true });
    };

    const handleDelete = (invoice: Invoice) => {
        if (!confirm(`Hapus invoice "${invoice.invoice_number}"?`)) return;
        router.delete(route('invoices.destroy', invoice.id));
    };

    const handleArchive = (invoice: Invoice) => {
        if (!confirm(`Arsipkan invoice "${invoice.invoice_number}"?`)) return;
        router.post(route('invoices.archive', invoice.id));
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        if (useItems && form.data.items.length > 0) {
            form.setData('amount', '');
        }
        form.setData('idempotency_key', crypto.randomUUID());
        form.post('/invoices', {
            onSuccess: () => {
                setFormOpen(false);
                setUseItems(false);
            },
        });
    };

    const submitDraft = (e: React.FormEvent) => {
        e.preventDefault();
        if (useItems && form.data.items.length > 0) {
            form.setData('amount', '');
        }
        form.setData('idempotency_key', crypto.randomUUID());
        form.post('/invoices', {
            onSuccess: () => {
                setFormOpen(false);
                setUseItems(false);
            },
        });
    };

    return (
        <AppLayout title="Invoices">
            <Head title="Invoices" />

            <div className="mb-4">
                <Link
                    href="/dashboard"
                    className="inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground"
                >
                    <ArrowLeft className="h-4 w-4" />
                    Kembali
                </Link>
            </div>

            {/* Filter + Add button */}
            <div className="mb-4 flex flex-wrap items-center gap-2">
                <div className="flex flex-wrap gap-2">
                    {[
                        { key: '', label: 'Semua' },
                        { key: 'draft', label: 'Draft' },
                        { key: 'outstanding', label: 'Outstanding' },
                        { key: 'partial', label: 'Sebagian' },
                        { key: 'paid', label: 'Lunas' },
                    ].map((f) => (
                        <Button
                            key={f.key}
                            size="sm"
                            variant={filters.status === f.key ? 'default' : 'outline'}
                            onClick={() => setFilter(f.key)}
                        >
                            {f.label}
                        </Button>
                    ))}
                    <Button
                        size="sm"
                        variant={filters.archived ? 'default' : 'outline'}
                        onClick={toggleArchived}
                    >
                        <Archive className="mr-1 h-4 w-4" />
                        {filters.archived ? 'Sembunyikan Arsip' : 'Tampilkan Arsip'}
                    </Button>
                </div>
                {isOwner && customers.length > 0 && (
                    <Button size="sm" onClick={() => setFormOpen(true)} className="ml-auto">
                        <Plus className="mr-1 h-4 w-4" />
                        Tambah
                    </Button>
                )}
            </div>

            {/* Table */}
            <div className="rounded-lg border border-border bg-card">
                <MobileCardTable
                    data={invoices}
                    keyFn={(inv) => inv.id}
                    emptyMessage="Belum ada invoice."
                    columns={[
                        {
                            header: 'Nomor',
                            render: (inv) => (
                                <Link
                                    href={`/invoices/${inv.id}`}
                                    className="font-medium hover:underline"
                                >
                                    {inv.invoice_number}
                                </Link>
                            ),
                            primary: true,
                        },
                        {
                            header: 'PO',
                            render: (inv) => inv.po_number || '-',
                            hideOnMobile: true,
                        },
                        {
                            header: 'Partner',
                            render: (inv) => inv.partner ?? '-',
                            secondary: true,
                        },
                        {
                            header: 'Nominal',
                            render: (inv) => formatRupiah(inv.amount),
                            amount: true,
                        },
                        {
                            header: 'Sisa',
                            render: (inv) => formatRupiah(inv.remaining),
                            hideOnMobile: true,
                        },
                        {
                            header: 'Jatuh Tempo',
                            render: (inv) => formatDate(inv.due_date),
                            secondary: true,
                        },
                        {
                            header: 'Status',
                            render: (inv) => <InvoiceStatusBadge status={inv.status} />,
                            badge: true,
                        },
                    ]}
                    actions={(inv) =>
                        isOwner ? (
                            <div className="flex items-center justify-end gap-1">
                                {(inv.status === 'draft' || inv.status === 'outstanding') && (
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => handleDelete(inv)}
                                    >
                                        <Trash2 className="h-4 w-4 text-red-500" />
                                    </Button>
                                )}
                                {(inv.status === 'partial' || inv.status === 'paid') && !filters.archived && (
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => handleArchive(inv)}
                                    >
                                        <Archive className="h-4 w-4" />
                                    </Button>
                                )}
                            </div>
                        ) : null
                    }
                />
            </div>

            <Modal
                open={formOpen}
                onClose={() => setFormOpen(false)}
                title="Buat Invoice"
                size="xl"
            >
                <form onSubmit={submit} className="space-y-4">
                    <div>
                        <Label htmlFor="partner_id">Customer</Label>
                        <CreatableCombobox
                            options={customers}
                            value={form.data.partner_id}
                            onChange={(v) => form.setData('partner_id', String(v))}
                            placeholder="- Pilih atau buat customer baru -"
                            onCreate={async (name) => {
                                const res = await fetch('/partners/json', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'X-Requested-With': 'XMLHttpRequest',
                                        'X-XSRF-TOKEN': decodeURIComponent(
                                            document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] || '',
                                        ),
                                    },
                                    body: JSON.stringify({ name, type: 'customer' }),
                                });
                                const data = await res.json();
                                setCustomers((prev) => [...prev, { id: data.id, name: data.name }]);
                                return { id: data.id, name: data.name };
                            }}
                        />
                        {form.errors.partner_id && (
                            <p className="mt-1 text-xs text-destructive">
                                {form.errors.partner_id}
                            </p>
                        )}
                    </div>
                    <div>
                        <Label htmlFor="po_number">Nomor PO (opsional)</Label>
                        <Input
                            id="po_number"
                            value={form.data.po_number}
                            onChange={(e) => form.setData('po_number', e.target.value)}
                            placeholder="Masukkan nomor PO dari customer"
                        />
                    </div>
                    <div className="flex items-center gap-2">
                        <input
                            id="useItems"
                            type="checkbox"
                            checked={useItems}
                            onChange={(e) => {
                                setUseItems(e.target.checked);
                                if (e.target.checked) {
                                    form.setData('amount', '');
                                    if (form.data.items.length === 0) addItem();
                                } else {
                                    form.setData('items', []);
                                }
                            }}
                            className="h-4 w-4 rounded border-gray-300"
                        />
                        <Label htmlFor="useItems">Rincian item</Label>
                    </div>
                    {!useItems && (
                        <div>
                            <Label htmlFor="amount">Nominal (Rp)</Label>
                            <CurrencyInput
                                id="amount"
                                value={form.data.amount}
                                onChange={(v) => form.setData('amount', v)}
                            />
                            {form.errors.amount && (
                                <p className="mt-1 text-xs text-destructive">
                                    {form.errors.amount}
                                </p>
                            )}
                        </div>
                    )}
                    {useItems && (
                        <InvoiceFormFields
                            items={form.data.items}
                            fees={form.data.fees}
                            onUpdateItem={updateItem}
                            onAddItem={addItem}
                            onRemoveItem={removeItem}
                            onUpdateFee={updateFee}
                            onAddFee={addFee}
                            onRemoveFee={removeFee}
                        />
                    )}

                    <div className="w-48">
                        <Label htmlFor="due_date">Jatuh Tempo</Label>
                        <Input
                            id="due_date"
                            type="date"
                            value={form.data.due_date}
                            onChange={(e) => form.setData('due_date', e.target.value)}
                        />
                        {form.errors.due_date && (
                            <p className="mt-1 text-xs text-destructive">{form.errors.due_date}</p>
                        )}
                    </div>
                    <div>
                        <Label htmlFor="note">Catatan</Label>
                        <Input
                            id="note"
                            value={form.data.note}
                            onChange={(e) => form.setData('note', e.target.value)}
                        />
                    </div>
                    <div className="flex justify-end gap-2">
                        <Button type="button" variant="outline" onClick={() => setFormOpen(false)}>
                            Batal
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            disabled={form.processing}
                            onClick={(e) => {
                                form.setData('status', 'draft');
                                submitDraft(e);
                            }}
                        >
                            Simpan Draft
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            Simpan & Kirim
                        </Button>
                    </div>
                </form>
            </Modal>
        </AppLayout>
    );
}
