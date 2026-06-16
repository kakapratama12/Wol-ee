import { Head, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
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
    product: string | null;
    quantity: number;
    revenue: number;
    cogs: number;
    profit: number;
    margin: number;
    source: string;
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

export default function SalesIndex({ sales, products }: Props) {
    const form = useForm({ product_id: '', quantity: '', unit_price: '', note: '' });
    const selected = products.find((p) => String(p.id) === form.data.product_id);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post('/sales', { onSuccess: () => form.reset() });
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
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {sales.data.length === 0 && (
                                    <TableRow>
                                        <TableCell colSpan={7} className="py-8 text-center text-muted-foreground">
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
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                        <Pagination links={sales.links} />
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
