import { PropsWithChildren } from 'react';
import { Link, usePage } from '@inertiajs/react';
import { LogOut, ShoppingCart, Package, BarChart3 } from 'lucide-react';
import type { PageProps } from '@/types';
import { cn } from '@/lib/utils';
import { ThemeProvider } from '@/Components/ThemeProvider';
import ThemeToggle from '@/Components/ThemeToggle';

interface PosLayoutProps {
    title?: string;
    branch?: string | null;
    actions?: React.ReactNode;
    activeTab?: string;
}

export default function PosLayout({
    title = 'Kasir',
    branch,
    actions,
    activeTab,
    children,
}: PropsWithChildren<PosLayoutProps>) {
    const { auth } = usePage<PageProps>().props;

    const navItems = [
        { href: '/pos/register', icon: ShoppingCart, label: 'Kasir' },
        { href: '/pos/stock', icon: Package, label: 'Stok' },
        { href: '/pos/today', icon: BarChart3, label: 'Hari Ini' },
    ];

    return (
        <ThemeProvider>
        <div className="flex min-h-screen flex-col bg-background text-foreground">
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
                        <ThemeToggle />
                        <Link
                            href="/pos/logout"
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

            <main className="mx-auto w-full max-w-7xl flex-1 p-4 pb-24">{children}</main>

            {/* Bottom Navigation */}
            <nav className="fixed bottom-0 inset-x-0 z-40 border-t border-border bg-card shadow-lg">
                <div className="mx-auto flex max-w-7xl items-center justify-around py-2">
                    {navItems.map((item) => {
                        const Icon = item.icon;
                        const isActive = activeTab ? item.label.toLowerCase().includes(activeTab) : (typeof window !== 'undefined' && window.location.pathname.startsWith(item.href));
                        return (
                            <Link
                                key={item.href}
                                href={item.href}
                                className={cn(
                                    'flex flex-col items-center gap-0.5 px-4 py-1 text-xs transition',
                                    isActive
                                        ? 'text-primary font-semibold'
                                        : 'text-muted-foreground hover:text-foreground',
                                )}
                            >
                                <Icon className="h-5 w-5" />
                                <span>{item.label}</span>
                            </Link>
                        );
                    })}
                </div>
            </nav>
        </div>
        </ThemeProvider>
    );
}
