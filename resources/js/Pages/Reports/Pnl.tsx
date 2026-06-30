import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { Download, ChevronDown, ChevronRight } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Select } from '@/Components/ui/select';
import { formatRupiah, formatPercent } from '@/lib/format';
import { cn } from '@/lib/utils';

interface RevenueItem {
    product: string;
    revenue: number;
}

interface CogsItem {
    product: string;
    cogs: number;
}

interface ExpenseItem {
    category: string;
    description: string;
    amount: number;
}

interface Report {
    revenue: number;
    revenue_by_product: RevenueItem[];
    cogs: number;
    cogs_by_product: CogsItem[];
    gross_profit: number;
    gross_margin: number;
    expenses: ExpenseItem[];
    total_expenses: number;
    expenses_by_category: Record<string, number>;
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

const categoryLabels: Record<string, string> = {
    bahan_baku: 'Bahan Baku',
    operasional: 'Operasional',
    logistik: 'Logistik/Pengiriman',
    overhead: 'Overhead',
    non_operasional: 'Di Luar Usaha',
};

const categoryColors: Record<string, string> = {
    bahan_baku: 'text-blue-700',
    operasional: 'text-green-700',
    logistik: 'text-purple-700',
    overhead: 'text-orange-700',
    non_operasional: 'text-slate-700',
};

export default function Pnl({ report, period, periodLabel }: Props) {
    const years = Array.from({ length: 5 }, (_, i) => new Date().getFullYear() - i);
    const [showRevenue, setShowRevenue] = useState(false);
    const [showCogs, setShowCogs] = useState(false);
    const [showExpenses, setShowExpenses] = useState(false);

    const changePeriod = (month: number, year: number) => {
        router.get('/pnl', { month, year }, { preserveState: true, preserveScroll: true });
    };

    // Group expenses by category
    const expensesByCategory = report.expenses.reduce((acc, e) => {
        if (!acc[e.category]) acc[e.category] = [];
        acc[e.category].push(e);
        return acc;
    }, {} as Record<string, ExpenseItem[]>);

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
                    {/* Revenue Section */}
                    <CollapsibleRow
                        label="Revenue"
                        value={formatRupiah(report.revenue)}
                        expanded={showRevenue}
                        onToggle={() => setShowRevenue(!showRevenue)}
                        count={report.revenue_by_product.length}
                    >
                        {report.revenue_by_product.map((item) => (
                            <div key={item.product} className="flex items-center justify-between py-0.5 pl-4">
                                <span className="text-muted-foreground">{item.product}</span>
                                <span>{formatRupiah(item.revenue)}</span>
                            </div>
                        ))}
                    </CollapsibleRow>

                    {/* COGS Section */}
                    <CollapsibleRow
                        label="COGS"
                        value={formatRupiah(report.cogs)}
                        expanded={showCogs}
                        onToggle={() => setShowCogs(!showCogs)}
                        count={report.cogs_by_product.length}
                    >
                        {report.cogs_by_product.map((item) => (
                            <div key={item.product} className="flex items-center justify-between py-0.5 pl-4">
                                <span className="text-muted-foreground">{item.product}</span>
                                <span>{formatRupiah(item.cogs)}</span>
                            </div>
                        ))}
                    </CollapsibleRow>

                    <Line
                        label="Laba Kotor"
                        value={formatRupiah(report.gross_profit)}
                        bold
                        hint={`Margin ${formatPercent(report.gross_margin)}`}
                    />

                    <div className="my-3 border-t border-border" />

                    {/* Expenses Section */}
                    <CollapsibleRow
                        label="Expenses"
                        value={formatRupiah(report.total_expenses)}
                        expanded={showExpenses}
                        onToggle={() => setShowExpenses(!showExpenses)}
                        count={report.expenses.length}
                    >
                        {Object.entries(expensesByCategory).map(([category, items]) => (
                            <div key={category} className="mt-2">
                                <p className={cn('font-semibold text-xs', categoryColors[category] ?? 'text-slate-700')}>
                                    {categoryLabels[category] ?? category}
                                </p>
                                {items.map((item, idx) => (
                                    <div key={idx} className="flex items-center justify-between py-0.5 pl-4">
                                        <span className="text-muted-foreground">{item.description}</span>
                                        <span>{formatRupiah(item.amount)}</span>
                                    </div>
                                ))}
                            </div>
                        ))}
                    </CollapsibleRow>

                    <div className="my-3 border-t border-border" />

                    <Line
                        label="Laba Bersih"
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

function CollapsibleRow({
    label,
    value,
    expanded,
    onToggle,
    count,
    children,
}: {
    label: string;
    value: string;
    expanded: boolean;
    onToggle: () => void;
    count: number;
    children: React.ReactNode;
}) {
    return (
        <div>
            <button
                type="button"
                onClick={onToggle}
                className="flex w-full items-center justify-between py-0.5 hover:bg-muted/50 rounded"
            >
                <span className="flex items-center gap-1">
                    {expanded ? <ChevronDown className="h-3 w-3" /> : <ChevronRight className="h-3 w-3" />}
                    {label}
                    <span className="text-xs text-muted-foreground">({count})</span>
                </span>
                <span className="text-number">{value}</span>
            </button>
            {expanded && <div className="ml-2 mt-1 space-y-0.5">{children}</div>}
        </div>
    );
}

function Line({
    label,
    value,
    bold,
    big,
    hint,
    negative,
}: {
    label: string;
    value: string;
    bold?: boolean;
    big?: boolean;
    hint?: string;
    negative?: boolean;
}) {
    return (
        <div className="flex items-center justify-between py-0.5">
            <span>{label}</span>
            <span
                className={cn(
                    'text-number',
                    bold && 'font-bold',
                    big && 'text-number-lg',
                    negative ? 'text-destructive' : big ? 'text-success' : '',
                )}
            >
                {value}
                {hint && <span className="ml-2 text-caption">{hint}</span>}
            </span>
        </div>
    );
}
