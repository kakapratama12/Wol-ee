import { Badge } from '@/Components/ui/badge';

const map: Record<string, { variant: 'success' | 'warning' | 'danger'; label: string }> = {
    aman: { variant: 'success', label: 'Aman' },
    menipis: { variant: 'warning', label: 'Menipis' },
    kritis: { variant: 'danger', label: 'Kritis' },
};

export default function StockStatusBadge({ status }: { status: string }) {
    const cfg = map[status] ?? map.aman;
    return <Badge variant={cfg.variant}>{cfg.label}</Badge>;
}
