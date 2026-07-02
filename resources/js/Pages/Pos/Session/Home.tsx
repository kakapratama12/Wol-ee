import { Head, Link } from '@inertiajs/react';
import PosLayout from '@/Layouts/PosLayout';
import { Button } from '@/Components/ui/button';
import { formatRupiah, formatDate } from '@/lib/format';
import { cn } from '@/lib/utils';

interface RecentSession {
    id: number;
    branch: string | null;
    opened_at: string | null;
    closed_at: string | null;
    opening_cash: number;
    total_cash: number;
    total_qris: number;
    total_transfer: number;
    total_omset: number;
    expected_cash: number;
    actual_cash: number;
    variance: number;
}

interface Props {
    branch?: string | null;
    recentSessions: RecentSession[];
}

export default function SessionHome({ branch, recentSessions }: Props) {
    return (
        <PosLayout title="Kasir" branch={branch}>
            <Head title="POS Kasir" />

            <div className="mx-auto max-w-lg space-y-6">
                <div className="rounded-xl border border-border bg-card p-6 text-center shadow-sm">
                    <h2 className="text-xl font-semibold">Belum ada sesi aktif</h2>
                    <p className="mt-2 text-sm text-muted-foreground">
                        Buka sesi baru untuk mulai melayani pelanggan.
                    </p>
                    <Link href="/pos/session/open" className="mt-4 inline-block w-full">
                        <Button className="h-12 w-full text-base">Buka Sesi Baru</Button>
                    </Link>
                </div>

                {recentSessions.length > 0 && (
                    <div className="rounded-xl border border-border bg-card p-4">
                        <h3 className="mb-3 font-semibold">Sesi sebelumnya</h3>
                        <ul className="space-y-3 text-sm">
                            {recentSessions.map((s) => (
                                <li
                                    key={s.id}
                                    className="border-b border-border pb-3 last:border-0 last:pb-0"
                                >
                                    <div className="flex items-start justify-between gap-2">
                                        <div>
                                            <p className="font-medium">{s.branch ?? 'Cabang'}</p>
                                            <p className="text-xs text-muted-foreground">
                                                {s.closed_at ? formatDate(s.closed_at) : '—'}
                                            </p>
                                        </div>
                                        <p className="font-medium">{formatRupiah(s.total_omset)}</p>
                                    </div>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        Tunai {formatRupiah(s.total_cash)} · QRIS{' '}
                                        {formatRupiah(s.total_qris)} · Transfer{' '}
                                        {formatRupiah(s.total_transfer)}
                                    </p>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        Kas laci seharusnya: {formatRupiah(s.expected_cash)}
                                    </p>
                                    {s.variance !== 0 && (
                                        <p
                                            className={cn(
                                                'mt-1 text-xs font-medium',
                                                s.variance < 0
                                                    ? 'text-destructive'
                                                    : 'text-amber-600 dark:text-amber-400',
                                            )}
                                        >
                                            Selisih kas: {formatRupiah(s.variance)}
                                        </p>
                                    )}
                                </li>
                            ))}
                        </ul>
                    </div>
                )}
            </div>
        </PosLayout>
    );
}
