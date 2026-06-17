import { useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import { Pencil, Trash2 } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import Modal from '@/Components/ui/modal';
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
    ingredient_id: number;
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

function toDatetimeLocal(iso: string | null): string {
    if (!iso) return '';
    const d = new Date(iso);
    const pad = (n: number) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

export default function TransactionsIndex({ transactions, ingredients }: Props) {
    const form = useForm({ ingredient_id: '', quantity: '', total: '', note: '' });
    const editForm = useForm({
        ingredient_id: '',
        quantity: '',
        total: '',
        note: '',
        occurred_at: '',
    });
    const [editing, setEditing] = useState<Transaction | null>(null);

    const selected = ingredients.find((i) => String(i.id) === form.data.ingredient_id);
    const editSelected = ingredients.find((i) => String(i.id) === editForm.data.ingredient_id);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post('/transactions', { onSuccess: () => form.reset() });
    };

    const openEdit = (transaction: Transaction) => {
        setEditing(transaction);
        editForm.setData({
            ingredient_id: String(transaction.ingredient_id),
            quantity: String(transaction.quantity),
            total: String(transaction.total),
            note: transaction.note ?? '',
            occurred_at: toDatetimeLocal(transaction.occurred_at),
        });
        editForm.clearErrors();
    };

    const submitEdit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!editing) return;
        editForm.put(`/transactions/${editing.id}`, {
            preserveScroll: true,
            onSuccess: () => setEditing(null),
        });
    };

    const remove = (transaction: Transaction) => {
        if (confirm(`Hapus pembelian ${transaction.ingredient}? Stok akan dikurangi.`)) {
            router.delete(`/transactions/${transaction.id}`, { preserveScroll: true });
        }
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
                                    <TableHead></TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {transactions.data.length === 0 && (
                                    <TableRow>
                                        <TableCell colSpan={6} className="py-8 text-center text-muted-foreground">
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
                                        <TableCell className="text-right">
                                            <div className="flex justify-end gap-1">
                                                <Button variant="ghost" size="icon" onClick={() => openEdit(t)}>
                                                    <Pencil className="h-4 w-4" />
                                                </Button>
                                                <Button variant="ghost" size="icon" onClick={() => remove(t)}>
                                                    <Trash2 className="h-4 w-4 text-destructive" />
                                                </Button>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                        <Pagination links={transactions.links} />
                    </CardContent>
                </Card>
            </div>

            <Modal open={editing !== null} onClose={() => setEditing(null)} title="Edit Pembelian">
                <form onSubmit={submitEdit} className="space-y-4">
                    <div>
                        <Label>Bahan</Label>
                        <Select value={editForm.data.ingredient_id} onChange={(e) => editForm.setData('ingredient_id', e.target.value)}>
                            <option value="">- Pilih bahan -</option>
                            {ingredients.map((i) => (
                                <option key={i.id} value={i.id}>
                                    {i.name}
                                </option>
                            ))}
                        </Select>
                        {editForm.errors.ingredient_id && <p className="mt-1 text-xs text-destructive">{editForm.errors.ingredient_id}</p>}
                    </div>
                    <div>
                        <Label>Jumlah {editSelected ? `(${editSelected.base_unit})` : ''}</Label>
                        <Input type="number" step="0.0001" value={editForm.data.quantity} onChange={(e) => editForm.setData('quantity', e.target.value)} />
                        {editForm.errors.quantity && <p className="mt-1 text-xs text-destructive">{editForm.errors.quantity}</p>}
                    </div>
                    <div>
                        <Label>Total harga (Rp)</Label>
                        <Input type="number" step="1" value={editForm.data.total} onChange={(e) => editForm.setData('total', e.target.value)} />
                        {editForm.errors.total && <p className="mt-1 text-xs text-destructive">{editForm.errors.total}</p>}
                    </div>
                    <div>
                        <Label>Tanggal</Label>
                        <Input type="datetime-local" value={editForm.data.occurred_at} onChange={(e) => editForm.setData('occurred_at', e.target.value)} />
                    </div>
                    <div>
                        <Label>Catatan</Label>
                        <Input value={editForm.data.note} onChange={(e) => editForm.setData('note', e.target.value)} />
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
