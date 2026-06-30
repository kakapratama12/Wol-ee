import { useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import { Pencil, Trash2 } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import Modal from '@/Components/ui/modal';
import CreateProductModal from '@/Components/CreateProductModal';
import Pagination from '@/Components/Pagination';
import { Button } from '@/Components/ui/button';
import { CurrencyInput } from '@/Components/ui/currency-input';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import CreatableCombobox from '@/Components/ui/creatable-combobox';
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

export default function SalesIndex({ sales, products: initialProducts }: Props) {
    const [products, setProducts] = useState(initialProducts);
    const [showCreateModal, setShowCreateModal] = useState(false);

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

    const handleCreateSuccess = (data: { id: number; name: string; selling_price: number }) => {
        const updated = [...products, data].sort((a, b) => a.name.localeCompare(b.name));
        setProducts(updated);
        form.setData('product_id', String(data.id));
        setShowCreateModal(false);
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
                                <Label>Produk</Label>
                                <CreatableCombobox
                                    options={products.map((p) => ({ id: p.id, label: p.name, sublabel: formatRupiah(p.selling_price) }))}
                                    value={form.data.product_id}
                                    onChange={(v) => form.setData('product_id', v)}
                                    onCreateNew={() => setShowCreateModal(true)}
                                    placeholder="- Pilih produk -"
                                    createLabel="Tambah Produk Baru"
                                    error={form.errors.product_id}
                                />
                            </div>
                            <div>
                                <Label htmlFor="quantity">Jumlah</Label>
                                <Input id="quantity" type="number" step="1" value={form.data.quantity} onChange={(e) => form.setData('quantity', e.target.value)} />
                                {form.errors.quantity && <p className="mt-1 text-xs text-destructive">{form.errors.quantity}</p>}
                            </div>
                            <div>
                                <Label htmlFor="unit_price">Harga jual / unit (opsional)</Label>
                                <CurrencyInput
                                    id="unit_price"
                                    placeholder={selected ? String(selected.selling_price) : 'pakai harga produk'}
                                    value={form.data.unit_price}
                                    onChange={(v) => form.setData('unit_price', v)}
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
                                {sales.data.map((s) => (
                                    <TableRow key={s.id}>
                                        <TableCell className="text-sm">{formatDate(s.occurred_at)}</TableCell>
                                        <TableCell className="font-medium">{s.product}</TableCell>
                                        <TableCell>{s.quantity}</TableCell>
                                        <TableCell>{formatRupiah(s.revenue)}</TableCell>
                                        <TableCell>{formatRupiah(s.cogs)}</TableCell>
                                        <TableCell>{formatRupiah(s.profit)}</TableCell>
                                        <TableCell>{formatPercent(s.margin)}</TableCell>
                                        <TableCell className="text-right">
                                            <div className="flex justify-end gap-1">
                                                <Button variant="ghost" size="sm" onClick={() => openEdit(s)}>
                                                    <Pencil className="h-4 w-4" />
                                                </Button>
                                                <Button variant="ghost" size="sm" onClick={() => remove(s)}>
                                                    <Trash2 className="h-4 w-4 text-destructive" />
                                                </Button>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                                {sales.data.length === 0 && (
                                    <TableRow>
                                        <TableCell colSpan={8} className="h-24 text-center text-muted-foreground">
                                            Belum ada penjualan.
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>
                        <div className="border-t px-4 py-3">
                            <Pagination links={sales.links} />
                        </div>
                    </CardContent>
                </Card>
            </div>

            {/* Edit Modal */}
            {editing && (
                <Modal open={!!editing} onClose={() => setEditing(null)} title="Edit Penjualan">
                    <form onSubmit={submitEdit} className="space-y-4">
                        <div>
                            <Label>Produk</Label>
                            <CreatableCombobox
                                options={products.map((p) => ({ id: p.id, label: p.name, sublabel: formatRupiah(p.selling_price) }))}
                                value={editForm.data.product_id}
                                onChange={(v) => editForm.setData('product_id', v)}
                                onCreateNew={() => { setEditing(null); setShowCreateModal(true); }}
                                placeholder="- Pilih produk -"
                                createLabel="Tambah Produk Baru"
                            />
                        </div>
                        <div>
                            <Label>Jumlah</Label>
                            <Input type="number" step="1" value={editForm.data.quantity} onChange={(e) => editForm.setData('quantity', e.target.value)} />
                        </div>
                        <div>
                            <Label>Harga jual / unit</Label>
                            <CurrencyInput placeholder={editSelected ? String(editSelected.selling_price) : ''} value={editForm.data.unit_price} onChange={(v) => editForm.setData('unit_price', v)} />
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

            {/* Shared Create Product Modal */}
            <CreateProductModal
                open={showCreateModal}
                onClose={() => setShowCreateModal(false)}
                onSuccess={handleCreateSuccess}
            />
        </AppLayout>
    );
}
