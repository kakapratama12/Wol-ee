import { Head, Link } from '@inertiajs/react';
import { Store, Clock, ShoppingBag } from 'lucide-react';
import PosLayout from '@/Layouts/PosLayout';
import { Button } from '@/Components/ui/button';
import { formatRupiah } from '@/lib/format';
import { cn } from '@/lib/utils';

interface TodaySession {
    id: number;
    status: 'open' | 'closed';
    opened_at: string | null;
    closed_at: string | null;
    opening_cash: number;
    total_omset: number;
    total_orders: number;
    outlet: string | null;
}

interface RecentSession {
    id: number;
    date: string;
    opened_at: string | null;
    closed_at: string | null;
    total_omset: number;
    total_orders: number;
    outlet: string | null;
    total_cash?: number;
    total_qris?: number;
    total_transfer?: number;
    expected_cash?: number;
    variance?: number;
}

interface Props {
    todaySession: TodaySession | null;
    recentSessions: RecentSession[];
}

export default function PosIndex({ todaySession, recentSessions }: Props) {
    const isOpen = todaySession?.status === 'open';

    return (
        <PosLayout title="POS">
            <Head title="POS" />

            <div className="mx-auto max-w-2xl space-y-6">
                {/* Status Toko Hari Ini */}
                <div className="rounded-xl border border-border bg-card p-6 shadow-sm">
                    <div className="flex items-center gap-3">
                        <div className={`flex h-12 w-12 items-center justify-center rounded-full ${isOpen ? 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300' : 'bg-muted text-muted-foreground'}`}>
                            <Store className="h-6 w-6" />
                        </div>
                        <div>
                            <h2 className="text-lg font-semibold">
                                {isOpen ? 'Toko Buka' : 'Toko Tutup'}
                            </h2>
                            {todaySession?.outlet && (
                                <p className="text-sm text-muted-foreground">{todaySession.outlet}</p>
                            )}
                        </div>
                    </div>

                    {isOpen && todaySession && (
                        <div className="mt-4 grid grid-cols-3 gap-4 text-center">
                            <div>
                                <p className="text-2xl font-bold text-primary">{formatRupiah(todaySession.total_omset)}</p>
                                <p className="text-xs text-muted-foreground">Omset Hari Ini</p>
                            </div>
                            <div>
                                <p className="text-2xl font-bold">{todaySession.total_orders}</p>
                                <p className="text-xs text-muted-foreground">Transaksi</p>
                            </div>
                            <div>
                                <p className="text-2xl font-bold">{todaySession.opened_at ? new Date(todaySession.opened_at).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }) : '-'}</p>
                                <p className="text-xs text-muted-foreground">Jam Buka</p>
                            </div>
                        </div>
                    )}

                    <div className="mt-6">
                        {isOpen ? (
                            <div className="flex gap-3">
                                <Link
                                    href="/pos/register"
                                    className="flex-1"
                                >
                                    <Button className="h-12 w-full text-base">
                                        <ShoppingBag className="mr-2 h-5 w-5" />
                                        Masuk Kasir
                                    </Button>
                                </Link>
                                <Link
                                    href="/pos/session/close"
                                >
                                    <Button variant="outline" className="h-12 px-6">
                                        Tutup Toko
                                    </Button>
                                </Link>
                            </div>
                        ) : (
                            <Link href="/pos/session/open">
                                <Button className="h-12 w-full text-base" size="lg">
                                    <Store className="mr-2 h-5 w-5" />
                                    Buka Toko
                                </Button>
                            </Link>
                        )}
                    </div>
                </div>


                {/* Riwayat Sesi */}
                {recentSessions.length > 0 && (
                    <div className="rounded-xl border border-border bg-card p-6 shadow-sm">
                        <h3 className="mb-4 text-sm font-semibold text-muted-foreground">Riwayat 5 Hari Terakhir</h3>
                        <div className="space-y-3">
                            {recentSessions.map((session) => (
                                <div
                                    key={session.id}
                                    className="flex items-center justify-between rounded-lg border border-border p-3"
                                >
                                    <div className="flex items-center gap-3">
                                        <div className="flex h-10 w-10 items-center justify-center rounded-full bg-muted">
                                            <Clock className="h-5 w-5 text-muted-foreground" />
                                        </div>
                                        <div>
                                            <p className="text-sm font-medium">{session.date}</p>
                                            <p className="text-xs text-muted-foreground">
                                                {session.opened_at} - {session.closed_at || '?'}
                                            </p>
                                        </div>
                                    </div>
                                    <div className="text-right">
                                        <p className="text-sm font-semibold">{formatRupiah(session.total_omset)}</p>
                                        <p className="text-xs text-muted-foreground">{session.total_orders} transaksi</p>
                                        {session.total_cash !== undefined && (
                                            <p className="mt-1 text-xs text-muted-foreground">
                                                Tunai {formatRupiah(session.total_cash)} · QRIS{' '}
                                                {formatRupiah(session.total_qris ?? 0)} · Transfer{' '}
                                                {formatRupiah(session.total_transfer ?? 0)}
                                            </p>
                                        )}
                                        {session.variance !== undefined && session.variance !== 0 && (
                                            <p
                                                className={cn(
                                                    'mt-1 text-xs font-medium',
                                                    session.variance < 0
                                                        ? 'text-destructive'
                                                        : 'text-amber-600 dark:text-amber-400',
                                                )}
                                            >
                                                Selisih kas: {formatRupiah(session.variance)}
                                            </p>
                                        )}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </PosLayout>
    );
}
