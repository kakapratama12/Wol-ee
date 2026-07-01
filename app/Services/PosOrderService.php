<?php

namespace App\Services;

use App\Exceptions\CartUnavailableException;
use App\Models\CashierSession;
use App\Models\PosOrder;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class PosOrderService
{
    public function __construct(
        private readonly ProductAvailabilityService $availability,
        private readonly SaleService $sales,
    ) {}

    /**
     * @param  list<array{product_id: int, quantity: int}>  $lineItems
     */
    public function checkout(
        CashierSession $session,
        User $user,
        array $lineItems,
        string $paymentMethod,
        float $amountPaid,
        ?string $note = null,
    ): PosOrder {
        if (! $session->isOpen()) {
            throw new InvalidArgumentException('Sesi kasir sudah ditutup.');
        }

        $this->availability->validateCart($lineItems);

        $products = Product::query()
            ->whereIn('id', collect($lineItems)->pluck('product_id'))
            ->get()
            ->keyBy('id');

        $total = 0.0;

        foreach ($lineItems as $item) {
            $product = $products->get($item['product_id']);

            if (! $product) {
                throw new InvalidArgumentException('Produk tidak ditemukan.');
            }

            $total += (float) $product->selling_price * (int) $item['quantity'];
        }

        $total = round($total, 2);
        $change = $paymentMethod === PosOrder::PAYMENT_TUNAI
            ? round(max(0, $amountPaid - $total), 2)
            : 0.0;

        if ($paymentMethod === PosOrder::PAYMENT_TUNAI && $amountPaid < $total) {
            throw new InvalidArgumentException('Nominal tunai kurang dari total.');
        }

        return DB::transaction(function () use ($session, $user, $lineItems, $paymentMethod, $amountPaid, $change, $total, $note, $products) {
            $order = PosOrder::create([
                'cashier_session_id' => $session->id,
                'branch_id' => $session->branch_id,
                'user_id' => $user->id,
                'total' => $total,
                'payment_method' => $paymentMethod,
                'amount_paid' => round($amountPaid, 2),
                'change_amount' => $change,
                'status' => PosOrder::STATUS_COMPLETED,
                'note' => $note,
            ]);

            $orderKey = Str::uuid()->toString();

            foreach ($lineItems as $item) {
                $product = $products->get($item['product_id']);

                $this->sales->record(
                    product: $product,
                    quantity: (int) $item['quantity'],
                    source: Sale::SOURCE_POS,
                    userId: $user->id,
                    note: $note,
                    occurredAt: Carbon::now(),
                    dispatchSaleRecorded: true,
                    idempotencyKey: $orderKey.'-'.$product->id,
                    posOrderId: $order->id,
                    branchId: $session->branch_id,
                );
            }

            $this->incrementSessionTotals($session, $paymentMethod, $total);

            return $order->load('sales.product');
        });
    }

    public function void(PosOrder $order, CashierSession $session): void
    {
        if ($order->cashier_session_id !== $session->id) {
            throw new InvalidArgumentException('Transaksi bukan dari sesi aktif.');
        }

        if (! $session->isOpen()) {
            throw new InvalidArgumentException('Sesi kasir sudah ditutup.');
        }

        if ($order->isVoid()) {
            throw new InvalidArgumentException('Transaksi sudah di-void.');
        }

        DB::transaction(function () use ($order, $session) {
            foreach ($order->sales()->active()->get() as $sale) {
                $this->sales->void($sale);
            }

            $order->update(['status' => PosOrder::STATUS_VOID]);

            $this->decrementSessionTotals($session, $order->payment_method, (float) $order->total);
        });
    }

    private function incrementSessionTotals(CashierSession $session, string $paymentMethod, float $total): void
    {
        $column = match ($paymentMethod) {
            PosOrder::PAYMENT_TUNAI => 'total_cash',
            PosOrder::PAYMENT_QRIS => 'total_qris',
            PosOrder::PAYMENT_TRANSFER => 'total_transfer',
            default => throw new InvalidArgumentException('Metode pembayaran tidak valid.'),
        };

        $session->increment($column, $total);
    }

    private function decrementSessionTotals(CashierSession $session, string $paymentMethod, float $total): void
    {
        $column = match ($paymentMethod) {
            PosOrder::PAYMENT_TUNAI => 'total_cash',
            PosOrder::PAYMENT_QRIS => 'total_qris',
            PosOrder::PAYMENT_TRANSFER => 'total_transfer',
            default => throw new InvalidArgumentException('Metode pembayaran tidak valid.'),
        };

        $session->decrement($column, $total);
    }
}
