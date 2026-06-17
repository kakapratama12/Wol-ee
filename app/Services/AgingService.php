<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Partner;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class AgingService
{
    /**
     * @return array{current: float, 1-2_months: float, 2-3_months: float, 3_plus: float}
     */
    public function emptyBuckets(): array
    {
        return [
            'current' => 0.0,
            '1-2_months' => 0.0,
            '2-3_months' => 0.0,
            '3_plus' => 0.0,
        ];
    }

    public function remainingAmount(Invoice $invoice): float
    {
        return max(0, round((float) $invoice->amount - (float) $invoice->paid_amount, 2));
    }

    public function bucketForDueDate(Carbon $dueDate): string
    {
        $daysOverdue = $dueDate->copy()->startOfDay()->diffInDays(now()->startOfDay(), false);

        if ($daysOverdue <= 30) {
            return 'current';
        }

        if ($daysOverdue <= 60) {
            return '1-2_months';
        }

        if ($daysOverdue <= 90) {
            return '2-3_months';
        }

        return '3_plus';
    }

    /**
     * @return array{current: float, 1-2_months: float, 2-3_months: float, 3_plus: float}
     */
    public function bucketsForInvoices(Collection $invoices): array
    {
        $buckets = $this->emptyBuckets();

        foreach ($invoices as $invoice) {
            if ($invoice->status === Invoice::STATUS_PAID) {
                continue;
            }

            $remaining = $this->remainingAmount($invoice);
            if ($remaining <= 0) {
                continue;
            }

            $bucket = $this->bucketForDueDate($invoice->due_date);
            $buckets[$bucket] = round($buckets[$bucket] + $remaining, 2);
        }

        return $buckets;
    }

    /**
     * @return array{current: float, 1-2_months: float, 2-3_months: float, 3_plus: float}
     */
    public function partnerAging(Partner $partner): array
    {
        return $this->bucketsForInvoices($partner->invoices()->get());
    }

    /**
     * @return array{
     *     summary: array{total_outstanding: float, total_partners: int},
     *     by_partner: list<array{partner_id: int, partner: string, total: float, current: float, 1-2_months: float, 2-3_months: float, 3_plus: float}>,
     *     by_aging: array{current: float, 1-2_months: float, 2-3_months: float, 3_plus: float}
     * }
     */
    public function report(): array
    {
        $partners = Partner::query()
            ->where('type', Partner::TYPE_CUSTOMER)
            ->with(['invoices' => fn ($q) => $q->where('status', '!=', Invoice::STATUS_PAID)])
            ->get();

        $byPartner = [];
        $byAging = $this->emptyBuckets();
        $totalOutstanding = 0.0;

        foreach ($partners as $partner) {
            $aging = $this->bucketsForInvoices($partner->invoices);
            $total = round(array_sum($aging), 2);

            if ($total <= 0) {
                continue;
            }

            $byPartner[] = [
                'partner_id' => $partner->id,
                'partner' => $partner->name,
                'total' => $total,
                ...$aging,
            ];

            foreach ($aging as $bucket => $amount) {
                $byAging[$bucket] = round($byAging[$bucket] + $amount, 2);
            }

            $totalOutstanding = round($totalOutstanding + $total, 2);
        }

        usort($byPartner, fn (array $a, array $b) => $b['total'] <=> $a['total']);

        return [
            'summary' => [
                'total_outstanding' => $totalOutstanding,
                'total_partners' => count($byPartner),
            ],
            'by_partner' => $byPartner,
            'by_aging' => $byAging,
        ];
    }
}
