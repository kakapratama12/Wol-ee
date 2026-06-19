<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Partner;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class InvoiceService
{
    public function __construct(private readonly AgingService $aging) {}

    public function resolveStatus(float $amount, float $paidAmount): string
    {
        if ($paidAmount <= 0) {
            return Invoice::STATUS_OUTSTANDING;
        }

        if ($paidAmount >= $amount) {
            return Invoice::STATUS_PAID;
        }

        return Invoice::STATUS_PARTIAL;
    }

    public function remainingAmount(Invoice $invoice): float
    {
        return $this->aging->remainingAmount($invoice);
    }

    /**
     * @param  array{partner_id: int, amount: float|int|string, due_date: string, note?: ?string}  $data
     */
    public function create(array $data): Invoice
    {
        $partner = Partner::findOrFail($data['partner_id']);

        if ($partner->type !== Partner::TYPE_CUSTOMER) {
            throw new InvalidArgumentException('Invoice hanya untuk partner tipe customer.');
        }

        $amount = round((float) $data['amount'], 2);
        $tenantId = $partner->tenant_id;

        return DB::transaction(function () use ($data, $amount, $tenantId, $partner) {
            $attempts = 0;

            while ($attempts < 3) {
                try {
                    return Invoice::create([
                        'tenant_id' => $tenantId,
                        'partner_id' => $partner->id,
                        'invoice_number' => $this->generateInvoiceNumber($tenantId),
                        'amount' => $amount,
                        'paid_amount' => 0,
                        'due_date' => $data['due_date'],
                        'status' => Invoice::STATUS_OUTSTANDING,
                        'note' => $data['note'] ?? null,
                    ]);
                } catch (QueryException $e) {
                    if (! $this->isUniqueViolation($e)) {
                        throw $e;
                    }
                    $attempts++;
                }
            }

            throw new InvalidArgumentException('Gagal membuat nomor invoice unik.');
        });
    }

    /**
     * @param  array{amount: float|int|string, paid_at?: ?string}  $data
     * @return array{invoice: Invoice, remaining: float}
     */
    public function recordPayment(Invoice $invoice, array $data): array
    {
        $payment = round((float) $data['amount'], 2);
        $remaining = $this->remainingAmount($invoice);

        if ($payment <= 0) {
            throw new InvalidArgumentException('Jumlah pembayaran harus lebih dari 0.');
        }

        if ($payment > $remaining) {
            throw new InvalidArgumentException('Melebihi tagihan.');
        }

        return DB::transaction(function () use ($invoice, $payment, $data) {
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);

            $newPaid = round((float) $invoice->paid_amount + $payment, 2);
            $amount = (float) $invoice->amount;
            $status = $this->resolveStatus($amount, $newPaid);
            $paidAt = $status === Invoice::STATUS_PAID
                ? ($data['paid_at'] ?? now())
                : $invoice->paid_at;

            if (is_string($paidAt)) {
                $paidAt = Carbon::parse($paidAt);
            }

            $invoice->update([
                'paid_amount' => $newPaid,
                'status' => $status,
                'paid_at' => $status === Invoice::STATUS_PAID ? $paidAt : $invoice->paid_at,
            ]);

            return [
                'invoice' => $invoice->fresh(['partner']),
                'remaining' => $this->remainingAmount($invoice->fresh()),
            ];
        });
    }

    public function generateInvoiceNumber(int $tenantId): string
    {
        $prefix = 'INV-'.now()->format('Ym').'-';

        $lastNumber = Invoice::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where('invoice_number', 'like', $prefix.'%')
            ->orderByDesc('invoice_number')
            ->value('invoice_number');

        $sequence = $lastNumber ? ((int) substr($lastNumber, -3)) + 1 : 1;

        return $prefix.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        $code = $e->errorInfo[0] ?? null;

        return in_array($code, ['23000', '23505'], true);
    }
}
