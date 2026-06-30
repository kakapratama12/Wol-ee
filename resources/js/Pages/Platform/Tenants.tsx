import { useState } from 'react';
import { Head } from '@inertiajs/react';
import { Search, ChevronDown, ChevronRight } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';
import { formatDate, formatNumber } from '@/lib/format';
import { cn } from '@/lib/utils';

interface PengelolaUser {
    id: number;
    name: string;
    email: string;
}

interface TenantRow {
    id: number;
    name: string;
    slug: string;
    plan: string;
    status: string;
    users_count: number;
    pengelola_count: number;
    pengelola_users: PengelolaUser[];
    staff_count: number;
    has_bot_token: boolean;
    ai_usage_today: number;
    feedback_count: number;
    created_at: string | null;
}

export default function Tenants({ tenants }: { tenants: TenantRow[] }) {
    const [search, setSearch] = useState('');
    const [expandedTenant, setExpandedTenant] = useState<number | null>(null);

    const filteredTenants = tenants.filter((tenant) => 
        search === '' || 
        tenant.name.toLowerCase().includes(search.toLowerCase()) ||
        tenant.slug.toLowerCase().includes(search.toLowerCase())
    );

    const toggleExpand = (id: number) => {
        setExpandedTenant(expandedTenant === id ? null : id);
    };

    return (
        <AppLayout title="Usaha">
            <Head title="Platform Usaha" />

            <div className="mb-4">
                <p className="text-sm text-muted-foreground">
                    {filteredTenants.length} usaha dari {tenants.length} total
                </p>
            </div>

            {/* Search */}
            <Card className="mb-6">
                <CardContent className="p-4">
                    <div className="relative">
                        <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            placeholder="Cari nama atau slug usaha..."
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            className="pl-9"
                        />
                    </div>
                </CardContent>
            </Card>

            {/* Tenants Table */}
            <Card>
                <CardContent className="p-0">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead className="w-8"></TableHead>
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
                            {filteredTenants.map((tenant) => (
                                <>
                                    <TableRow key={tenant.id}>
                                        <TableCell>
                                            <button
                                                type="button"
                                                onClick={() => toggleExpand(tenant.id)}
                                                className="rounded p-1 hover:bg-accent"
                                            >
                                                {expandedTenant === tenant.id ? (
                                                    <ChevronDown className="h-4 w-4" />
                                                ) : (
                                                    <ChevronRight className="h-4 w-4" />
                                                )}
                                            </button>
                                        </TableCell>
                                        <TableCell>
                                            <div className="font-medium">{tenant.name}</div>
                                            <div className="text-xs text-muted-foreground">{tenant.slug}</div>
                                        </TableCell>
                                        <TableCell className="uppercase text-xs text-muted-foreground">{tenant.plan}</TableCell>
                                        <TableCell className="uppercase text-xs text-muted-foreground">{tenant.status}</TableCell>
                                        <TableCell>
                                            {formatNumber(tenant.users_count)}
                                            <span className="ml-1 text-xs text-muted-foreground">
                                                ({tenant.pengelola_count} pengelola, {tenant.staff_count} staff)
                                            </span>
                                        </TableCell>
                                        <TableCell>{tenant.has_bot_token ? 'Aktif' : '-'}</TableCell>
                                        <TableCell>{formatNumber(tenant.ai_usage_today)}</TableCell>
                                        <TableCell>{formatNumber(tenant.feedback_count)}</TableCell>
                                        <TableCell className="text-muted-foreground">{formatDate(tenant.created_at)}</TableCell>
                                    </TableRow>
                                    {expandedTenant === tenant.id && (
                                        <TableRow key={`${tenant.id}-detail`}>
                                            <TableCell colSpan={9} className="bg-muted/30">
                                                <div className="py-4 px-6">
                                                    <h4 className="mb-3 text-sm font-medium">Pengelola</h4>
                                                    {tenant.pengelola_users.length > 0 ? (
                                                        <div className="space-y-2">
                                                            {tenant.pengelola_users.map((user) => (
                                                                <div key={user.id} className="flex items-center gap-3">
                                                                    <div className="flex h-8 w-8 items-center justify-center rounded-full bg-primary/10 text-sm font-medium text-primary">
                                                                        {user.name.charAt(0).toUpperCase()}
                                                                    </div>
                                                                    <div>
                                                                        <div className="text-sm font-medium">{user.name}</div>
                                                                        <div className="text-xs text-muted-foreground">{user.email}</div>
                                                                    </div>
                                                                </div>
                                                            ))}
                                                        </div>
                                                    ) : (
                                                        <p className="text-sm text-muted-foreground">Belum ada pengelola</p>
                                                    )}
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    )}
                                </>
                            ))}
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>
        </AppLayout>
    );
}
