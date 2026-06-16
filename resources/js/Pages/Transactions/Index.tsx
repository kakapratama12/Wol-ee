import { Head, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import Pagination from '@/Components/Pagination';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Select } from '@/Components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';
import { formatRupiah, formatNumber, formatDate } from '@/lib/format';
import type { Paginated } from '@/types';

interface Transaction {
    id: number;
    ingredient: string | null;
    base_unit: string | null;
    quantity: number;
    unit_price: number;
    total: number;
    source: string;
    note: string | null;
    occurred_at: string | null;
}

interface IngredientOption {
    id: number;
    name: string;
    base_unit: string;
}

interface Props {
    transactions: Paginated<Transaction>;
    ingredients: IngredientOption[];
}

export default function TransactionsIndex({ transactions, ingredients }: Props) {
    const form = useForm({ ingredient_id: '', quantity: '', total: '', note: '' });

    const selected = ingredients.find((i) => String(i.id) === form.data.ingredient_id);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post('/transactions', { onSuccess: () => form.reset() });
    };

    return (
        <AppLayout title="Pembelian Bahan">
            <Head title="Pembelian" />

            <div className="grid gap-6 lg:grid-cols-3">
                <Card className="lg:col-span-1">
                    <CardHeader>
                        <CardTitle>Catat Pembelian</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="space-y-4">
                            <div>
                                <Label htmlFor="ingredient_id">Bahan</Label>
                                <Select id="ingredient_id" value={form.data.ingredient_id} onChange={(e) => form.setData('ingredient_id', e.target.value)}>
                                    <option value="">- Pilih bahan -</option>
                                    {ingredients.map((i) => (
                                        <option key={i.id} value={i.id}>
                                            {i.name}
                                        </option>
                                    ))}
                                </Select>
                                {form.errors.ingredient_id && <p className="mt-1 text-xs text-destructive">{form.errors.ingredient_id}</p>}
                            </div>
                            <div>
                                <Label htmlFor="quantity">Jumlah {selected ? `(${selected.base_unit})` : ''}</Label>
                                <Input id="quantity" type="number" step="0.0001" value={form.data.quantity} onChange={(e) => form.setData('quantity', e.target.value)} />
                                {form.errors.quantity && <p className="mt-1 text-xs text-destructive">{form.errors.quantity}</p>}
                            </div>
                            <div>
                                <Label htmlFor="total">Total harga (Rp)</Label>
                                <Input id="total" type="number" step="1" value={form.data.total} onChange={(e) => form.setData('total', e.target.value)} />
                                {form.errors.total && <p className="mt-1 text-xs text-destructive">{form.errors.total}</p>}
                            </div>
                            <div>
                                <Label htmlFor="note">Catatan (opsional)</Label>
                                <Input id="note" value={form.data.note} onChange={(e) => form.setData('note', e.target.value)} />
                            </div>
                            <Button type="submit" className="w-full" disabled={form.processing}>
                                Simpan & Tambah Stok
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <Card className="lg:col-span-2">
                    <CardContent className="p-0">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Tanggal</TableHead>
                                    <TableHead>Bahan</TableHead>
                                    <TableHead>Jumlah</TableHead>
                                    <TableHead>Total</TableHead>
                                    <TableHead>Sumber</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {transactions.data.length === 0 && (
                                    <TableRow>
                                        <TableCell colSpan={5} className="py-8 text-center text-muted-foreground">
                                            Belum ada pembelian.
                                        </TableCell>
                                    </TableRow>
                                )}
                                {transactions.data.map((t) => (
                                    <TableRow key={t.id}>
                                        <TableCell className="text-muted-foreground">{formatDate(t.occurred_at)}</TableCell>
                                        <TableCell className="font-medium">{t.ingredient ?? '-'}</TableCell>
                                        <TableCell>
                                            {formatNumber(t.quantity, 2)} {t.base_unit}
                                        </TableCell>
                                        <TableCell>{formatRupiah(t.total)}</TableCell>
                                        <TableCell className="uppercase text-xs text-muted-foreground">{t.source}</TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                        <Pagination links={transactions.links} />
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
