import { PropsWithChildren, ReactNode, useEffect, useState } from 'react';
import { Link, usePage } from '@inertiajs/react';
import {
    LayoutDashboard,
    Boxes,
    ShoppingCart,
    Receipt,
    BookOpen,
    Calculator,
    FileSpreadsheet,
    Wallet,
    TrendingDown,
    Bot,
    LogOut,
    Menu,
    X,
} from 'lucide-react';
import { cn } from '@/lib/utils';
import type { PageProps } from '@/types';

interface NavItem {
    label: string;
    href: string;
    icon: ReactNode;
    ownerOnly?: boolean;
}

const navItems: NavItem[] = [
    { label: 'Dashboard', href: '/dashboard', icon: <LayoutDashboard className="h-4 w-4" /> },
    { label: 'Inventory', href: '/inventory', icon: <Boxes className="h-4 w-4" /> },
    { label: 'Pembelian', href: '/transactions', icon: <Receipt className="h-4 w-4" /> },
    { label: 'Penjualan', href: '/sales', icon: <ShoppingCart className="h-4 w-4" /> },
    { label: 'Produk & Resep', href: '/products', icon: <BookOpen className="h-4 w-4" />, ownerOnly: true },
    { label: 'Tax Simulator', href: '/tax', icon: <Calculator className="h-4 w-4" />, ownerOnly: true },
    { label: 'Laporan P&L', href: '/pnl', icon: <FileSpreadsheet className="h-4 w-4" />, ownerOnly: true },
    { label: 'Biaya', href: '/expenses', icon: <Wallet className="h-4 w-4" />, ownerOnly: true },
    { label: 'Margin Protection', href: '/margin', icon: <TrendingDown className="h-4 w-4" />, ownerOnly: true },
    { label: 'Bot Integration', href: '/settings/bot', icon: <Bot className="h-4 w-4" />, ownerOnly: true },
];

export default function AppLayout({ title, children }: PropsWithChildren<{ title?: string }>) {
    const { props, url } = usePage<PageProps>();
    const user = props.auth.user;
    const isOwner = user.role === 'owner';
    const [sidebarOpen, setSidebarOpen] = useState(false);
    const [toast, setToast] = useState<{ type: 'success' | 'error'; message: string } | null>(null);

    const flash = props.flash;
    useEffect(() => {
        if (flash?.success) setToast({ type: 'success', message: flash.success });
        else if (flash?.error) setToast({ type: 'error', message: flash.error });
    }, [flash]);

    useEffect(() => {
        if (!toast) return;
        const t = setTimeout(() => setToast(null), 4000);
        return () => clearTimeout(t);
    }, [toast]);

    const visibleNav = navItems.filter((item) => !item.ownerOnly || isOwner);

    return (
        <div className="min-h-screen bg-muted/30">
            {/* Sidebar */}
            <aside
                className={cn(
                    'fixed inset-y-0 left-0 z-40 w-64 transform border-r border-border bg-card transition-transform lg:translate-x-0',
                    sidebarOpen ? 'translate-x-0' : '-translate-x-full',
                )}
            >
                <div className="flex h-16 items-center gap-2 border-b border-border px-6">
                    <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-primary font-bold text-primary-foreground">
                        W
                    </div>
                    <span className="text-lg font-bold tracking-tight">Wol-ee</span>
                </div>
                <nav className="flex flex-col gap-1 p-3">
                    {visibleNav.map((item) => {
                        const active = url.startsWith(item.href);
                        return (
                            <Link
                                key={item.href}
                                href={item.href}
                                onClick={() => setSidebarOpen(false)}
                                className={cn(
                                    'flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors',
                                    active
                                        ? 'bg-primary/10 text-primary'
                                        : 'text-muted-foreground hover:bg-accent hover:text-foreground',
                                )}
                            >
                                {item.icon}
                                {item.label}
                            </Link>
                        );
                    })}
                </nav>
            </aside>

            {sidebarOpen && (
                <div className="fixed inset-0 z-30 bg-black/40 lg:hidden" onClick={() => setSidebarOpen(false)} />
            )}

            {/* Main */}
            <div className="lg:pl-64">
                <header className="sticky top-0 z-20 flex h-16 items-center justify-between border-b border-border bg-card/80 px-4 backdrop-blur sm:px-6">
                    <div className="flex items-center gap-3">
                        <button
                            className="rounded-md p-2 hover:bg-accent lg:hidden"
                            onClick={() => setSidebarOpen((v) => !v)}
                        >
                            {sidebarOpen ? <X className="h-5 w-5" /> : <Menu className="h-5 w-5" />}
                        </button>
                        <h1 className="text-lg font-semibold">{title}</h1>
                    </div>
                    <div className="flex items-center gap-3">
                        <div className="hidden text-right sm:block">
                            <p className="text-sm font-medium leading-tight">{user.name}</p>
                            <p className="text-xs capitalize text-muted-foreground">{user.role}</p>
                        </div>
                        <Link
                            href="/logout"
                            method="post"
                            as="button"
                            className="flex items-center gap-1.5 rounded-md px-2.5 py-1.5 text-sm text-muted-foreground hover:bg-accent hover:text-foreground"
                        >
                            <LogOut className="h-4 w-4" />
                            <span className="hidden sm:inline">Keluar</span>
                        </Link>
                    </div>
                </header>

                <main className="p-4 sm:p-6">{children}</main>
            </div>

            {toast && (
                <div
                    className={cn(
                        'fixed bottom-6 right-6 z-50 rounded-lg px-4 py-3 text-sm font-medium text-white shadow-lg',
                        toast.type === 'success' ? 'bg-success' : 'bg-destructive',
                    )}
                >
                    {toast.message}
                </div>
            )}
        </div>
    );
}
