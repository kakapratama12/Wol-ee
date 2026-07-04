import { useState } from 'react';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { type PageProps } from '@/types';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import Modal from '@/Components/ui/modal';
import Pagination from '@/Components/Pagination';
import { Button } from '@/Components/ui/button';
import { CurrencyInput } from '@/Components/ui/currency-input';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { MobileCardTable } from '@/Components/ui/mobile-card-table';
import { Select } from '@/Components/ui/select';
import { formatRupiah, formatDate } from '@/lib/format';

interface Outlet {
    id: number;
    name: string;
}

interface Expense {
    id: number;
    category: string;
    description: string | null;
    amount: number;
    period_month: number;
    period_year: number;
    occurred_at: string | null;
    outlet_id: number | null;
    outlet_name: string | null;
}

interface Props {
    expenses: Expense[];
    total: number;
    categories: Record<string, string>;
    categoryDescriptions: Record<string, string>;
    outlets: Outlet[];
    period: { month: number; year: number };
    periodLabel: string;
}

const categoryColors: Record<string, string> = {
    bahan_baku: 'bg-blue-100 text-blue-800',
    operasional: 'bg-green-100 text-green-800',
    logistik: 'bg-purple-100 text-purple-800',
    overhead: 'bg-orange-100 text-orange-800',
    non_operasional: 'bg-slate-100 text-slate-800',
};

const months = [
    'Januari',
    'Februari',
    'Maret',
    'April',
    'Mei',
    'Juni',
    'Juli',
    'Agustus',
    'September',
    'Oktober',
    'November',
    'Desember',
];

