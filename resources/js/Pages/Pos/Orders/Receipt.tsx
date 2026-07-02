import { Head, Link } from '@inertiajs/react';
import { formatRupiah, formatDate } from '@/lib/format';

interface SaleLine {
    product: string | null;
    quantity: number;
    unit_price: number;
    revenue: number;
}

interface OrderInfo {
    id: number;
    total: number;
    payment_method: string;
    amount_paid: number;
    change_amount: number;
    outlet?: string | null;
    cashier?: string | null;
    created_at?: string | null;
    sales: SaleLine[];
}

interface Props {
    order: OrderInfo;
}

const paymentLabels: Record<string, string> = {
    tunai: 'Tunai',
    qris: 'QRIS',
    transfer: 'Transfer',
};

export default function OrderReceipt({ order }: Props) {
    return (
        <>
            <Head title={`Struk #${order.id}`} />
            <style>{`
                @media print {
                    .no-print { display: none !important; }
                    body { margin: 0; }
                }
            `}</style>

            <div className="mx-auto max-w-sm p-4 font-mono text-sm text-black">
                <div className="no-print mb-4 flex gap-2">
                    <button
                        type="button"
                        onClick={() => window.print()}
                        className="rounded border px-3 py-2 text-sm"
                    >
                        Cetak
                    </button>
                    <Link href={`/pos/orders/${order.id}/success`} className="rounded border px-3 py-2 text-sm">
                        Kembali
                    </Link>
                </div>

                <div className="text-center">
                    <p className="text-base font-bold">Wol-ee POS</p>
                    {order.outlet && <p>{order.outlet}</p>}
                    <p className="text-xs text-gray-600">
                        {order.created_at ? formatDate(order.created_at) : ''}
                    </p>
                    <p className="text-xs">Order #{order.id}</p>
                    {order.cashier && <p className="text-xs">Kasir: {order.cashier}</p>}
                </div>

                <hr className="my-3 border-dashed border-gray-400" />

                <div className="space-y-1">
                    {order.sales.map((line, i) => (
                        <div key={i}>
                            <div className="flex justify-between gap-2">
                                <span>{line.product}</span>
                                <span>{formatRupiah(line.revenue)}</span>
                            </div>
                            <div className="text-xs text-gray-600">
                                {line.quantity} × {formatRupiah(line.unit_price)}
                            </div>
                        </div>
                    ))}
                </div>

                <hr className="my-3 border-dashed border-gray-400" />

                <div className="space-y-1">
                    <div className="flex justify-between font-bold">
                        <span>TOTAL</span>
                        <span>{formatRupiah(order.total)}</span>
                    </div>
                    <div className="flex justify-between text-xs">
                        <span>Bayar ({paymentLabels[order.payment_method] ?? order.payment_method})</span>
                        <span>{formatRupiah(order.amount_paid)}</span>
                    </div>
                    {order.payment_method === 'tunai' && order.change_amount > 0 && (
                        <div className="flex justify-between text-xs">
                            <span>Kembali</span>
                            <span>{formatRupiah(order.change_amount)}</span>
                        </div>
                    )}
                </div>

                <p className="mt-6 text-center text-xs text-gray-500">Terima kasih</p>
            </div>
        </>
    );
}
