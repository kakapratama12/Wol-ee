import { useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import Modal from '@/Components/ui/modal';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Select } from '@/Components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';
import { formatRupiah } from '@/lib/format';

interface Expense {
    id: number;
    category: string;
    description: string | null;
    amount: number;
    period_month: number;
    period_year: number;
}

interface Props {
    expenses: Expense[];
    total: number;
    categories: Record<string, string>;
    period: { month: number; year: number };
    periodLabel: string;
}

const categoryColors: Record<string, string> = {
    bahan_baku: 'bg-blue-100 text-blue-800',
    operasional: 'bg-green-100 text-green-800',
    overhead: 'bg-orange-100 text-orange-800',
};

const months = [
    'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
];

export default function ExpensesIndex({ expenses, total, categories, period, periodLabel }: Props) {
    const form = useForm({
        category: 'operasional',
        description: '',
        amount: '',
        period_month: period.month,
        period_year: period.year,
    });
    const editForm = useForm({
        category: 'operasional',
        description: '',
        amount: '',
        period_month: period.month,
        period_year: period.year,
    });
    const [editing, setEditing] = useState<Expense | null>(null);
    const years = Array.from({ length: 5 }, (_, i) => new Date().getFullYear() - i);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post('/expenses', { preserveScroll: true, onSuccess: () => form.setData('amount', '') });
    };

    const openEdit = (expense: Expense) => {
        setEditing(expense);
        editForm.setData({
            category: expense.category,
            description: expense.description ?? '',
            amount: String(expense.amount),
            period_month: expense.period_month,
            period_year: expense.period_year,
        });
        editForm.clearErrors();
    };

    const submitEdit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!editing) return;
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
                <Select className="w-40" value={period.month} onChange={(e) => changePeriod(Number(e.target.value), period.year)}>
                    {months.map((m, i) => (
                        <option key={i} value={i + 1}>
                            {m}
                        </option>
                    ))}
                </Select>
                <Select className="w-28" value={period.year} onChange={(e) => changePeriod(period.month, Number(e.target.value))}>
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
                                <Select id="category" value={form.data.category} onChange={(e) => form.setData('category', e.target.value)}>
                                    {Object.entries(categories).map(([key, label]) => (
                                        <option key={key} value={key}>
                                            {label}
                                        </option>
                                    ))}
                                </Select>
                            </div>
                            <div>
                                <Label htmlFor="description">Deskripsi (opsional)</Label>
                                <Input id="description" value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} />
                            </div>
                            <div>
                                <Label htmlFor="amount">Jumlah (Rp)</Label>
                                <Input id="amount" type="number" step="1" value={form.data.amount} onChange={(e) => form.setData('amount', e.target.value)} />
                                {form.errors.amount && <p className="mt-1 text-xs text-destructive">{form.errors.amount}</p>}
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
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="w-32">Kategori</TableHead>
                                    <TableHead>Deskripsi</TableHead>
                                    <TableHead>Jumlah</TableHead>
                                    <TableHead></TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {expenses.length === 0 && (
                                    <TableRow>
                                        <TableCell colSpan={4} className="py-8 text-center text-muted-foreground">
                                            Belum ada biaya.
                                        </TableCell>
                                    </TableRow>
                                )}
                                {expenses.map((e) => (
                                    <TableRow key={e.id}>
                                        <TableCell className="font-medium">
                                            <span className={`inline-block rounded-full px-2 py-0.5 text-xs font-medium ${categoryColors[e.category] ?? 'bg-gray-100 text-gray-800'}`}>
                                                {categories[e.category] ?? e.category}
                                            </span>
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">{e.description ?? '-'}</TableCell>
                                        <TableCell>{formatRupiah(e.amount)}</TableCell>
                                        <TableCell className="text-right">
                                            <div className="flex justify-end gap-1">
                                                <Button variant="ghost" size="icon" onClick={() => openEdit(e)}>
                                                    <Pencil className="h-4 w-4" />
                                                </Button>
                                                <Button variant="ghost" size="icon" onClick={() => remove(e.id)}>
                                                    <Trash2 className="h-4 w-4 text-destructive" />
                                                </Button>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>

            <Modal open={editing !== null} onClose={() => setEditing(null)} title="Edit Biaya">
                <form onSubmit={submitEdit} className="space-y-4">
                    <div>
                        <Label>Kategori</Label>
                        <Select value={editForm.data.category} onChange={(e) => editForm.setData('category', e.target.value)}>
                            {Object.entries(categories).map(([key, label]) => (
                                <option key={key} value={key}>
                                    {label}
                                </option>
                            ))}
                        </Select>
                    </div>
                    <div>
                        <Label>Deskripsi</Label>
                        <Input value={editForm.data.description} onChange={(e) => editForm.setData('description', e.target.value)} />
                    </div>
                    <div>
                        <Label>Jumlah (Rp)</Label>
                        <Input type="number" step="1" value={editForm.data.amount} onChange={(e) => editForm.setData('amount', e.target.value)} />
                        {editForm.errors.amount && <p className="mt-1 text-xs text-destructive">{editForm.errors.amount}</p>}
                    </div>
                    <div className="grid grid-cols-2 gap-2">
                        <div>
                            <Label>Bulan</Label>
                            <Select value={editForm.data.period_month} onChange={(e) => editForm.setData('period_month', Number(e.target.value))}>
                                {months.map((m, i) => (
                                    <option key={i} value={i + 1}>
                                        {m}
                                    </option>
                                ))}
                            </Select>
                        </div>
                        <div>
                            <Label>Tahun</Label>
                            <Select value={editForm.data.period_year} onChange={(e) => editForm.setData('period_year', Number(e.target.value))}>
                                {years.map((y) => (
                                    <option key={y} value={y}>
                                        {y}
                                    </option>
                                ))}
                            </Select>
                        </div>
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
