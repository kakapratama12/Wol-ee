import { Head } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import StatCard from '@/Components/StatCard';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';
import { Activity, AlertTriangle, BarChart3, Gauge, Timer } from 'lucide-react';
import { formatNumber } from '@/lib/format';

interface Summary {
    today: number;
    quota_today: number;
    last_7_days: number;
    peak_rpm: number;
    error_rate: number;
    tokens: number;
}
interface DailyRow {
    date: string;
    count: number;
    errors: number;
}
interface TenantUsage {
    tenant_id: number;
    tenant: string;
    plan: string;
    today: number;
    quota_today: number;
    last_7_days: number;
    peak_rpm: number;
    error_rate: number;
    tokens: number;
}
interface PlanUsage {
    plan: string;
    tenants: number;
    usage_today: number;
    quota_today: number;
    daily_quota: number;
    provider: string;
    provider_rpm_limit: number;
    provider_rpd_limit: number;
    tokens: number;
}
interface ProviderUsage {
    provider: string;
    label: string;
    today: number;
    last_7_days: number;
    peak_rpm: number;
    rpm_limit: number;
    rpd_limit: number;
    error_rate: number;
    tokens: number;
}

interface Props {
    summary: Summary;
    daily: DailyRow[];
    byTenant: TenantUsage[];
    byPlan: PlanUsage[];
    byProvider: ProviderUsage[];
}

function percent(value: number, limit: number): string {
    if (limit <= 0) return '0%';
    return `${Math.min(100, Math.max(2, (value / limit) * 100))}%`;
}

export default function AiUsage({ summary, daily, byTenant, byPlan, byProvider }: Props) {
    const maxDaily = Math.max(1, ...daily.map((row) => row.count));

    return (
        <AppLayout title="AI Usage">
            <Head title="Platform AI Usage" />

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                <StatCard
                    label="Request Hari Ini"
                    value={formatNumber(summary.today)}
                    icon={<Activity className="h-5 w-5" />}
                />
                <StatCard
                    label="Quota Consumed"
                    value={formatNumber(summary.quota_today)}
                    icon={<Gauge className="h-5 w-5" />}
                />
                <StatCard
                    label="Request 7 Hari"
                    value={formatNumber(summary.last_7_days)}
                    accent="success"
                    icon={<BarChart3 className="h-5 w-5" />}
                />
                <StatCard
                    label="Peak Req/Min"
                    value={formatNumber(summary.peak_rpm)}
                    icon={<Timer className="h-5 w-5" />}
                />
                <StatCard
                    label="Error Rate"
                    value={`${summary.error_rate}%`}
                    accent={summary.error_rate > 5 ? 'danger' : 'default'}
                    icon={<AlertTriangle className="h-5 w-5" />}
                />
            </div>

            <Card className="mt-6">
                <CardHeader>
                    <CardTitle>7 Hari Terakhir</CardTitle>
                    <CardDescription>
                        Request LLM aktual, bukan hanya counter quota.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div className="grid gap-3 md:grid-cols-7">
                        {daily.map((row) => (
                            <div key={row.date} className="rounded-md border p-3">
                                <div className="text-xs text-muted-foreground">{row.date}</div>
                                <div className="mt-2 h-2 rounded-full bg-muted">
                                    <div
                                        className="h-2 rounded-full bg-primary"
                                        style={{ width: percent(row.count, maxDaily) }}
                                    />
                                </div>
                                <div className="mt-2 text-sm font-medium">
                                    {formatNumber(row.count)} request
                                </div>
                                <div className="text-xs text-muted-foreground">
                                    {formatNumber(row.errors)} error
                                </div>
                            </div>
                        ))}
                    </div>
                </CardContent>
            </Card>

            <div className="mt-6 grid gap-6 xl:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>Provider Limits</CardTitle>
                        <CardDescription>
                            Untuk pantau seberapa dekat ke batas free/pro provider.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Provider</TableHead>
                                    <TableHead>Hari Ini</TableHead>
                                    <TableHead>Peak RPM</TableHead>
                                    <TableHead>Limit</TableHead>
                                    <TableHead>Error</TableHead>
                                    <TableHead>Token</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {byProvider.map((row) => (
                                    <TableRow key={row.provider}>
                                        <TableCell className="font-medium">{row.label}</TableCell>
                                        <TableCell>{formatNumber(row.today)}</TableCell>
                                        <TableCell>{formatNumber(row.peak_rpm)}</TableCell>
                                        <TableCell className="text-xs text-muted-foreground">
                                            {formatNumber(row.rpm_limit)}/min ·{' '}
                                            {formatNumber(row.rpd_limit)}/hari
                                        </TableCell>
                                        <TableCell>{row.error_rate}%</TableCell>
                                        <TableCell>{formatNumber(row.tokens)}</TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Usage per Plan</CardTitle>
                        <CardDescription>
                            Quota produk vs provider yang dipakai setiap plan.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Plan</TableHead>
                                    <TableHead>Tenant</TableHead>
                                    <TableHead>Req Hari Ini</TableHead>
                                    <TableHead>Quota/User</TableHead>
                                    <TableHead>Provider Limit</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {byPlan.map((row) => (
                                    <TableRow key={row.plan}>
                                        <TableCell className="uppercase text-xs text-muted-foreground">
                                            {row.plan}
                                        </TableCell>
                                        <TableCell>{formatNumber(row.tenants)}</TableCell>
                                        <TableCell>
                                            {formatNumber(row.usage_today)}{' '}
                                            <span className="text-xs text-muted-foreground">
                                                ({formatNumber(row.quota_today)} quota)
                                            </span>
                                        </TableCell>
                                        <TableCell>{formatNumber(row.daily_quota)}/hari</TableCell>
                                        <TableCell className="text-xs text-muted-foreground">
                                            {row.provider}: {formatNumber(row.provider_rpm_limit)}
                                            /min · {formatNumber(row.provider_rpd_limit)}/hari
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>

            <Card className="mt-6">
                <CardHeader>
                    <CardTitle>Usage per Tenant</CardTitle>
                    <CardDescription>
                        Dipakai untuk cari tenant yang mendekati limit, error tinggi, atau boros
                        token.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Tenant</TableHead>
                                <TableHead>Plan</TableHead>
                                <TableHead>Hari Ini</TableHead>
                                <TableHead>7 Hari</TableHead>
                                <TableHead>Peak RPM</TableHead>
                                <TableHead>Error</TableHead>
                                <TableHead>Token</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {byTenant.map((row) => (
                                <TableRow key={row.tenant_id}>
                                    <TableCell className="font-medium">{row.tenant}</TableCell>
                                    <TableCell className="uppercase text-xs text-muted-foreground">
                                        {row.plan}
                                    </TableCell>
                                    <TableCell>
                                        {formatNumber(row.today)}{' '}
                                        <span className="text-xs text-muted-foreground">
                                            ({formatNumber(row.quota_today)} quota)
                                        </span>
                                    </TableCell>
                                    <TableCell>{formatNumber(row.last_7_days)}</TableCell>
                                    <TableCell>{formatNumber(row.peak_rpm)}</TableCell>
                                    <TableCell>{row.error_rate}%</TableCell>
                                    <TableCell>{formatNumber(row.tokens)}</TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>
        </AppLayout>
    );
}
