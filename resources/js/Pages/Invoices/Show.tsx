import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
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

interface Props {
    invoice: Invoice;
    payments: Payment[];
}

export default function InvoicesShow({ invoice, payments }: Props) {
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

            <div className="grid gap-6 lg:grid-cols-3">
                <Card className="lg:col-span-2">
                    <CardHeader className="flex flex-row items-center justify-between">
                        <CardTitle>{invoice.invoice_number}</CardTitle>
                        <InvoiceStatusBadge status={invoice.status} />
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
        </AppLayout>
    );
}
