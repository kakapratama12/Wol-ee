import { Head } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';
import { formatRupiah } from '@/lib/format';

interface AgingBuckets {
    current: number;
    '1-2_months': number;
    '2-3_months': number;
    '3_plus': number;
}

interface PartnerAging {
    partner_id: number;
    partner: string;
    total: number;
    current: number;
    '1-2_months': number;
    '2-3_months': number;
    '3_plus': number;
}

interface Props {
    report: {
        summary: {
            total_outstanding: number;
            total_partners: number;
        };
        by_partner: PartnerAging[];
        by_aging: AgingBuckets;
    };
}

const agingLabels: Record<string, string> = {
    current: 'Current (0-30 hari)',
    '1-2_months': '1-2 bulan',
    '2-3_months': '2-3 bulan',
    '3_plus': '3+ bulan',
};

export default function AgingReport({ report }: Props) {
    return (
        <AppLayout title="Aging Report">
            <Head title="Aging Report" />

            <div className="grid gap-6 lg:grid-cols-3">
                <Card className="lg:col-span-1">
                    <CardHeader>
                        <CardTitle>Ringkasan Piutang</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-2 text-sm">
                        <p>
                            <span className="text-muted-foreground">Total outstanding:</span>{' '}
                            <span className="font-semibold">{formatRupiah(report.summary.total_outstanding)}</span>
                        </p>
                        <p>
                            <span className="text-muted-foreground">Partner berutang:</span>{' '}
                            {report.summary.total_partners}
                        </p>
                        <div className="border-t pt-3">
                            {Object.entries(report.by_aging).map(([key, value]) => (
                                <div key={key} className="flex justify-between py-1">
                                    <span className="text-muted-foreground">{agingLabels[key]}</span>
                                    <span>{formatRupiah(value)}</span>
                                </div>
                            ))}
                        </div>
                    </CardContent>
                </Card>

                <Card className="lg:col-span-2">
                    <CardHeader>
                        <CardTitle>Per Partner</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {report.by_partner.length === 0 ? (
                            <p className="text-sm text-muted-foreground">Tidak ada piutang outstanding.</p>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Partner</TableHead>
                                        <TableHead className="text-right">Total</TableHead>
                                        <TableHead className="text-right">Current</TableHead>
                                        <TableHead className="text-right">1-2 bln</TableHead>
                                        <TableHead className="text-right">2-3 bln</TableHead>
                                        <TableHead className="text-right">3+ bln</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {report.by_partner.map((row) => (
                                        <TableRow key={row.partner_id}>
                                            <TableCell className="font-medium">{row.partner}</TableCell>
                                            <TableCell className="text-right">{formatRupiah(row.total)}</TableCell>
                                            <TableCell className="text-right">{formatRupiah(row.current)}</TableCell>
                                            <TableCell className="text-right">{formatRupiah(row['1-2_months'])}</TableCell>
                                            <TableCell className="text-right">{formatRupiah(row['2-3_months'])}</TableCell>
                                            <TableCell className="text-right">{formatRupiah(row['3_plus'])}</TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
