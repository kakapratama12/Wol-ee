import { Head } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import StatCard from '@/Components/StatCard';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';
import { Activity, BarChart3 } from 'lucide-react';
import { formatNumber } from '@/lib/format';

interface Summary { today: number; last_7_days: number }
interface DailyRow { date: string; count: number }
interface TenantUsage { tenant_id: number; tenant: string; plan: string; today: number; last_7_days: number }
interface PlanUsage { plan: string; tenants: number; usage_today: number }

interface Props {
    summary: Summary;
    daily: DailyRow[];
    byTenant: TenantUsage[];
    byPlan: PlanUsage[];
}

export default function AiUsage({ summary, daily, byTenant, byPlan }: Props) {
    const maxDaily = Math.max(1, ...daily.map((row) => row.count));

    return (
        <AppLayout title="AI Usage">
            <Head title="Platform AI Usage" />

            <div className="grid gap-4 sm:grid-cols-2">
                <StatCard label="AI Usage Hari Ini" value={formatNumber(summary.today)} icon={<Activity className="h-5 w-5" />} />
                <StatCard label="AI Usage 7 Hari" value={formatNumber(summary.last_7_days)} accent="success" icon={<BarChart3 className="h-5 w-5" />} />
            </div>

            <Card className="mt-6">
                <CardHeader><CardTitle>7 Hari Terakhir</CardTitle></CardHeader>
                <CardContent>
                    <div className="grid gap-3 md:grid-cols-7">
                        {daily.map((row) => (
                            <div key={row.date} className="rounded-md border p-3">
                                <div className="text-xs text-muted-foreground">{row.date}</div>
                                <div className="mt-2 h-2 rounded-full bg-muted">
                                    <div className="h-2 rounded-full bg-primary" style={{ width: `${Math.max(2, (row.count / maxDaily) * 100)}%` }} />
                                </div>
                                <div className="mt-2 text-sm font-medium">{formatNumber(row.count)}</div>
                            </div>
                        ))}
                    </div>
                </CardContent>
            </Card>

            <div className="mt-6 grid gap-6 lg:grid-cols-2">
                <Card>
                    <CardHeader><CardTitle>Usage per Tenant</CardTitle></CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader><TableRow><TableHead>Tenant</TableHead><TableHead>Plan</TableHead><TableHead>Hari Ini</TableHead><TableHead>7 Hari</TableHead></TableRow></TableHeader>
                            <TableBody>
                                {byTenant.map((row) => (
                                    <TableRow key={row.tenant_id}>
                                        <TableCell className="font-medium">{row.tenant}</TableCell>
                                        <TableCell className="uppercase text-xs text-muted-foreground">{row.plan}</TableCell>
                                        <TableCell>{formatNumber(row.today)}</TableCell>
                                        <TableCell>{formatNumber(row.last_7_days)}</TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader><CardTitle>Usage per Plan</CardTitle></CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader><TableRow><TableHead>Plan</TableHead><TableHead>Tenant</TableHead><TableHead>Usage Hari Ini</TableHead></TableRow></TableHeader>
                            <TableBody>
                                {byPlan.map((row) => (
                                    <TableRow key={row.plan}>
                                        <TableCell className="uppercase text-xs text-muted-foreground">{row.plan}</TableCell>
                                        <TableCell>{formatNumber(row.tenants)}</TableCell>
                                        <TableCell>{formatNumber(row.usage_today)}</TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
