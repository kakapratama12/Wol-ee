<?php

namespace App\Services;

class TaxSimulatorService
{
    public const TYPE_PERORANGAN = 'perorangan';
    public const TYPE_CV = 'cv';
    public const TYPE_PT = 'pt';

    /** Tarif final PP 23/2018. */
    private const PP23_RATE = 0.005;

    /** Tarif badan (CV/PT). */
    private const BADAN_RATE = 0.22;

    /**
     * Lapisan PPh 21 progresif (UU HPP) untuk wajib pajak orang pribadi.
     *
     * @var array<int, array{limit: float|null, rate: float}>
     */
    private const PPH21_BRACKETS = [
        ['limit' => 60_000_000, 'rate' => 0.05],
        ['limit' => 250_000_000, 'rate' => 0.15],
        ['limit' => 500_000_000, 'rate' => 0.25],
        ['limit' => 5_000_000_000, 'rate' => 0.30],
        ['limit' => null, 'rate' => 0.35],
    ];

    /**
     * @return array<string, mixed>
     */
    public function simulate(
        string $businessType,
        float $omset,
        float $cogs,
        float $expense,
        float $wastePercent = 0.0,
    ): array {
        $cogsWithWaste = round($cogs * (1 + ($wastePercent / 100)), 2);
        $taxableProfit = max(0.0, round($omset - $cogsWithWaste - $expense, 2));

        $pp23 = round($omset * self::PP23_RATE, 2);
        $normal = $this->normalTax($businessType, $taxableProfit);
        $difference = round($normal - $pp23, 2);

        return [
            'business_type' => $businessType,
            'omset' => round($omset, 2),
            'cogs' => round($cogs, 2),
            'waste_percent' => $wastePercent,
            'cogs_with_waste' => $cogsWithWaste,
            'expense' => round($expense, 2),
            'taxable_profit' => $taxableProfit,
            'pp23' => $pp23,
            'normal' => $normal,
            'difference' => $difference,
            // skema yang lebih hemat
            'recommended' => $pp23 <= $normal ? 'pp23' : 'normal',
            'saving' => abs($difference),
        ];
    }

    private function normalTax(string $businessType, float $taxableProfit): float
    {
        if ($taxableProfit <= 0) {
            return 0.0;
        }

        if ($businessType === self::TYPE_PT || $businessType === self::TYPE_CV) {
            return round($taxableProfit * self::BADAN_RATE, 2);
        }

        return $this->progressivePph21($taxableProfit);
    }

    private function progressivePph21(float $taxableProfit): float
    {
        $tax = 0.0;
        $previousLimit = 0.0;

        foreach (self::PPH21_BRACKETS as $bracket) {
            $limit = $bracket['limit'];
            if ($limit === null) {
                $tax += ($taxableProfit - $previousLimit) * $bracket['rate'];
                break;
            }

            if ($taxableProfit > $limit) {
                $tax += ($limit - $previousLimit) * $bracket['rate'];
                $previousLimit = $limit;
            } else {
                $tax += ($taxableProfit - $previousLimit) * $bracket['rate'];
                break;
            }
        }

        return round($tax, 2);
    }
}
