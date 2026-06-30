import { Link } from '@inertiajs/react';
import { PropsWithChildren } from 'react';
import { ThemeProvider, useTheme } from '@/Components/ThemeProvider';
import ThemeToggle from '@/Components/ThemeToggle';

function GuestInner({ children }: PropsWithChildren) {
    const { theme } = useTheme();
    return (
        <div className="flex min-h-screen flex-col items-center bg-muted/30 pt-6 sm:justify-center sm:pt-0">
            <div className="fixed top-4 right-4">
                <ThemeToggle />
            </div>
            <div>
                <Link href="/">
                    <img src={theme === "dark" ? "/logo-white.png" : "/logo.png"} alt="Wol-ee" className="h-16 w-auto" />
                </Link>
            </div>

            <div className="mt-6 w-full overflow-hidden bg-card px-6 py-4 shadow-md sm:max-w-md sm:rounded-lg">
                {children}
            </div>
        </div>
    );
}

export default function Guest(props: PropsWithChildren) {
    return (
        <ThemeProvider>
            <GuestInner {...props} />
        </ThemeProvider>
    );
}
