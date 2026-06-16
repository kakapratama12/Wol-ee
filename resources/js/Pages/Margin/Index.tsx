import { Head, useForm } from '@inertiajs/react';
import { AlertTriangle, TrendingDown } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Select } from '@/Components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';
import { formatRupiah, formatPercent } from '@/lib/format';

interface ProductMargin {
    product_id: number;
    product: string;
    selling_price: number;
    cogs: number;
    margin: number;
}

interface Alert {
    product: string;
    previous_margin: number;
    current_margin: number;
    margin_drop: number;
    current_cogs: number;
}

interface WhatIf {
    product: string;
    product_id: number;
    increase_percent: number;
    current_cogs: number;
    new_cogs: number;
    current_margin: number;
    new_margin: number;
    recommended_price: number;
    price_increase_percent: number;
}

interface Props {
    products: ProductMargin[];
    alerts: Alert[];
    whatIf: WhatIf | null;
}

export default function MarginIndex({ products, alerts, whatIf }: Props) {
    const form = useForm({ product_id: whatIf ? String(whatIf.product_id) : '', increase_percent: whatIf ? String(whatIf.increase_percent) : '10' });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post('/margin/what-if', { preserveScroll: true });
    };

    return (
        <AppLayout title="Margin Protection">
            <Head title="Margin Protection" />

            {alerts.length > 0 && (
                <div className="mb-6 space-y-2">
                    {alerts.map((a) => (
                        <div key={a.product} className="flex items-start gap-3 rounded-lg border border-warning/40 bg-warning/10 p-4">
                            <AlertTriangle className="mt-0.5 h-5 w-5 shrink-0 text-warning" />
                            <div>
                                <p className="font-medium">
                                    {a.product}: margin turun {formatPercent(a.margin_drop)}
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    Dari {formatPercent(a.previous_margin)} ke {formatPercent(a.current_margin)} - COGS sekarang {formatRupiah(a.current_cogs)}
                                </p>
                            </div>
                        </div>
                    ))}
                </div>
            )}

            <div className="grid gap-6 lg:grid-cols-3">
                <Card className="lg:col-span-2">
                    <CardHeader>
                        <CardTitle>Margin per Produk</CardTitle>
                    </CardHeader>
                    <CardContent className="p-0">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Produk</TableHead>
                                    <TableHead>Harga Jual</TableHead>
                                    <TableHead>COGS</TableHead>
                                    <TableHead>Margin</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {products.length === 0 && (
                                    <TableRow>
                                        <TableCell colSpan={4} className="py-8 text-center text-muted-foreground">
                                            Belum ada produk dengan resep.
                                        </TableCell>
                                    </TableRow>
                                )}
                                {products.map((p) => (
                                    <TableRow key={p.product_id}>
                                        <TableCell className="font-medium">{p.product}</TableCell>
                                        <TableCell>{formatRupiah(p.selling_price)}</TableCell>
                                        <TableCell className="text-warning">{formatRupiah(p.cogs)}</TableCell>
                                        <TableCell className="font-semibold text-success">{formatPercent(p.margin)}</TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>

                <Card className="lg:col-span-1">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <TrendingDown className="h-4 w-4" /> What-If Simulator
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="space-y-4">
                            <div>
                                <Label htmlFor="product_id">Produk</Label>
                                <Select id="product_id" value={form.data.product_id} onChange={(e) => form.setData('product_id', e.target.value)}>
                                    <option value="">- pilih -</option>
                                    {products.map((p) => (
                                        <option key={p.product_id} value={p.product_id}>
                                            {p.product}
                                        </option>
                                    ))}
                                </Select>
                                {form.errors.product_id && <p className="mt-1 text-xs text-destructive">{form.errors.product_id}</p>}
                            </div>
                            <div>
                                <Label htmlFor="increase_percent">Kenaikan harga bahan (%)</Label>
                                <Input id="increase_percent" type="number" step="0.1" value={form.data.increase_percent} onChange={(e) => form.setData('increase_percent', e.target.value)} />
                            </div>
                            <Button type="submit" className="w-full" disabled={form.processing}>
                                Simulasikan
                            </Button>
                        </form>

                        {whatIf && (
                            <div className="mt-5 space-y-2 rounded-lg bg-muted/50 p-4 text-sm">
                                <p className="font-medium">{whatIf.product}</p>
                                <Row label="COGS sekarang" value={formatRupiah(whatIf.current_cogs)} />
                                <Row label={`COGS jika +${whatIf.increase_percent}%`} value={formatRupiah(whatIf.new_cogs)} />
                                <Row label="Margin sekarang" value={formatPercent(whatIf.current_margin)} />
                                <Row label="Margin baru" value={formatPercent(whatIf.new_margin)} />
                                <div className="my-2 border-t border-border" />
                                <p className="text-muted-foreground">Agar margin tetap, harga jual jadi:</p>
                                <p className="text-lg font-bold text-primary">
                                    {formatRupiah(whatIf.recommended_price)}{' '}
                                    <span className="text-sm font-normal text-muted-foreground">(+{formatPercent(whatIf.price_increase_percent)})</span>
                                </p>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}

function Row({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex items-center justify-between">
            <span className="text-muted-foreground">{label}</span>
            <span className="font-medium">{value}</span>
        </div>
    );
}
