import { useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import { Pencil, Trash2 } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import Modal from '@/Components/ui/modal';
import CreateIngredientModal from '@/Components/CreateIngredientModal';
import Pagination from '@/Components/Pagination';
import { Button } from '@/Components/ui/button';
import { CurrencyInput } from '@/Components/ui/currency-input';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import CreatableCombobox from '@/Components/ui/creatable-combobox';
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

export default function TransactionsIndex({ transactions, ingredients: initialIngredients }: Props) {
    const [ingredients, setIngredients] = useState(initialIngredients);
    const [showCreateModal, setShowCreateModal] = useState(false);

    const now = new Date();
    const pad = (n: number) => String(n).padStart(2, '0');
    const defaultDate = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}T${pad(now.getHours())}:${pad(now.getMinutes())}`;
    const form = useForm({ ingredient_id: '', quantity: '', total: '', note: '', occurred_at: defaultDate });
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

    const handleCreateSuccess = (data: { id: number; name: string; base_unit: string }) => {
        const updated = [...ingredients, data].sort((a, b) => a.name.localeCompare(b.name));
        setIngredients(updated);
        form.setData('ingredient_id', String(data.id));
        setShowCreateModal(false);
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
                                <Label>Bahan</Label>
                                <CreatableCombobox
                                    options={ingredients.map((i) => ({ id: i.id, label: i.name, sublabel: i.base_unit }))}
                                    value={form.data.ingredient_id}
                                    onChange={(v) => form.setData('ingredient_id', v)}
                                    onCreateNew={() => setShowCreateModal(true)}
                                    placeholder="- Pilih bahan -"
                                    createLabel="Tambah Bahan Baru"
                                    error={form.errors.ingredient_id}
                                />
                            </div>
                            <div>
                                <Label htmlFor="quantity">Jumlah {selected ? `(${selected.base_unit})` : ''}</Label>
                                <Input id="quantity" type="number" step="0.0001" value={form.data.quantity} onChange={(e) => form.setData('quantity', e.target.value)} />
                                {form.errors.quantity && <p className="mt-1 text-xs text-destructive">{form.errors.quantity}</p>}
                            </div>
                            <div>
                                <Label htmlFor="total">Total harga (Rp)</Label>
                                <CurrencyInput id="total" value={form.data.total} onChange={(v) => form.setData('total', v)} />
                                {form.errors.total && <p className="mt-1 text-xs text-destructive">{form.errors.total}</p>}
                            </div>
                            <div>
                                <Label htmlFor="note">Catatan (opsional)</Label>
                                <Input id="note" value={form.data.note} onChange={(e) => form.setData('note', e.target.value)} />
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
                                {transactions.data.map((t) => (
                                    <TableRow key={t.id}>
                                        <TableCell className="text-sm">{formatDate(t.occurred_at)}</TableCell>
                                        <TableCell className="font-medium">{t.ingredient}</TableCell>
                                        <TableCell className="text-number">{formatNumber(t.quantity)} {t.base_unit}</TableCell>
                                        <TableCell className="text-number">{formatRupiah(t.total)}</TableCell>
                                        <TableCell>
                                            <span className="inline-flex items-center rounded-md bg-blue-50 px-2 py-1 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-700/10">
                                                {t.source}
                                            </span>
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <div className="flex justify-end gap-1">
                                                <Button variant="ghost" size="sm" onClick={() => openEdit(t)}>
                                                    <Pencil className="h-4 w-4" />
                                                </Button>
                                                <Button variant="ghost" size="sm" onClick={() => remove(t)}>
                                                    <Trash2 className="h-4 w-4 text-destructive" />
                                                </Button>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                                {transactions.data.length === 0 && (
                                    <TableRow>
                                        <TableCell colSpan={6} className="h-24 text-center text-muted-foreground">
                                            Belum ada pembelian.
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>
                        <div className="border-t px-4 py-3">
                            <Pagination links={transactions.links} />
                        </div>
                    </CardContent>
                </Card>
            </div>

            {/* Edit Modal */}
            {editing && (
                <Modal open={!!editing} onClose={() => setEditing(null)} title="Edit Pembelian">
                    <form onSubmit={submitEdit} className="space-y-4">
                        <div>
                            <Label>Bahan</Label>
                            <CreatableCombobox
                                options={ingredients.map((i) => ({ id: i.id, label: i.name, sublabel: i.base_unit }))}
                                value={editForm.data.ingredient_id}
                                onChange={(v) => editForm.setData('ingredient_id', v)}
                                onCreateNew={() => { setEditing(null); setShowCreateModal(true); }}
                                placeholder="- Pilih bahan -"
                                createLabel="Tambah Bahan Baru"
                            />
                        </div>
                        <div>
                            <Label>Jumlah {editSelected ? `(${editSelected.base_unit})` : ''}</Label>
                            <Input type="number" step="0.0001" value={editForm.data.quantity} onChange={(e) => editForm.setData('quantity', e.target.value)} />
                        </div>
                        <div>
                            <Label>Total harga (Rp)</Label>
                            <CurrencyInput value={editForm.data.total} onChange={(v) => editForm.setData('total', v)} />
                        </div>
                        <div>
                            <Label>Catatan</Label>
                            <Input value={editForm.data.note} onChange={(e) => editForm.setData('note', e.target.value)} />
                        </div>
                        <div>
                            <Label>Tanggal</Label>
                            <Input type="datetime-local" value={editForm.data.occurred_at} onChange={(e) => editForm.setData('occurred_at', e.target.value)} />
                        </div>
                        <div className="flex justify-end gap-2">
                            <Button type="button" variant="outline" onClick={() => setEditing(null)}>Batal</Button>
                            <Button type="submit" disabled={editForm.processing}>Simpan</Button>
                        </div>
                    </form>
                </Modal>
            )}

            {/* Shared Create Ingredient Modal */}
            <CreateIngredientModal
                open={showCreateModal}
                onClose={() => setShowCreateModal(false)}
                onSuccess={handleCreateSuccess}
            />
        </AppLayout>
    );
}
