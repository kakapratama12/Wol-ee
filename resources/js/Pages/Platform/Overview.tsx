import { Head, Link } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import StatCard from '@/Components/StatCard';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';
import { Building2, MessageSquare, Users, Zap } from 'lucide-react';
import { formatDate, formatNumber } from '@/lib/format';

interface Stats {
    tenantCount: number;
    activeTenantCount: number;
    userCount: number;
    newFeedbackCount: number;
    aiUsageToday: number;
}

interface FeedbackRow {
    id: number;
    tenant: string | null;
    feedback_text: string;
    status: string;
    created_at: string | null;
}

interface Props {
    stats: Stats;
    recentFeedback: FeedbackRow[];
}

export default function Overview({ stats, recentFeedback }: Props) {
    return (
        <AppLayout title="Platform Overview">
            <Head title="Platform Overview" />

            <p className="mb-4 text-sm text-muted-foreground">
                Ringkasan operasional Wol-ee early adopter.
            </p>

            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard
                    label="Tenant"
                    value={formatNumber(stats.tenantCount)}
                    hint={`${stats.activeTenantCount} aktif`}
                    icon={<Building2 className="h-5 w-5" />}
                />
                <StatCard
                    label="User"
                    value={formatNumber(stats.userCount)}
                    icon={<Users className="h-5 w-5" />}
                />
                <StatCard
                    label="Feedback Baru"
                    value={formatNumber(stats.newFeedbackCount)}
                    accent="warning"
                    icon={<MessageSquare className="h-5 w-5" />}
                />
                <StatCard
                    label="AI Usage Hari Ini"
                    value={formatNumber(stats.aiUsageToday)}
                    accent="success"
                    icon={<Zap className="h-5 w-5" />}
                />
            </div>

            <Card className="mt-6">
                <CardHeader>
                    <CardTitle>Feedback Terbaru</CardTitle>
                </CardHeader>
                <CardContent>
                    {recentFeedback.length === 0 ? (
                        <p className="py-6 text-center text-sm text-muted-foreground">
                            Belum ada feedback.
                        </p>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Tanggal</TableHead>
                                    <TableHead>Tenant</TableHead>
                                    <TableHead>Feedback</TableHead>
                                    <TableHead>Status</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {recentFeedback.map((row) => (
                                    <TableRow key={row.id}>
                                        <TableCell className="text-muted-foreground">
                                            {formatDate(row.created_at)}
                                        </TableCell>
                                        <TableCell>{row.tenant ?? '-'}</TableCell>
                                        <TableCell className="max-w-xl truncate">
                                            {row.feedback_text}
                                        </TableCell>
                                        <TableCell className="uppercase text-xs text-muted-foreground">
                                            {row.status}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                    <Link
                        href="/platform/feedback"
                        className="mt-3 inline-block text-sm font-medium text-primary hover:underline"
                    >
                        Lihat feedback inbox
                    </Link>
                </CardContent>
            </Card>
        </AppLayout>
    );
}
