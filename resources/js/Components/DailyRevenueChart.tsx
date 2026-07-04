import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { formatRupiah } from '@/lib/format';
import { ResponsiveContainer, AreaChart, Area, XAxis, YAxis, Tooltip, CartesianGrid } from 'recharts';

interface DailyPoint {
    date: string;
    label: string;
    revenue: number;
}

interface Props {
    data: DailyPoint[];
}

export default function DailyRevenueChart({ data }: Props) {
    if (!data || data.length === 0) {
        return (
            <Card>
                <CardHeader>
                    <CardTitle>Omset 30 Hari Terakhir</CardTitle>
                </CardHeader>
                <CardContent>
                    <p className="py-6 text-center text-sm text-muted-foreground">
                        Belum ada data penjualan.
                    </p>
                </CardContent>
            </Card>
        );
    }

    const maxRevenue = Math.max(...data.map((d) => d.revenue));

    return (
        <Card>
            <CardHeader>
                <CardTitle>Omset 30 Hari Terakhir</CardTitle>
            </CardHeader>
            <CardContent>
                <div className="h-[250px] w-full">
                    <ResponsiveContainer width="100%" height="100%">
                        <AreaChart data={data} margin={{ top: 5, right: 10, left: 10, bottom: 0 }}>
                            <defs>
                                <linearGradient id="revenueGradient" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="5%" stopColor="hsl(var(--primary))" stopOpacity={0.3} />
                                    <stop offset="95%" stopColor="hsl(var(--primary))" stopOpacity={0} />
                                </linearGradient>
                            </defs>
                            <CartesianGrid strokeDasharray="3 3" className="stroke-muted" />
                            <XAxis
                                dataKey="label"
                                tick={{ fontSize: 11 }}
                                className="text-muted-foreground"
                                interval="preserveStartEnd"
                            />
                            <YAxis
                                tick={{ fontSize: 11 }}
                                className="text-muted-foreground"
                                tickFormatter={(v) => v >= 1000000 ? `${(v / 1000000).toFixed(0)}jt` : v >= 1000 ? `${(v / 1000).toFixed(0)}rb` : v}
                                width={50}
                            />
                            <Tooltip
                                content={({ active, payload }) => {
                                    if (!active || !payload?.length) return null;
                                    const point = payload[0].payload as DailyPoint;
                                    return (
                                        <div className="rounded-lg border bg-card p-3 shadow-md">
                                            <p className="text-xs text-muted-foreground">{point.label}</p>
                                            <p className="text-sm font-semibold text-foreground">{formatRupiah(point.revenue)}</p>
                                        </div>
                                    );
                                }}
                            />
                            <Area
                                type="monotone"
                                dataKey="revenue"
                                stroke="hsl(var(--primary))"
                                strokeWidth={2}
                                fill="url(#revenueGradient)"
                            />
                        </AreaChart>
                    </ResponsiveContainer>
                </div>
            </CardContent>
        </Card>
    );
}
