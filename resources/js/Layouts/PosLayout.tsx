import { PropsWithChildren } from 'react';
import { Link, usePage } from '@inertiajs/react';
import { LogOut } from 'lucide-react';
import type { PageProps } from '@/types';

interface PosLayoutProps {
    title?: string;
    branch?: string | null;
    actions?: React.ReactNode;
}

export default function PosLayout({
    title = 'Kasir',
    branch,
    actions,
    children,
}: PropsWithChildren<PosLayoutProps>) {
    const { auth } = usePage<PageProps>().props;

    return (
        <div className="min-h-screen bg-background text-foreground">
            <header className="sticky top-0 z-30 border-b border-border bg-card px-4 py-3 shadow-sm">
                <div className="mx-auto flex max-w-7xl items-center justify-between gap-3">
                    <div>
                        <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                            Wol-ee POS
                        </p>
                        <h1 className="text-lg font-semibold leading-tight">{title}</h1>
                        {(branch || auth.user?.name) && (
                            <p className="text-xs text-muted-foreground">
                                {[branch, auth.user?.name].filter(Boolean).join(' · ')}
                            </p>
                        )}
                    </div>
                    <div className="flex items-center gap-2">
                        {actions}
                        <Link
                            href="/logout"
                            method="post"
                            as="button"
                            className="inline-flex h-10 w-10 items-center justify-center rounded-md border border-border text-muted-foreground hover:bg-accent"
                            title="Keluar"
                        >
                            <LogOut className="h-4 w-4" />
                        </Link>
                    </div>
                </div>
            </header>
            <main className="mx-auto max-w-7xl p-4">{children}</main>
        </div>
    );
}
