import { useState } from 'react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, Eye } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import InvoiceStatusBadge from '@/Components/InvoiceStatusBadge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';
import { formatDate, formatRupiah } from '@/lib/format';
import type { PageProps } from '@/types';

interface Invoice {
    id: number;
    invoice_number: string;
    partner: string | null;
    amount: number;
    paid_amount: number;
    remaining: number;
    due_date: string;
    status: string;
    note: string | null;
    paid_at: string | null;
}

interface Payment {
    paid_at: string;
    amount: number;
}

interface InvoiceItem {
    id: number;
    description: string;
    quantity: number;
    unit_price: number;
    total: number;
}

interface Props {
    invoice: Invoice & { items?: InvoiceItem[] };
    payments: Payment[];
}

export default function InvoicesShow({ invoice, payments }: Props) {
    const items = invoice.items ?? [];
    const [previewOpen, setPreviewOpen] = useState(false);
    const { props } = usePage<PageProps>();
    const isOwner = props.auth.user.role === 'owner';
    const canPay = isOwner && invoice.status !== 'paid';

    const form = useForm({
        amount: '',
        paid_at: new Date().toISOString().slice(0, 10),
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(`/invoices/${invoice.id}/pay`, { preserveScroll: true, onSuccess: () => form.reset('amount') });
    };

    return (
        <AppLayout title={invoice.invoice_number}>
            <Head title={invoice.invoice_number} />

            <div className="mb-4">
                <Link href="/invoices" className="inline-flex items-center text-sm text-muted-foreground hover:text-foreground">
                    <ArrowLeft className="mr-1 h-4 w-4" />
                    Kembali ke daftar
                </Link>
            </div>

            {items.length > 0 && (
                <Card className="mt-6">
                    <CardHeader>
                        <CardTitle>Rincian Item</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Deskripsi</TableHead>
                                    <TableHead className="text-right">Qty</TableHead>
                                    <TableHead className="text-right">Harga Satuan</TableHead>
                                    <TableHead className="text-right">Total</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {items.map((item) => (
                                    <TableRow key={item.id}>
                                        <TableCell>{item.description}</TableCell>
                                        <TableCell className="text-right">{item.quantity}</TableCell>
                                        <TableCell className="text-right">{formatRupiah(item.unit_price)}</TableCell>
                                        <TableCell className="text-right">{formatRupiah(item.total)}</TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            )}

            <div className="grid gap-6 lg:grid-cols-3">
                <Card className="lg:col-span-2">
                    <CardHeader className="flex flex-row items-center justify-between">
                        <div className="flex items-center gap-3">
                            <CardTitle>{invoice.invoice_number}</CardTitle>
                            <InvoiceStatusBadge status={invoice.status} />
                        </div>
                        <div className="flex gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => setPreviewOpen(true)}
                            >
                                <Eye className="mr-1 h-4 w-4" />
                                Preview
                            </Button>
                            <a
                                href={`/invoices/${invoice.id}/pdf`}
                                className="inline-flex items-center gap-2 rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90"
                            >
                                <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                                Download
                            </a>
                        </div>
                    </CardHeader>
                    <CardContent className="grid gap-3 text-sm sm:grid-cols-2">
                        <p><span className="text-muted-foreground">Partner:</span> {invoice.partner ?? '-'}</p>
                        <p><span className="text-muted-foreground">Jatuh tempo:</span> {formatDate(invoice.due_date)}</p>
                        <p><span className="text-muted-foreground">Nominal:</span> {formatRupiah(invoice.amount)}</p>
                        <p><span className="text-muted-foreground">Terbayar:</span> {formatRupiah(invoice.paid_amount)}</p>
                        <p><span className="text-muted-foreground">Sisa:</span> {formatRupiah(invoice.remaining)}</p>
                        {invoice.note && (
                            <p className="sm:col-span-2"><span className="text-muted-foreground">Catatan:</span> {invoice.note}</p>
                        )}
                    </CardContent>
                </Card>

                {canPay && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Catat Pembayaran</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={submit} className="space-y-4">
                                <div>
                                    <Label htmlFor="amount">Jumlah (Rp)</Label>
                                    <Input
                                        id="amount"
                                        type="number"
                                        max={invoice.remaining}
                                        value={form.data.amount}
                                        onChange={(e) => form.setData('amount', e.target.value)}
                                        placeholder={`Maks ${invoice.remaining}`}
                                    />
                                    {form.errors.amount && <p className="mt-1 text-xs text-destructive">{form.errors.amount}</p>}
                                </div>
                                <div>
                                    <Label htmlFor="paid_at">Tanggal</Label>
                                    <Input
                                        id="paid_at"
                                        type="date"
                                        value={form.data.paid_at}
                                        onChange={(e) => form.setData('paid_at', e.target.value)}
                                    />
                                    {form.errors.paid_at && <p className="mt-1 text-xs text-destructive">{form.errors.paid_at}</p>}
                                </div>
                                <Button type="submit" className="w-full" disabled={form.processing}>
                                    Submit Pembayaran
                                </Button>
                            </form>
                        </CardContent>
                    </Card>
                )}
            </div>

            <Card className="mt-6">
                <CardHeader>
                    <CardTitle>Riwayat Pembayaran</CardTitle>
                </CardHeader>
                <CardContent>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Tanggal</TableHead>
                                <TableHead className="text-right">Jumlah</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {payments.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={2} className="text-center text-muted-foreground">
                                        Belum ada pembayaran.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                payments.map((payment, index) => (
                                    <TableRow key={index}>
                                        <TableCell>{formatDate(payment.paid_at)}</TableCell>
                                        <TableCell className="text-right">{formatRupiah(payment.amount)}</TableCell>
                                    </TableRow>
                                ))
                            )}
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>

            {previewOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
                    <div className="relative mx-4 flex h-[90vh] w-full max-w-4xl flex-col rounded-lg bg-white shadow-xl">
                        <div className="flex items-center justify-between border-b px-4 py-3">
                            <h3 className="text-lg font-medium">Preview Invoice</h3>
                            <div className="flex gap-2">
                                <a
                                    href={`/invoices/${invoice.id}/pdf`}
                                    className="rounded-md bg-primary px-3 py-1.5 text-sm font-medium text-primary-foreground hover:bg-primary/90"
                                >
                                    Download
                                </a>
                                <button
                                    onClick={() => setPreviewOpen(false)}
                                    className="rounded-md px-3 py-1.5 text-sm text-muted-foreground hover:text-foreground"
                                >
                                    Tutup
                                </button>
                            </div>
                        </div>
                        <iframe
                            src={`/invoices/${invoice.id}/pdf/preview`}
                            className="flex-1 border-0"
                        />
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
