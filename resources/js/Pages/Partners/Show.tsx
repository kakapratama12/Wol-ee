import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft, Trash2 } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import InvoiceStatusBadge from '@/Components/InvoiceStatusBadge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';
import { Badge } from '@/Components/ui/badge';
import { formatDate, formatRupiah } from '@/lib/format';
import type { PageProps } from '@/types';

interface Partner {
    id: number;
    name: string;
    type: 'customer' | 'supplier';
    contact: string | null;
    phone: string | null;
    email: string | null;
    address: string | null;
}

interface OutstandingInvoice {
    id: number;
    invoice_number: string;
    amount: number;
    remaining: number;
    due_date: string;
    status: string;
    days_label: string;
}

interface Aging {
    current: number;
    '1-2_months': number;
    '2-3_months': number;
    '3_plus': number;
}

interface Props {
    partner: Partner;
    outstandingInvoices: OutstandingInvoice[];
    aging: Aging;
    totalOutstanding: number;
}

const agingLabels: Record<string, string> = {
    current: 'Current (0-30 hari)',
    '1-2_months': '1-2 bulan',
    '2-3_months': '2-3 bulan',
    '3_plus': '3+ bulan',
};

export default function PartnersShow({ partner, outstandingInvoices, aging, totalOutstanding }: Props) {
    const { props } = usePage<PageProps>();
    const isOwner = props.auth.user.role === 'owner';

    const remove = () => {
        if (confirm(`Hapus partner "${partner.name}"?`)) {
            router.delete(`/partners/${partner.id}`);
        }
    };

    return (
        <AppLayout title={partner.name}>
            <Head title={partner.name} />

            <div className="mb-4">
                <Link href="/partners" className="inline-flex items-center text-sm text-muted-foreground hover:text-foreground">
                    <ArrowLeft className="mr-1 h-4 w-4" />
                    Kembali ke daftar
                </Link>
            </div>

            <div className="grid gap-6 lg:grid-cols-3">
                <Card className="lg:col-span-2">
                    <CardHeader className="flex flex-row items-start justify-between">
                        <div>
                            <CardTitle>{partner.name}</CardTitle>
                            <Badge variant="outline" className="mt-2">
                                {partner.type === 'customer' ? 'Customer' : 'Supplier'}
                            </Badge>
                        </div>
                        {isOwner && (
                            <div className="flex gap-2">
                                <Button size="sm" variant="outline" onClick={remove}>
                                    <Trash2 className="mr-1 h-4 w-4" />
                                    Hapus
                                </Button>
                            </div>
                        )}
                    </CardHeader>
                    <CardContent className="grid gap-2 text-sm sm:grid-cols-2">
                        <p><span className="text-muted-foreground">Kontak:</span> {partner.contact ?? '-'}</p>
                        <p><span className="text-muted-foreground">Telepon:</span> {partner.phone ?? '-'}</p>
                        <p><span className="text-muted-foreground">Email:</span> {partner.email ?? '-'}</p>
                        <p className="sm:col-span-2"><span className="text-muted-foreground">Alamat:</span> {partner.address ?? '-'}</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Aging Summary</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-2 text-sm">
                        <p className="font-medium">Total outstanding: {formatRupiah(totalOutstanding)}</p>
                        {Object.entries(aging).map(([key, value]) => (
                            <div key={key} className="flex justify-between">
                                <span className="text-muted-foreground">{agingLabels[key]}</span>
                                <span>{formatRupiah(value)}</span>
                            </div>
                        ))}
                    </CardContent>
                </Card>
            </div>

            <Card className="mt-6">
                <CardHeader>
                    <CardTitle>Invoice Outstanding</CardTitle>
                </CardHeader>
                <CardContent>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Nomor</TableHead>
                                <TableHead>Sisa</TableHead>
                                <TableHead>Jatuh Tempo</TableHead>
                                <TableHead>Status</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {outstandingInvoices.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={4} className="text-center text-muted-foreground">
                                        Tidak ada invoice outstanding.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                outstandingInvoices.map((invoice) => (
                                    <TableRow key={invoice.id}>
                                        <TableCell>
                                            <Link href={`/invoices/${invoice.id}`} className="font-medium hover:underline">
                                                {invoice.invoice_number}
                                            </Link>
                                        </TableCell>
                                        <TableCell>{formatRupiah(invoice.remaining)}</TableCell>
                                        <TableCell>
                                            {formatDate(invoice.due_date)}
                                            <span className="ml-2 text-xs text-muted-foreground">({invoice.days_label})</span>
                                        </TableCell>
                                        <TableCell>
                                            <InvoiceStatusBadge status={invoice.status} />
                                        </TableCell>
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
