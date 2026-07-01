import { Head } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';
import { formatRupiah } from '@/lib/format';
import { cn } from '@/lib/utils';

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

interface AgingReport {
    summary: {
        total_outstanding: number;
        total_partners: number;
    };
    by_partner: PartnerAging[];
    by_aging: AgingBuckets;
}

interface Props {
    report: AgingReport;
    payableReport: AgingReport;
}

const agingLabels: Record<string, string> = {
    current: 'Current (0-30 hari)',
    '1-2_months': '1-2 bulan',
    '2-3_months': '2-3 bulan',
    '3_plus': '3+ bulan',
};

function AgingSummary({ title, report }: { title: string; report: AgingReport }) {
    return (
        <Card className="lg:col-span-1">
            <CardHeader>
                <CardTitle>{title}</CardTitle>
            </CardHeader>
            <CardContent className="space-y-2 text-sm">
                <p>
                    <span className="text-muted-foreground">Total outstanding:</span>{' '}
                    <span className="font-semibold">
                        {formatRupiah(report.summary.total_outstanding)}
                    </span>
                </p>
                <p>
                    <span className="text-muted-foreground">
                        {title.includes('Supplier') ? 'Supplier' : 'Partner'} berutang:
                    </span>{' '}
                    {report.summary.total_partners}
                </p>
                <div className="border-t pt-3">
                    {Object.entries(report.by_aging).map(([key, value]) => (
                        <div key={key} className="flex justify-between py-1">
                            <span className="text-muted-foreground">
                                {agingLabels[key]}
                            </span>
                            <span>{formatRupiah(value)}</span>
                        </div>
                    ))}
                </div>
            </CardContent>
        </Card>
    );
}

function AgingTable({ report, isSupplier }: { report: AgingReport; isSupplier?: boolean }) {
    return (
        <Card className="lg:col-span-2">
            <CardHeader>
                <CardTitle>Per {isSupplier ? 'Supplier' : 'Partner'}</CardTitle>
            </CardHeader>
            <CardContent>
                {report.by_partner.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        Tidak ada {isSupplier ? 'tagihan' : 'piutang'} outstanding.
                    </p>
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>{isSupplier ? 'Supplier' : 'Partner'}</TableHead>
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
                                    <TableCell className="font-medium">
                                        {row.partner}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        {formatRupiah(row.total)}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        {formatRupiah(row.current)}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        {formatRupiah(row['1-2_months'])}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        {formatRupiah(row['2-3_months'])}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        {formatRupiah(row['3_plus'])}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
            </CardContent>
        </Card>
    );
}

export default function AgingReport({ report, payableReport }: Props) {
    const [activeTab, setActiveTab] = useState<'ar' | 'ap'>('ar');

    return (
        <AppLayout title="Aging Report">
            <Head title="Aging Report" />

            {/* Tab Navigation */}
            <div className="mb-6 flex gap-1 rounded-lg border border-border bg-card p-1">
                <button
                    onClick={() => setActiveTab('ar')}
                    className={cn(
                        'flex-1 rounded-md px-4 py-2 text-sm font-medium transition-colors',
                        activeTab === 'ar'
                            ? 'bg-primary text-primary-foreground'
                            : 'text-muted-foreground hover:bg-accent',
                    )}
                >
                    Piutang (AR)
                </button>
                <button
                    onClick={() => setActiveTab('ap')}
                    className={cn(
                        'flex-1 rounded-md px-4 py-2 text-sm font-medium transition-colors',
                        activeTab === 'ap'
                            ? 'bg-primary text-primary-foreground'
                            : 'text-muted-foreground hover:bg-accent',
                    )}
                >
                    Tagihan Supplier (AP)
                </button>
            </div>

            {activeTab === 'ar' ? (
                <div className="grid gap-6 lg:grid-cols-3">
                    <AgingSummary title="Ringkasan Piutang" report={report} />
                    <AgingTable report={report} />
                </div>
            ) : (
                <div className="grid gap-6 lg:grid-cols-3">
                    <AgingSummary title="Ringkasan Tagihan Supplier" report={payableReport} />
                    <AgingTable report={payableReport} isSupplier />
                </div>
            )}
        </AppLayout>
    );
}
