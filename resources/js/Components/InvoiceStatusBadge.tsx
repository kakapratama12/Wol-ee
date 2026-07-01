import { Badge } from '@/Components/ui/badge';

const labels: Record<string, string> = {
    draft: 'Draft',
    outstanding: 'Outstanding',
    partial: 'Sebagian',
    paid: 'Lunas',
};

const variants: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
    draft: 'outline',
    outstanding: 'destructive',
    partial: 'secondary',
    paid: 'default',
};

export default function InvoiceStatusBadge({ status }: { status: string }) {
    return <Badge variant={variants[status] ?? 'outline'}>{labels[status] ?? status}</Badge>;
}
