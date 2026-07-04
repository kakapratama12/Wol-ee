const styles: Record<string, string> = {
    outstanding: 'bg-yellow-100 text-yellow-800',
    partial: 'bg-blue-100 text-blue-800',
    paid: 'bg-green-100 text-green-800',
    draft: 'bg-gray-100 text-gray-600',
};

const labels: Record<string, string> = {
    outstanding: 'Belum Lunas',
    partial: 'Sebagian',
    paid: 'Lunas',
    draft: 'Draft',
};

export default function PayableStatusBadge({ status }: { status: string }) {
    return (
        <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${styles[status] || styles.draft}`}>
            {labels[status] || status}
        </span>
    );
}
