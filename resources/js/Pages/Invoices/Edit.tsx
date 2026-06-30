import { useState } from 'react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/Components/ui/button';
import { buttonVariants } from '@/Components/ui/button';
import { CurrencyInput } from '@/Components/ui/currency-input';
import CreatableCombobox from '@/Components/CreatableCombobox';
import InvoiceFormFields, { type FeeRow } from '@/Components/InvoiceFormFields';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
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

interface InvoiceFee {
    id?: number;
    name: string;
    type: 'fixed' | 'percentage';
    value: string;
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
    fees: InvoiceFee[];
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
        fees: FeeRow[];
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
        fees: (invoice.fees ?? []).map((fee) => ({
            name: fee.name,
            type: fee.type,
            value: String(fee.value),
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

    const addFee = () => {
        form.setData('fees', [...form.data.fees, { name: '', type: 'fixed', value: '' }]);
    };
    const removeFee = (i: number) => {
        form.setData('fees', form.data.fees.filter((_, idx) => idx !== i));
    };
    const updateFee = (i: number, key: keyof FeeRow, value: string) => {
        form.setData('fees', form.data.fees.map((row, idx) => (idx === i ? { ...row, [key]: value } : row)));
    };

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
                                        form.setData('fees', []);
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
