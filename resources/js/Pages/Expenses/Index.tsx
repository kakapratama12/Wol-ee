import { Head, useForm, router } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
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
}

interface Props {
    expenses: Expense[];
    total: number;
    period: { month: number; year: number };
    periodLabel: string;
}

const categories = ['Listrik', 'Sewa', 'Internet', 'Gaji', 'Marketing', 'Lainnya'];
const months = [
    'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
];

export default function ExpensesIndex({ expenses, total, period, periodLabel }: Props) {
    const form = useForm({
        category: 'Listrik',
        description: '',
        amount: '',
        period_month: period.month,
        period_year: period.year,
    });
    const years = Array.from({ length: 5 }, (_, i) => new Date().getFullYear() - i);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post('/expenses', { preserveScroll: true, onSuccess: () => form.setData('amount', '') });
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
                                    {categories.map((c) => (
                                        <option key={c} value={c}>
                                            {c}
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
                                    <TableHead>Kategori</TableHead>
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
                                        <TableCell className="font-medium">{e.category}</TableCell>
                                        <TableCell className="text-muted-foreground">{e.description ?? '-'}</TableCell>
                                        <TableCell>{formatRupiah(e.amount)}</TableCell>
                                        <TableCell className="text-right">
                                            <Button variant="ghost" size="icon" onClick={() => remove(e.id)}>
                                                <Trash2 className="h-4 w-4 text-destructive" />
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
