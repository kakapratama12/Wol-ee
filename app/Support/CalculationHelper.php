<?php

namespace App\Support;

class CalculationHelper
{
    /**
     * Margin persentase: (revenue - cost) / revenue * 100.
     *
     * Mengembalikan 0.0 jika revenue <= 0.
     */
    public static function marginPercent(float $revenue, float $cost): float
    {
        return $revenue > 0 ? round(($revenue - $cost) / $revenue * 100, 2) : 0.0;
    }
}
