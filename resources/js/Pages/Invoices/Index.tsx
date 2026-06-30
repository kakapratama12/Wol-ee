import { useState } from 'react';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { FileText, Plus } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import InvoiceStatusBadge from '@/Components/InvoiceStatusBadge';
import Modal from '@/Components/ui/modal';
import { Button } from '@/Components/ui/button';
import { CurrencyInput } from '@/Components/ui/currency-input';
import CreatableCombobox from '@/Components/CreatableCombobox';
import InvoiceFormFields, { type FeeRow } from '@/Components/InvoiceFormFields';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';
import { formatDate, formatRupiah } from '@/lib/format';
import type { PageProps } from '@/types';

interface Invoice {
    id: number;
    invoice_number: string;
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
    filters: { status: string };
}

export default function InvoicesIndex({ invoices, customers: initialCustomers, filters }: Props) {
    const { props } = usePage<PageProps>();
    const isOwner = props.auth.user.role === 'owner';
    const [formOpen, setFormOpen] = useState(false);
    const [customers, setCustomers] = useState(initialCustomers);

    const form = useForm<{
        partner_id: string;
        amount: string;
        due_date: string;
        note: string;
        items: { description: string; quantity: string; unit_price: string }[];
        fees: FeeRow[];
    }>({
        partner_id: '',
        amount: '',
        due_date: '',
        note: '',
        items: [],
        fees: [],
    });

    const [useItems, setUseItems] = useState(false);

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

    const setFilter = (status: string) => {
        router.get('/invoices', status ? { status } : {}, { preserveState: true });
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        if (useItems && form.data.items.length > 0) {
            form.setData('amount', '');
        }
        form.post('/invoices', { onSuccess: () => {
            setFormOpen(false);
            setUseItems(false);
        }});
    };

    return (
        <AppLayout title="Invoices">
            <Head title="Invoices" />

            <Card>
                <CardHeader className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <CardTitle className="flex items-center gap-2">
                        <FileText className="h-5 w-5" />
                        Invoices
                    </CardTitle>
                    {isOwner && customers.length > 0 && (
                        <Button onClick={() => setFormOpen(true)}>
                            <Plus className="mr-2 h-4 w-4" />
                            Tambah
                        </Button>
                    )}
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="flex gap-2">
                        {[
                            { key: '', label: 'Semua' },
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
                    </div>

                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Nomor</TableHead>
                                <TableHead>Partner</TableHead>
                                <TableHead className="text-right">Nominal</TableHead>
                                <TableHead className="text-right">Sisa</TableHead>
                                <TableHead>Jatuh Tempo</TableHead>
                                <TableHead>Status</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {invoices.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={6} className="text-center text-muted-foreground">
                                        Belum ada invoice.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                invoices.map((invoice) => (
                                    <TableRow key={invoice.id}>
                                        <TableCell>
                                            <Link href={`/invoices/${invoice.id}`} className="font-medium hover:underline">
                                                {invoice.invoice_number}
                                            </Link>
                                        </TableCell>
                                        <TableCell>{invoice.partner ?? '-'}</TableCell>
                                        <TableCell className="text-right text-number">{formatRupiah(invoice.amount)}</TableCell>
                                        <TableCell className="text-right text-number">{formatRupiah(invoice.remaining)}</TableCell>
                                        <TableCell>{formatDate(invoice.due_date)}</TableCell>
                                        <TableCell>
                                            <InvoiceStatusBadge status={invoice.status} />
                                        </TableCell>
                                    </TableRow>
                                ))
                            )}
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>

            <Modal open={formOpen} onClose={() => setFormOpen(false)} title="Buat Invoice" size="xl">
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
                                        'X-XSRF-TOKEN': decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] || ''),
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
                        <Input id="due_date" type="date" value={form.data.due_date} onChange={(e) => form.setData('due_date', e.target.value)} />
                        {form.errors.due_date && <p className="mt-1 text-xs text-destructive">{form.errors.due_date}</p>}
                    </div>
                    <div>
                        <Label htmlFor="note">Catatan</Label>
                        <Input id="note" value={form.data.note} onChange={(e) => form.setData('note', e.target.value)} />
                    </div>
                    <div className="flex justify-end gap-2">
                        <Button type="button" variant="outline" onClick={() => setFormOpen(false)}>
                            Batal
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            Simpan
                        </Button>
                    </div>
                </form>
            </Modal>
        </AppLayout>
    );
}
