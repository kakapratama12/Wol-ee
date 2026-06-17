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
import { formatRupiah, formatPercent, formatDate } from '@/lib/format';
import type { Paginated } from '@/types';

interface Sale {
    id: number;
    product_id: number;
    product: string | null;
    quantity: number;
    unit_price: number;
    revenue: number;
    cogs: number;
    profit: number;
    margin: number;
    source: string;
    note: string | null;
    occurred_at: string | null;
}

interface ProductOption {
    id: number;
    name: string;
    selling_price: number;
}

interface Props {
    sales: Paginated<Sale>;
    products: ProductOption[];
}

function toDatetimeLocal(iso: string | null): string {
    if (!iso) return '';
    const d = new Date(iso);
    const pad = (n: number) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

export default function SalesIndex({ sales, products }: Props) {
    const form = useForm({ product_id: '', quantity: '', unit_price: '', note: '' });
    const editForm = useForm({
        product_id: '',
        quantity: '',
        unit_price: '',
        note: '',
        occurred_at: '',
    });
    const [editing, setEditing] = useState<Sale | null>(null);

    const selected = products.find((p) => String(p.id) === form.data.product_id);
    const editSelected = products.find((p) => String(p.id) === editForm.data.product_id);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post('/sales', { onSuccess: () => form.reset() });
    };

    const openEdit = (sale: Sale) => {
        setEditing(sale);
        editForm.setData({
            product_id: String(sale.product_id),
            quantity: String(sale.quantity),
            unit_price: String(sale.unit_price),
            note: sale.note ?? '',
            occurred_at: toDatetimeLocal(sale.occurred_at),
        });
        editForm.clearErrors();
    };

    const submitEdit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!editing) return;
        editForm.put(`/sales/${editing.id}`, {
            preserveScroll: true,
            onSuccess: () => setEditing(null),
        });
    };

    const remove = (sale: Sale) => {
        if (confirm(`Hapus penjualan ${sale.product} x${sale.quantity}? Stok bahan akan dikembalikan.`)) {
            router.delete(`/sales/${sale.id}`, { preserveScroll: true });
        }
    };

    return (
        <AppLayout title="Penjualan">
            <Head title="Penjualan" />

            <div className="grid gap-6 lg:grid-cols-3">
                <Card className="lg:col-span-1">
                    <CardHeader>
                        <CardTitle>Catat Penjualan</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="space-y-4">
                            <div>
                                <Label htmlFor="product_id">Produk</Label>
                                <Select id="product_id" value={form.data.product_id} onChange={(e) => form.setData('product_id', e.target.value)}>
                                    <option value="">- Pilih produk -</option>
                                    {products.map((p) => (
                                        <option key={p.id} value={p.id}>
                                            {p.name} ({formatRupiah(p.selling_price)})
                                        </option>
                                    ))}
                                </Select>
                                {form.errors.product_id && <p className="mt-1 text-xs text-destructive">{form.errors.product_id}</p>}
                            </div>
                            <div>
                                <Label htmlFor="quantity">Jumlah</Label>
                                <Input id="quantity" type="number" step="1" value={form.data.quantity} onChange={(e) => form.setData('quantity', e.target.value)} />
                                {form.errors.quantity && <p className="mt-1 text-xs text-destructive">{form.errors.quantity}</p>}
                            </div>
                            <div>
                                <Label htmlFor="unit_price">Harga jual / unit (opsional)</Label>
                                <Input
                                    id="unit_price"
                                    type="number"
                                    step="1"
                                    placeholder={selected ? String(selected.selling_price) : 'pakai harga produk'}
                                    value={form.data.unit_price}
                                    onChange={(e) => form.setData('unit_price', e.target.value)}
                                />
                            </div>
                            <Button type="submit" className="w-full" disabled={form.processing}>
                                Simpan & Kurangi Stok
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
                                    <TableHead>Produk</TableHead>
                                    <TableHead>Qty</TableHead>
                                    <TableHead>Revenue</TableHead>
                                    <TableHead>COGS</TableHead>
                                    <TableHead>Profit</TableHead>
                                    <TableHead>Margin</TableHead>
                                    <TableHead></TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {sales.data.length === 0 && (
                                    <TableRow>
                                        <TableCell colSpan={8} className="py-8 text-center text-muted-foreground">
                                            Belum ada penjualan.
                                        </TableCell>
                                    </TableRow>
                                )}
                                {sales.data.map((s) => (
                                    <TableRow key={s.id}>
                                        <TableCell className="text-muted-foreground">{formatDate(s.occurred_at)}</TableCell>
                                        <TableCell className="font-medium">{s.product ?? '-'}</TableCell>
                                        <TableCell>{s.quantity}</TableCell>
                                        <TableCell>{formatRupiah(s.revenue)}</TableCell>
                                        <TableCell className="text-warning">{formatRupiah(s.cogs)}</TableCell>
                                        <TableCell className="text-success">{formatRupiah(s.profit)}</TableCell>
                                        <TableCell>{formatPercent(s.margin)}</TableCell>
                                        <TableCell className="text-right">
                                            <div className="flex justify-end gap-1">
                                                <Button variant="ghost" size="icon" onClick={() => openEdit(s)}>
                                                    <Pencil className="h-4 w-4" />
                                                </Button>
                                                <Button variant="ghost" size="icon" onClick={() => remove(s)}>
                                                    <Trash2 className="h-4 w-4 text-destructive" />
                                                </Button>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                        <Pagination links={sales.links} />
                    </CardContent>
                </Card>
            </div>

            <Modal open={editing !== null} onClose={() => setEditing(null)} title="Edit Penjualan">
                <form onSubmit={submitEdit} className="space-y-4">
                    <div>
                        <Label>Produk</Label>
                        <Select value={editForm.data.product_id} onChange={(e) => editForm.setData('product_id', e.target.value)}>
                            <option value="">- Pilih produk -</option>
                            {products.map((p) => (
                                <option key={p.id} value={p.id}>
                                    {p.name} ({formatRupiah(p.selling_price)})
                                </option>
                            ))}
                        </Select>
                        {editForm.errors.product_id && <p className="mt-1 text-xs text-destructive">{editForm.errors.product_id}</p>}
                    </div>
                    <div>
                        <Label>Jumlah</Label>
                        <Input type="number" step="1" value={editForm.data.quantity} onChange={(e) => editForm.setData('quantity', e.target.value)} />
                        {editForm.errors.quantity && <p className="mt-1 text-xs text-destructive">{editForm.errors.quantity}</p>}
                    </div>
                    <div>
                        <Label>Harga jual / unit (opsional)</Label>
                        <Input
                            type="number"
                            step="1"
                            placeholder={editSelected ? String(editSelected.selling_price) : ''}
                            value={editForm.data.unit_price}
                            onChange={(e) => editForm.setData('unit_price', e.target.value)}
                        />
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
