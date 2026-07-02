import { PropsWithChildren } from 'react';
import { Link, usePage } from '@inertiajs/react';
import { ShoppingCart, Package, BarChart3, LogOut } from 'lucide-react';
import type { PageProps } from '@/types';
import { cn } from '@/lib/utils';

interface PosLayoutProps {
    title?: string;
    branch?: string | null;
    actions?: React.ReactNode;
    activeTab?: 'kasir' | 'stok' | 'hari-ini';
}

const navItems = [
    { key: 'kasir', label: 'Kasir', icon: ShoppingCart, href: '/pos/register' },
    { key: 'stok', label: 'Stok', icon: Package, href: '/pos/stock' },
    { key: 'hari-ini', label: 'Hari Ini', icon: BarChart3, href: '/pos/summary' },
] as const;

export default function PosLayout({
    title = 'Kasir',
    branch,
    actions,
    activeTab,
    children,
}: PropsWithChildren<PosLayoutProps>) {
    const { auth } = usePage<PageProps>().props;

    return (
        <div className="flex min-h-screen flex-col bg-background text-foreground">
            {/* Header */}
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
                    </div>
                </div>
            </header>

            {/* Main content */}
            <main className="flex-1 overflow-y-auto pb-20">
                <div className="mx-auto max-w-7xl p-4">{children}</div>
            </main>

            {/* Bottom Navigation */}
            <nav className="fixed bottom-0 left-0 right-0 z-40 border-t border-border bg-card shadow-lg">
                <div className="mx-auto flex max-w-lg items-center justify-around py-2">
                    {navItems.map((item) => {
                        const Icon = item.icon;
                        const isActive = activeTab === item.key;
                        return (
                            <Link
                                key={item.key}
                                href={item.href}
                                className={cn(
                                    'flex flex-col items-center gap-1 px-4 py-1 text-xs font-medium transition',
                                    isActive
                                        ? 'text-primary'
                                        : 'text-muted-foreground hover:text-foreground',
                                )}
                            >
                                <Icon className="h-5 w-5" />
                                <span>{item.label}</span>
                            </Link>
                        );
                    })}
                    <Link
                        href="/pos/logout"
                        method="post"
                        as="button"
                        className="flex flex-col items-center gap-1 px-4 py-1 text-xs font-medium text-muted-foreground hover:text-destructive"
                    >
                        <LogOut className="h-5 w-5" />
                        <span>Keluar</span>
                    </Link>
                </div>
            </nav>
        </div>
    );
}
