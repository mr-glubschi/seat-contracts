<?php

namespace Glubschi\Seat\Contracts\Pricing;

/**
 * Price figures for a set of ship prices: count, lowest, median, average, and which prices are outliers.
 *
 * Outliers are decided per hull, against the median of that hull: below half of it or above twice it. That
 * catches "Naglfar for 1 ISK" bait and absurd asks, while a real bargain at 60 % still counts. With fewer than
 * three prices there is no meaningful median to compare against, so nothing is an outlier.
 */
final class Stats
{
    public const LOW = 0.5;

    public const HIGH = 2.0;

    public const MIN_FOR_OUTLIERS = 3;

    /** Below this share of the median of the whole class segment, a price is always an outlier. */
    public const FLOOR = 0.1;

    /**
     * @param  array<int, float>  $prices  ship price by contract ID
     * @return array<int, true>  contract IDs whose price is an outlier
     */
    public static function outliers(array $prices): array
    {
        if (count($prices) < self::MIN_FOR_OUTLIERS) {
            return [];
        }
        $median = self::median(array_values($prices));
        $out = [];
        foreach ($prices as $id => $price) {
            if ($price < $median * self::LOW || $price > $median * self::HIGH) {
                $out[$id] = true;
            }
        }

        return $out;
    }

    /**
     * @param  float[]  $prices
     * @return array{count: int, lowest: float|null, median: float|null, average: float|null}
     */
    public static function figures(array $prices): array
    {
        $prices = array_values($prices);
        if ($prices === []) {
            return ['count' => 0, 'lowest' => null, 'median' => null, 'average' => null];
        }

        return [
            'count' => count($prices),
            'lowest' => min($prices),
            'median' => self::median($prices),
            'average' => array_sum($prices) / count($prices),
        ];
    }

    /** @param  float[]  $values  not empty */
    public static function median(array $values): float
    {
        sort($values);
        $n = count($values);
        $mid = intdiv($n, 2);

        return $n % 2 ? (float) $values[$mid] : ($values[$mid - 1] + $values[$mid]) / 2;
    }
}
