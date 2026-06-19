import { Head, router } from '@inertiajs/react';
import { Download } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Select } from '@/Components/ui/select';
import { formatRupiah, formatPercent } from '@/lib/format';
import { cn } from '@/lib/utils';

interface ExpenseRow {
    category: string;
    amount: number;
}

interface Report {
    revenue: number;
    cogs: number;
    gross_profit: number;
    gross_margin: number;
    expenses: ExpenseRow[];
    total_expenses: number;
    net_profit: number;
    net_margin: number;
}

interface Props {
    report: Report;
    period: { month: number; year: number };
    periodLabel: string;
}

const months = [
    'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
];

export default function Pnl({ report, period, periodLabel }: Props) {
    const years = Array.from({ length: 5 }, (_, i) => new Date().getFullYear() - i);

    const changePeriod = (month: number, year: number) => {
        router.get('/pnl', { month, year }, { preserveState: true, preserveScroll: true });
    };

    return (
        <AppLayout title="Laporan P&L">
            <Head title="Laporan P&L" />

            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div className="flex gap-2">
                    <Select
                        className="w-40"
                        value={period.month}
                        onChange={(e) => changePeriod(Number(e.target.value), period.year)}
                    >
                        {months.map((m, i) => (
                            <option key={i} value={i + 1}>
                                {m}
                            </option>
                        ))}
                    </Select>
                    <Select className="w-28" value={period.year} onChange={(e) => changePeriod(period.month, Number(e.target.value))}>
                        {years.map((y) => (
                            <option key={y} value={y}>
                                {y}
                            </option>
                        ))}
                    </Select>
                </div>
                <a href={`/pnl/export?month=${period.month}&year=${period.year}`}>
                    <Button variant="outline">
                        <Download className="h-4 w-4" /> Export Excel
                    </Button>
                </a>
            </div>

            <Card className="mx-auto max-w-2xl">
                <CardHeader>
                    <CardTitle>Laporan P&L - {periodLabel}</CardTitle>
                </CardHeader>
                <CardContent className="space-y-1 text-sm">
                    <Line label="Revenue" value={formatRupiah(report.revenue)} />
                    <Line label="COGS" value={`(${formatRupiah(report.cogs)})`} muted />
                    <Line label="Gross Profit" value={formatRupiah(report.gross_profit)} bold hint={`Margin ${formatPercent(report.gross_margin)}`} />

                    <div className="my-3 border-t border-border" />
                    <p className="font-semibold">Expenses</p>
                    {report.expenses.length === 0 ? (
                        <p className="py-1 text-muted-foreground">Belum ada biaya tercatat.</p>
                    ) : (
                        report.expenses.map((e) => (
                            <Line key={e.category} label={`- ${capitalize(e.category)}`} value={`(${formatRupiah(e.amount)})`} muted />
                        ))
                    )}
                    <Line label="Total Expenses" value={`(${formatRupiah(report.total_expenses)})`} bold muted />

                    <Line
                        label="Laba (Rugi) bersih"
                        value={formatRupiah(report.net_profit)}
                        bold
                        hint={`Margin ${formatPercent(report.net_margin)}`}
                        big
                        negative={report.net_profit < 0}
                    />
                </CardContent>
            </Card>
        </AppLayout>
    );
}

function capitalize(s: string) {
    return s.charAt(0).toUpperCase() + s.slice(1);
}

function Line({
    label,
    value,
    bold,
    muted,
    big,
    hint,
    negative,
}: {
    label: string;
    value: string;
    bold?: boolean;
    muted?: boolean;
    big?: boolean;
    hint?: string;
    negative?: boolean;
}) {
    return (
        <div className="flex items-center justify-between py-0.5">
            <span className={muted ? 'text-muted-foreground' : ''}>{label}</span>
            <span
                className={cn(
                    bold && 'font-bold',
                    big && 'text-lg',
                    negative ? 'text-destructive' : big ? 'text-success' : '',
                )}
            >
                {value}
                {hint && <span className="ml-2 text-xs font-normal text-muted-foreground">{hint}</span>}
            </span>
        </div>
    );
}
