import { Head } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';
import { formatDate, formatNumber } from '@/lib/format';

interface TenantRow {
    id: number;
    name: string;
    slug: string;
    plan: string;
    status: string;
    users_count: number;
    pengelola_count: number;
    admin_count: number;
    has_bot_token: boolean;
    ai_usage_today: number;
    feedback_count: number;
    created_at: string | null;
}

export default function Tenants({ tenants }: { tenants: TenantRow[] }) {
    return (
        <AppLayout title="Usaha">
            <Head title="Platform Usaha" />

            <Card>
                <CardHeader>
                    <CardTitle>Daftar Usaha</CardTitle>
                </CardHeader>
                <CardContent>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Usaha</TableHead>
                                <TableHead>Plan</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Users</TableHead>
                                <TableHead>Bot Token</TableHead>
                                <TableHead>AI Hari Ini</TableHead>
                                <TableHead>Feedback</TableHead>
                                <TableHead>Dibuat</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {tenants.map((tenant) => (
                                <TableRow key={tenant.id}>
                                    <TableCell>
                                        <div className="font-medium">{tenant.name}</div>
                                        <div className="text-xs text-muted-foreground">{tenant.slug}</div>
                                    </TableCell>
                                    <TableCell className="uppercase text-xs text-muted-foreground">{tenant.plan}</TableCell>
                                    <TableCell className="uppercase text-xs text-muted-foreground">{tenant.status}</TableCell>
                                    <TableCell>
                                        {formatNumber(tenant.users_count)}
                                        <span className="ml-1 text-xs text-muted-foreground">({tenant.pengelola_count} pengelola, {tenant.admin_count} admin)</span>
                                    </TableCell>
                                    <TableCell>{tenant.has_bot_token ? 'Aktif' : '-'}</TableCell>
                                    <TableCell>{formatNumber(tenant.ai_usage_today)}</TableCell>
                                    <TableCell>{formatNumber(tenant.feedback_count)}</TableCell>
                                    <TableCell className="text-muted-foreground">{formatDate(tenant.created_at)}</TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>
        </AppLayout>
    );
}
