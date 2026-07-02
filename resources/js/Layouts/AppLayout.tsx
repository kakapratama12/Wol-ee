import { PropsWithChildren, ReactNode, useEffect, useMemo, useRef, useState } from 'react';
import { Link, usePage } from '@inertiajs/react';
import {
    LayoutDashboard,
    Receipt,
    Boxes,
    FileSpreadsheet,
    Users,
    Settings,
    Shield,
    LogOut,
    Menu,
    X,
    ChevronDown,
    Utensils,
    User,
    Truck,
    Building2,
} from 'lucide-react';
import { cn } from '@/lib/utils';
import { ThemeProvider, useTheme } from '@/Components/ThemeProvider';
import ThemeToggle from '@/Components/ThemeToggle';
import type { PageProps } from '@/types';

interface NavSingle {
    label: string;
    href: string;
    icon: ReactNode;
    pengelolaOnly?: boolean;
    staffOnly?: boolean;
    superAdminOnly?: boolean;
    multiOutletOnly?: boolean;
}

interface NavLink {
    label: string;
    href: string;
    pengelolaOnly?: boolean;
    staffOnly?: boolean;
    superAdminOnly?: boolean;
    multiOutletOnly?: boolean;
    hideIfNoInvoices?: boolean;
}

interface NavGroup {
    label: string;
    icon: ReactNode;
    pengelolaOnly?: boolean;
    staffOnly?: boolean;
    superAdminOnly?: boolean;
    multiOutletOnly?: boolean;
    children: NavLink[];
}

const navigation: (NavSingle | NavGroup)[] = [
    { label: 'Dashboard', href: '/dashboard', icon: <LayoutDashboard className="h-4 w-4" /> },
    {
        label: 'Transaksi',
        icon: <Receipt className="h-4 w-4" />,
        children: [
            { label: 'Pembelian', href: '/transactions' },
            { label: 'Penjualan', href: '/sales', pengelolaOnly: true },
            { label: 'Biaya', href: '/expenses', pengelolaOnly: true },
        ],
    },
    { label: 'POS', href: '/pos', icon: <Receipt className="h-4 w-4" />, staffOnly: true },
    {
        label: 'Distribusi',
        href: '/distributions',
        icon: <Truck className="h-4 w-4" />,
        pengelolaOnly: true,
        multiOutletOnly: true,
    },
    {
        label: 'Outlet',
        href: '/outlets',
        icon: <Building2 className="h-4 w-4" />,
        pengelolaOnly: true,
        multiOutletOnly: true,
    },
    {
        label: 'Inventory',
        icon: <Boxes className="h-4 w-4" />,
        children: [
            { label: 'Stok Bahan Dasar', href: '/inventory?type=raw_material' },
            { label: 'Stok Prep', href: '/prep-stocks' },
            { label: 'Stok Produk Jadi', href: '/finished-goods', pengelolaOnly: true },
        ],
    },
    {
        label: 'Produk',
        icon: <Utensils className="h-4 w-4" />,
        pengelolaOnly: true,
        children: [
            { label: 'Produk & Resep', href: '/products' },
            { label: 'Produksi', href: '/production-runs' },
        ],
    },
    {
        label: 'Laporan',
        icon: <FileSpreadsheet className="h-4 w-4" />,
        pengelolaOnly: true,
        children: [
            { label: 'Laporan P&L', href: '/pnl' },
            { label: 'Laporan Cashflow', href: '/reports/cashflow' },
            { label: 'Tax Simulator', href: '/tax' },
            { label: 'Margin Protection', href: '/margin' },
            { label: 'Aging Report', href: '/reports/aging', hideIfNoInvoices: true },
        ],
    },
    {
        label: 'Partner',
        icon: <Users className="h-4 w-4" />,
        children: [
            { label: 'Daftar Partner', href: '/partners' },
            { label: 'Invoices', href: '/invoices' },
            { label: 'Tagihan Supplier', href: '/payables' },
        ],
    },
    {
        label: 'Settings',
        icon: <Settings className="h-4 w-4" />,
        pengelolaOnly: true,
        children: [
            { label: 'Bot Integration', href: '/settings/bot' },
            { label: 'Perusahaan', href: '/settings/company' },
        ],
    },
    {
        label: 'Platform',
        icon: <Shield className="h-4 w-4" />,
        superAdminOnly: true,
        children: [
            { label: 'Overview', href: '/platform' },
            { label: 'Usaha', href: '/platform/tenants' },
            { label: 'Feedback', href: '/platform/feedback' },
            { label: 'AI Usage', href: '/platform/ai-usage' },
            { label: 'Bot Skills', href: '/platform/bot-skills' },
            { label: 'Users', href: '/platform/users' },
        ],
    },
];

