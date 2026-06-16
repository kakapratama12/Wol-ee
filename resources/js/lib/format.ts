export function formatRupiah(value: number | string | null | undefined): string {
    const n = typeof value === 'string' ? Number(value) : (value ?? 0);
    if (!Number.isFinite(n)) return 'Rp 0';
    return 'Rp ' + Math.round(n).toLocaleString('id-ID');
}

export function formatPercent(value: number | string | null | undefined, digits = 1): string {
    const n = typeof value === 'string' ? Number(value) : (value ?? 0);
    if (!Number.isFinite(n)) return '0%';
    return `${n.toFixed(digits)}%`;
}

export function formatDate(value: string | null | undefined): string {
    if (!value) return '-';
    return new Date(value).toLocaleDateString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
}

export function formatNumber(value: number | string | null | undefined, digits = 0): string {
    const n = typeof value === 'string' ? Number(value) : (value ?? 0);
    if (!Number.isFinite(n)) return '0';
    return n.toLocaleString('id-ID', { maximumFractionDigits: digits });
}