function toDatetimeLocal(iso: string | null): string {
    if (!iso) return '';
    const d = new Date(iso);
    const pad = (n: number) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

function getPeriodFromDate(dateStr: string): { month: number; year: number } {
    const d = new Date(dateStr);
    return { month: d.getMonth() + 1, year: d.getFullYear() };
}

export default function ExpensesIndex({
    expenses,
    total,
    categories,
    categoryDescriptions,
    outlets,
    period,
    periodLabel,
}: Props) {
    const { props } = usePage<PageProps>();
    const now = new Date();
    const pad = (n: number) => String(n).padStart(2, '0');
    const defaultDate = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}T${pad(now.getHours())}:${pad(now.getMinutes())}`;

    const form = useForm({
        category: 'operasional',
        description: '',
        amount: '',
        period_month: period.month,
        period_year: period.year,
        occurred_at: defaultDate,
    });
    const editForm = useForm({
        category: 'operasional',
        description: '',
        amount: '',
        period_month: period.month,
        period_year: period.year,
        occurred_at: '',
    });
    const [editing, setEditing] = useState<Expense | null>(null);
    const years = Array.from({ length: 5 }, (_, i) => new Date().getFullYear() - i);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        // Sync period from occurred_at
        if (form.data.occurred_at) {
            const p = getPeriodFromDate(form.data.occurred_at);
            form.setData('period_month', p.month);
            form.setData('period_year', p.year);
        }
        form.post('/expenses', {
            preserveScroll: true,
            onSuccess: () => form.setData({ amount: '' }),
        });
    };

    const openEdit = (expense: Expense) => {
        setEditing(expense);
        editForm.setData({
            category: expense.category,
            description: expense.description ?? '',
            amount: String(expense.amount),
            period_month: expense.period_month,
            period_year: expense.period_year,
            occurred_at: toDatetimeLocal(expense.occurred_at),
        });
        editForm.clearErrors();
    };

    const submitEdit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!editing) return;
        // Sync period from occurred_at
        if (editForm.data.occurred_at) {
            const p = getPeriodFromDate(editForm.data.occurred_at);
            editForm.setData('period_month', p.month);
            editForm.setData('period_year', p.year);
        }
        editForm.put(`/expenses/${editing.id}`, {
            preserveScroll: true,
            onSuccess: () => setEditing(null),
        });
    };

    const remove = (id: number) => {
        if (confirm('Hapus biaya ini?')) router.delete(`/expenses/${id}`, { preserveScroll: true });
    };

    const changePeriod = (month: number, year: number) => {
        router.get('/expenses', { month, year }, { preserveState: true });
    };

    return (
        <AppLayout title="Biaya Operasional">
            <Head title="Biaya" />

            <div className="mb-4 flex gap-2">
                <Select
                    className="w-40"
                    value={period.month}
                    onChange={(e) => changePeriod(Number(e.target.value), period.year)}
                >
                    {months.map((m, i) => (
                        <option key={i} value={i + 1}>
                            {m}
                        </option>
                    ))}
                </Select>
                <Select
                    className="w-28"
                    value={period.year}
                    onChange={(e) => changePeriod(period.month, Number(e.target.value))}
                >
                    {years.map((y) => (
                        <option key={y} value={y}>
                            {y}
                        </option>
                    ))}
                </Select>
            </div>

            <div className="grid gap-6 lg:grid-cols-3">
                <Card className="lg:col-span-1">
                    <CardHeader>
                        <CardTitle>Tambah Biaya</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="space-y-4">
                            <div>
                                <Label htmlFor="category">Kategori</Label>
                                <Select
                                    id="category"
                                    value={form.data.category}
                                    onChange={(e) => form.setData('category', e.target.value)}
                                >
                                    {Object.entries(categories).map(([key, label]) => (
                                        <option key={key} value={key}>
                                            {label}
                                        </option>
                                    ))}
                                </Select>
                                {form.data.category && categoryDescriptions[form.data.category] && (
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        {categoryDescriptions[form.data.category]}
                                    </p>
                                )}
                            </div>
                            <div>
                                <Label htmlFor="description">Deskripsi (opsional)</Label>
                                <Input
                                    id="description"
                                    value={form.data.description}
                                    onChange={(e) => form.setData('description', e.target.value)}
                                />
                            </div>
                            <div>
                                <Label htmlFor="amount">Jumlah (Rp)</Label>
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
                            <div>
                                <Label htmlFor="occurred_at">Tanggal</Label>
                                <Input
                                    id="occurred_at"
                                    type="datetime-local"
                                    value={form.data.occurred_at}
                                    onChange={(e) => form.setData('occurred_at', e.target.value)}
                                />
                            </div>
                            <Button type="submit" className="w-full" disabled={form.processing}>
                                <Plus className="h-4 w-4" /> Tambah
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <Card className="lg:col-span-2">
                    <CardHeader className="flex-row items-center justify-between space-y-0">
                        <CardTitle>Biaya {periodLabel}</CardTitle>
                        <span className="text-sm font-semibold">Total: {formatRupiah(total)}</span>
                    </CardHeader>
                    <CardContent className="p-0">
                        <MobileCardTable
                            data={expenses}
                            keyFn={(e) => e.id}
                            emptyMessage="Belum ada biaya."
                            columns={[
                                {
                                    header: 'Kategori',
                                    render: (e) => (
                                        <span
                                            className={`inline-block rounded-full px-2 py-0.5 text-xs font-medium ${categoryColors[e.category] ?? 'bg-gray-100 text-gray-800'}`}
                                        >
                                            {categories[e.category] ?? e.category}
                                        </span>
                                    ),
                                    badge: true,
                                },
                                {
                                    header: 'Tanggal',
                                    render: (e) => formatDate(e.occurred_at),
                                    secondary: true,
                                },
                                {
                                    header: 'Deskripsi',
                                    render: (e) => e.description ?? '-',
                                    primary: true,
                                },
                                {
                                    header: 'Outlet',
                                    render: (e) => e.outlet_name ?? '—',
                                    hideOnMobile: true,
                                },
                                {
                                    header: 'Jumlah',
                                    render: (e) => formatRupiah(e.amount),
                                    amount: true,
                                },
                            ]}
                            actions={(e) => (
                                <div className="flex justify-end gap-1">
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        onClick={() => openEdit(e)}
                                    >
                                        <Pencil className="h-4 w-4" />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        onClick={() => remove(e.id)}
                                    >
                                        <Trash2 className="h-4 w-4 text-destructive" />
                                    </Button>
                                </div>
                            )}
                        />
                    </CardContent>
                </Card>
            </div>

            <Modal open={editing !== null} onClose={() => setEditing(null)} title="Edit Biaya">
                <form onSubmit={submitEdit} className="space-y-4">
                    <div>
                        <Label>Kategori</Label>
                        <Select
                            value={editForm.data.category}
                            onChange={(e) => editForm.setData('category', e.target.value)}
                        >
                            {Object.entries(categories).map(([key, label]) => (
                                <option key={key} value={key}>
                                    {label}
                                </option>
                            ))}
                        </Select>
                        {editForm.data.category && categoryDescriptions[editForm.data.category] && (
                            <p className="mt-1 text-xs text-muted-foreground">
                                {categoryDescriptions[editForm.data.category]}
                            </p>
                        )}
                    </div>
                    <div>
                        <Label>Deskripsi</Label>
                        <Input
                            value={editForm.data.description}
                            onChange={(e) => editForm.setData('description', e.target.value)}
                        />
                    </div>
                    <div>
                        <Label>Jumlah (Rp)</Label>
                        <CurrencyInput
                            value={editForm.data.amount}
                            onChange={(v) => editForm.setData('amount', v)}
                        />
                        {editForm.errors.amount && (
                            <p className="mt-1 text-xs text-destructive">
                                {editForm.errors.amount}
                            </p>
                        )}
                    </div>
                    <div>
                        <Label>Tanggal</Label>
                        <Input
                            type="datetime-local"
                            value={editForm.data.occurred_at}
                            onChange={(e) => editForm.setData('occurred_at', e.target.value)}
                        />
                    </div>
                    <div className="flex justify-end gap-2">
                        <Button type="button" variant="outline" onClick={() => setEditing(null)}>
                            Batal
                        </Button>
                        <Button type="submit" disabled={editForm.processing}>
                            Simpan
                        </Button>
                    </div>
                </form>
            </Modal>
        </AppLayout>
    );
}