function isNavGroup(item: NavSingle | NavGroup): item is NavGroup {
    return 'children' in item;
}

function isActive(url: string, href: string): boolean {
    return url === href || url.startsWith(`${href}/`);
}

function groupHasActiveChild(url: string, children: NavLink[]): boolean {
    return children.some((child) => isActive(url, child.href));
}

function AppLayoutInner({ title, children }: PropsWithChildren<{ title?: string }>) {
    const { theme } = useTheme();
    const { props, url } = usePage<PageProps>();
    const user = props.auth.user;
    const isPengelola = user.role === 'pengelola';
    const isSuperAdmin = user.role === 'super_admin';
    const businessType = props.auth.businessType ?? 'single';
    const isMultiOutlet = businessType === 'multi';
    const hasInvoices = props.hasInvoices ?? false;
    const [sidebarOpen, setSidebarOpen] = useState(false);
    const [toast, setToast] = useState<{ type: 'success' | 'error'; message: string } | null>(null);
    const [openGroups, setOpenGroups] = useState<Record<string, boolean>>({});
    const [userMenuOpen, setUserMenuOpen] = useState(false);
    const userMenuRef = useRef<HTMLDivElement>(null);

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

    // Close user menu on click outside
    useEffect(() => {
        if (!userMenuOpen) return;
        const handler = (e: MouseEvent) => {
            if (userMenuRef.current && !userMenuRef.current.contains(e.target as Node)) {
                setUserMenuOpen(false);
            }
        };
        document.addEventListener('mousedown', handler);
        return () => document.removeEventListener('mousedown', handler);
    }, [userMenuOpen]);

    const visibleNav = useMemo(() => {
        const isStaff = user.role === 'staff';

        return navigation
            .map((item) => {
                if (!isNavGroup(item)) {
                    if ('staffOnly' in item && item.staffOnly && !isStaff) return null;
                    if ('pengelolaOnly' in item && item.pengelolaOnly && !isPengelola) return null;
                    if ('superAdminOnly' in item && item.superAdminOnly && !isSuperAdmin)
                        return null;
                    if ('multiOutletOnly' in item && item.multiOutletOnly && !isMultiOutlet) return null;
                    if (isSuperAdmin && !item.superAdminOnly) return null;
                    if (isStaff && !item.staffOnly) return null;
                    return item;
                }

                if (item.staffOnly && !isStaff) return null;
                if (item.pengelolaOnly && !isPengelola) return null;
                if (item.superAdminOnly && !isSuperAdmin) return null;
                if (item.multiOutletOnly && !isMultiOutlet) return null;
                if (isSuperAdmin && !item.superAdminOnly) return null;
                if (isStaff && !item.staffOnly) return null;

                const children = item.children.filter((child) => {
                    if (child.staffOnly && !isStaff) return false;
                    if (child.pengelolaOnly && !isPengelola) return false;
                    if (child.superAdminOnly && !isSuperAdmin) return false;
                    if (child.multiOutletOnly && !isMultiOutlet) return false;
                    if (child.hideIfNoInvoices && !hasInvoices) return false;
                    if (isStaff && !child.staffOnly) return false;
                    return true;
                });

                if (children.length === 0) return null;

                return { ...item, children };
            })
            .filter(Boolean) as (NavSingle | NavGroup)[];
    }, [isPengelola, isSuperAdmin, isMultiOutlet, hasInvoices, user.role]);

    useEffect(() => {
        const initial: Record<string, boolean> = {};
        visibleNav.forEach((item) => {
            if (isNavGroup(item) && groupHasActiveChild(url, item.children)) {
                initial[item.label] = true;
            }
        });
        setOpenGroups((prev) => ({ ...prev, ...initial }));
    }, [url, visibleNav]);

    const toggleGroup = (label: string) => {
        setOpenGroups((prev) => ({ ...prev, [label]: !prev[label] }));
    };

    const renderNav = () => (
        <nav className="flex flex-col gap-1 p-3">
            {visibleNav.map((item) => {
                if (!isNavGroup(item)) {
                    const active = isActive(url, item.href);
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
                }

                const isOpen = openGroups[item.label] ?? false;
                const groupActive = groupHasActiveChild(url, item.children);

                return (
                    <div key={item.label}>
                        <button
                            type="button"
                            onClick={() => toggleGroup(item.label)}
                            className={cn(
                                'flex w-full items-center justify-between rounded-lg px-3 py-2 text-sm font-medium transition-colors',
                                groupActive
                                    ? 'text-primary'
                                    : 'text-muted-foreground hover:bg-accent hover:text-foreground',
                            )}
                        >
                            <span className="flex items-center gap-3">
                                {item.icon}
                                {item.label}
                            </span>
                            <ChevronDown
                                className={cn(
                                    'h-4 w-4 transition-transform',
                                    isOpen && 'rotate-180',
                                )}
                            />
                        </button>
                        {isOpen && (
                            <div className="ml-4 mt-1 flex flex-col gap-0.5 border-l border-border pl-3">
                                {item.children.map((child) => {
                                    const active = isActive(url, child.href);
                                    return (
                                        <Link
                                            key={child.href}
                                            href={child.href}
                                            onClick={() => setSidebarOpen(false)}
                                            className={cn(
                                                'rounded-lg px-3 py-1.5 text-sm transition-colors',
                                                active
                                                    ? 'bg-primary/10 font-medium text-primary'
                                                    : 'text-muted-foreground hover:bg-accent hover:text-foreground',
                                            )}
                                        >
                                            {child.label}
                                        </Link>
                                    );
                                })}
                            </div>
                        )}
                    </div>
                );
            })}
        </nav>
    );

    return (
        <div className="min-h-screen bg-muted/30">
            <aside
                className={cn(
                    'fixed inset-y-0 left-0 z-40 w-64 transform border-r border-border bg-card transition-transform lg:translate-x-0',
                    sidebarOpen ? 'translate-x-0' : '-translate-x-full',
                )}
            >
                <div className="flex h-16 items-center border-b border-border px-6">
                    <img
                        src={theme === 'dark' ? '/logo-white.png' : '/logo.png'}
                        alt="Wol-ee"
                        className="h-10 w-auto"
                    />
                </div>
                {renderNav()}
            </aside>

            {sidebarOpen && (
                <div
                    className="fixed inset-0 z-30 bg-black/40 lg:hidden"
                    onClick={() => setSidebarOpen(false)}
                />
            )}

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
                    <div className="flex items-center gap-2">
                        <ThemeToggle />
                        <div ref={userMenuRef} className="relative">
                            <button
                                type="button"
                                onClick={() => setUserMenuOpen(!userMenuOpen)}
                                className="flex items-center gap-2 rounded-md px-2 py-1.5 text-sm hover:bg-accent"
                            >
                                <div className="hidden text-right sm:block">
                                    <p className="text-sm font-medium leading-tight">{user.name}</p>
                                    <p className="text-xs capitalize text-muted-foreground">{user.role}</p>
                                </div>
                                <ChevronDown className={cn('h-4 w-4 text-muted-foreground transition-transform', userMenuOpen && 'rotate-180')} />
                            </button>

                            {userMenuOpen && (
                                <div className="absolute right-0 z-50 mt-1 w-44 rounded-lg border border-border bg-card py-1 shadow-lg">
                                    <Link
                                        href="/profile"
                                        onClick={() => setUserMenuOpen(false)}
                                        className="flex items-center gap-2 px-4 py-2 text-sm text-foreground hover:bg-accent"
                                    >
                                        <User className="h-4 w-4" />
                                        Ganti Password
                                    </Link>
                                    <Link
                                        href="/logout"
                                        method="post"
                                        as="button"
                                        onClick={() => setUserMenuOpen(false)}
                                        className="flex w-full items-center gap-2 px-4 py-2 text-sm text-foreground hover:bg-accent"
                                    >
                                        <LogOut className="h-4 w-4" />
                                        Keluar
                                    </Link>
                                </div>
                            )}
                        </div>
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

export default function AppLayout(props: PropsWithChildren<{ title?: string }>) {
    return (
        <ThemeProvider>
            <AppLayoutInner {...props} />
        </ThemeProvider>
    );
}