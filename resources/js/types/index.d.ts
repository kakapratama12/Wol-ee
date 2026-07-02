export type UserRole = 'pengelola' | 'staff' | 'super_admin';

export interface User {
    id: number;
    name: string;
    email: string;
    role: UserRole;
    email_verified_at?: string;
}

export interface FlashMessages {
    success?: string | null;
    error?: string | null;
}

export type PageProps<T extends Record<string, unknown> = Record<string, unknown>> = T & {
    auth: {
        user: User;
        businessType: 'single' | 'multi';
    };
    flash: FlashMessages;
    hasInvoices: boolean;
};

export interface Paginated<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    current_page: number;
    last_page: number;
    total: number;
}
