<?php

namespace App\Services;

use App\Models\Partner;
use App\Models\Payable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PayableService
{
    public function __construct(private readonly AgingService $aging) {}

    public function resolveStatus(float $amount, float $paidAmount): string
    {
        if ($paidAmount <= 0) {
            return Payable::STATUS_OUTSTANDING;
        }

        if ($paidAmount >= $amount) {
            return Payable::STATUS_PAID;
        }

        return Payable::STATUS_PARTIAL;
    }

    public function remainingAmount(Payable $payable): float
    {
        return $this->aging->remainingPayableAmount($payable);
    }

    /**
     * @param  array{partner_id: int, due_date: string, note?: ?string, items?: array}  $data
     */
    public function create(array $data): Payable
    {
        $partner = Partner::findOrFail($data['partner_id']);

        if ($partner->type !== Partner::TYPE_SUPPLIER) {
            throw new InvalidArgumentException('Payable hanya untuk partner tipe supplier.');
        }

        $tenantId = $partner->tenant_id;
        $items = $data['items'] ?? [];

        // Calculate amount from line items
        $amount = count($items) > 0
            ? round(array_sum(array_map(fn ($item) => (float) $item['quantity'] * (float) $item['unit_price'], $items)), 2)
            : 0;

        return DB::transaction(function () use ($data, $amount, $tenantId, $partner, $items) {
            $attempts = 0;

            while ($attempts < 3) {
                try {
                    $payable = Payable::create([
                        'tenant_id' => $tenantId,
                        'partner_id' => $partner->id,
                        'payable_number' => $this->generatePayableNumber($tenantId),
                        'amount' => $amount,
                        'paid_amount' => 0,
                        'due_date' => $data['due_date'] ?? null,
                        'status' => $data['status'] ?? Payable::STATUS_OUTSTANDING,
                        'note' => $data['note'] ?? null,
                    ]);

                    // Create line items if provided
                    foreach ($items as $item) {
                        $itemTotal = round((float) $item['quantity'] * (float) $item['unit_price'], 2);
                        $payable->items()->create([
                            'description' => $item['description'],
                            'quantity' => $item['quantity'],
                            'unit_price' => $item['unit_price'],
                            'total' => $itemTotal,
                        ]);
                    }

                    return $payable;
                } catch (QueryException $e) {
                    if (! $this->isUniqueViolation($e)) {
                        throw $e;
                    }
                    $attempts++;
                }
            }

            throw new InvalidArgumentException('Gagal membuat nomor payable unik.');
        });
    }

    /**
     * @param  array{amount: float|int|string, paid_at?: ?string}  $data
     * @return array{payable: Payable, remaining: float}
     */
    public function recordPayment(Payable $payable, array $data): array
    {
        $payment = round((float) $data['amount'], 2);
        $remaining = $this->remainingAmount($payable);

        if ($payment <= 0) {
            throw new InvalidArgumentException('Jumlah pembayaran harus lebih dari 0.');
        }

        if ($payment > $remaining) {
            throw new InvalidArgumentException('Melebihi tagihan.');
        }

        return DB::transaction(function () use ($payable, $payment, $data) {
            $payable = Payable::query()->lockForUpdate()->findOrFail($payable->id);

            $newPaid = round((float) $payable->paid_amount + $payment, 2);
            $amount = (float) $payable->amount;
            $status = $this->resolveStatus($amount, $newPaid);
            $paidAt = $status === Payable::STATUS_PAID
                ? ($data['paid_at'] ?? now())
                : $payable->paid_at;

            if (is_string($paidAt)) {
                $paidAt = Carbon::parse($paidAt);
            }

            $payable->update([
                'paid_amount' => $newPaid,
                'status' => $status,
                'paid_at' => $status === Payable::STATUS_PAID ? $paidAt : $payable->paid_at,
            ]);

            return [
                'payable' => $payable->fresh(['partner']),
                'remaining' => $this->remainingAmount($payable->fresh()),
            ];
        });
    }

    public function generatePayableNumber(int $tenantId): string
    {
        $prefix = 'PAY-'.now()->format('Ym').'-';

        $lastNumber = Payable::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where('payable_number', 'like', $prefix.'%')
            ->orderByDesc('payable_number')
            ->value('payable_number');

        $sequence = $lastNumber ? ((int) substr($lastNumber, -3)) + 1 : 1;

        return $prefix.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        $code = $e->errorInfo[0] ?? null;

        return in_array($code, ['23000', '23505'], true);
    }
}
