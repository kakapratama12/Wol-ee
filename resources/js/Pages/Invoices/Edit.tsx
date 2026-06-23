import { useState } from 'react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, Plus, Trash2 } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/Components/ui/button';
import { buttonVariants } from '@/Components/ui/button';
import { CurrencyInput } from '@/Components/ui/currency-input';
import CreatableCombobox from '@/Components/CreatableCombobox';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';
import { formatRupiah } from '@/lib/format';
import type { PageProps } from '@/types';

interface Customer {
    id: number;
    name: string;
}

interface InvoiceItem {
    id?: number;
    description: string;
    quantity: string;
    unit_price: string;
}

interface Invoice {
    id: number;
    invoice_number: string;
    partner_id: number;
    partner: string | null;
    amount: number;
    paid_amount: number;
    due_date: string;
    status: string;
    note: string | null;
    items: InvoiceItem[];
}

interface Props {
    invoice: Invoice;
    customers: Customer[];
}

export default function InvoicesEdit({ invoice, customers: initialCustomers }: Props) {
    const [customers, setCustomers] = useState(initialCustomers);
    const hasItems = invoice.items.length > 0;
    const [useItems, setUseItems] = useState(hasItems);

    const form = useForm<{
        partner_id: string;
        amount: string;
        due_date: string;
        note: string;
        items: { description: string; quantity: string; unit_price: string }[];
    }>({
        partner_id: String(invoice.partner_id),
        amount: hasItems ? '' : String(invoice.amount),
        due_date: invoice.due_date,
        note: invoice.note ?? '',
        items: invoice.items.map((item) => ({
            description: item.description,
            quantity: String(item.quantity),
            unit_price: String(item.unit_price),
        })),
    });

    const addItem = () => {
        form.setData('items', [...form.data.items, { description: '', quantity: '1', unit_price: '' }]);
    };
    const removeItem = (i: number) => {
        form.setData('items', form.data.items.filter((_, idx) => idx !== i));
    };
    const updateItem = (i: number, key: string, value: string) => {
        form.setData('items', form.data.items.map((row, idx) => (idx === i ? { ...row, [key]: value } : row)));
    };

    const subtotal = form.data.items.reduce((sum, row) => {
        return sum + (parseFloat(row.quantity) || 0) * (parseFloat(row.unit_price) || 0);
    }, 0);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        if (useItems && form.data.items.length > 0) {
            form.setData('amount', '');
        }
        form.put(`/invoices/${invoice.id}`);
    };

    return (
        <AppLayout title={`Edit ${invoice.invoice_number}`}>
            <Head title={`Edit ${invoice.invoice_number}`} />

            <div className="mb-4">
                <Link href="/invoices" className="inline-flex items-center text-sm text-muted-foreground hover:text-foreground">
                    <ArrowLeft className="mr-1 h-4 w-4" />
                    Kembali ke daftar
                </Link>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Edit Invoice {invoice.invoice_number}</CardTitle>
                </CardHeader>
                <CardContent>
                    <form onSubmit={submit} className="space-y-6">
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
                                            'X-XSRF-TOKEN': decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? ''),
                                        },
                                        body: JSON.stringify({ name, type: 'customer' }),
                                    });
                                    const data = await res.json();
                                    setCustomers((prev) => [...prev, { id: data.id, name: data.name }]);
                                    return { id: data.id, name: data.name };
                                }}
                            />
                            {form.errors.partner_id && <p className="mt-1 text-xs text-destructive">{form.errors.partner_id}</p>}
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
                                <CurrencyInput id="amount" value={form.data.amount} onChange={(v) => form.setData('amount', v)} />
                                {form.errors.amount && <p className="mt-1 text-xs text-destructive">{form.errors.amount}</p>}
                            </div>
                        )}

                        {useItems && (
                            <div className="space-y-3">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead className="w-1/3">Deskripsi</TableHead>
                                            <TableHead className="w-24">Qty</TableHead>
                                            <TableHead className="w-44">Harga Satuan</TableHead>
                                            <TableHead className="w-10"></TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {form.data.items.map((row, i) => (
                                            <TableRow key={i}>
                                                <TableCell>
                                                    <Input
                                                        value={row.description}
                                                        onChange={(e) => updateItem(i, 'description', e.target.value)}
                                                        placeholder="Deskripsi item"
                                                    />
                                                </TableCell>
                                                <TableCell>
                                                    <Input
                                                        type="number"
                                                        min="1"
                                                        value={row.quantity}
                                                        onChange={(e) => updateItem(i, 'quantity', e.target.value)}
                                                    />
                                                </TableCell>
                                                <TableCell>
                                                    <CurrencyInput
                                                        value={row.unit_price}
                                                        onChange={(v) => updateItem(i, 'unit_price', v)}
                                                    />
                                                </TableCell>
                                                <TableCell>
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon"
                                                        onClick={() => removeItem(i)}
                                                        disabled={form.data.items.length <= 1}
                                                    >
                                                        <Trash2 className="h-4 w-4 text-destructive" />
                                                    </Button>
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                                <Button type="button" variant="outline" size="sm" onClick={addItem}>
                                    <Plus className="mr-1 h-4 w-4" />
                                    Tambah Item
                                </Button>
                                {subtotal > 0 && (
                                    <div className="flex justify-end text-sm font-medium">
                                        Subtotal: {formatRupiah(subtotal)}
                                    </div>
                                )}
                            </div>
                        )}

                        <div>
                            <Label htmlFor="due_date">Jatuh Tempo</Label>
                            <Input id="due_date" type="date" value={form.data.due_date} onChange={(e) => form.setData('due_date', e.target.value)} />
                            {form.errors.due_date && <p className="mt-1 text-xs text-destructive">{form.errors.due_date}</p>}
                        </div>

                        <div>
                            <Label htmlFor="note">Catatan</Label>
                            <Input id="note" value={form.data.note} onChange={(e) => form.setData('note', e.target.value)} />
                        </div>

                        <div className="flex justify-end gap-2">
                            <Link href="/invoices" className={buttonVariants({ variant: 'outline' })}>
                                Batal
                            </Link>
                            <Button type="submit" disabled={form.processing}>
                                Simpan Perubahan
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </AppLayout>
    );
}
