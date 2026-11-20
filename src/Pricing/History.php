<?php

namespace Glubschi\Seat\Contracts\Pricing;

use Carbon\Carbon;

/**
 * Price history of one hull, built from the contracts the plugin has seen. ESI keeps no history for contracts.
 *
 * A contract is open from its issue date until it was gone from the region lists or expired, whichever came
 * first. The price of a day is the median of the counted contracts open at the end of that day (now for today).
 *
 * A contract that was gone before its expiry date is "probably sold": ESI does not say whether it was accepted or
 * deleted. It needs to be gone at least an hour before expiry, since a contract is only seen every 30 minutes.
 *
 * Days before the plugin's first run are built only from contracts that were still open on that run: contracts
 * sold before were never seen. Those days are marked "estimated"; their asking prices tend to be too high.
 *
 * Contracts are given as arrays with: issued (timestamp), closed (timestamp), net_price, counted (bool),
 * sold (bool), gone (timestamp or null).
 */
final class History
{
    /** Gone this long before expiry counts as probably sold. */
    public const SOLD_MARGIN = 3600;

    /**
     * @return array<int, array{date: string, median: float|null, lowest: float|null, open: int, sold: int, sold_median: float|null, estimated: bool}>
     */
    public static function series(array $contracts, Carbon $from, Carbon $now, ?Carbon $collectionStart): array
    {
        $days = [];
        for ($day = $from->copy()->startOfDay(); $day->lte($now); $day->addDay()) {
            $start = $day->getTimestamp();
            $end = min($day->copy()->endOfDay()->getTimestamp(), $now->getTimestamp());

            $open = self::openAt($contracts, $end);
            $sold = [];
            foreach ($contracts as $c) {
                if ($c['sold'] && $c['counted'] && $c['gone'] >= $start && $c['gone'] <= $end) {
                    $sold[] = $c['net_price'];
                }
            }

            $days[] = [
                'date' => $day->toDateString(),
                'median' => $open === [] ? null : Stats::median($open),
                'lowest' => $open === [] ? null : min($open),
                'open' => count($open),
                'sold' => count($sold),
                'sold_median' => $sold === [] ? null : Stats::median($sold),
                'estimated' => $collectionStart !== null && $end < $collectionStart->getTimestamp(),
            ];
        }

        return $days;
    }

    /** Median of the counted contracts open at a moment, or null when none was open. */
    public static function medianAt(array $contracts, int $timestamp): ?float
    {
        $open = self::openAt($contracts, $timestamp);

        return $open === [] ? null : Stats::median($open);
    }

    /**
     * Change of the median from $days days ago to now, in percent, or null when one of the two is missing.
     *
     * @return array{pct: float, estimated: bool}|null
     */
    public static function trend(array $contracts, Carbon $now, int $days, ?Carbon $collectionStart): ?array
    {
        $then = $now->copy()->subDays($days);
        $before = self::medianAt($contracts, $then->getTimestamp());
        $current = self::medianAt($contracts, $now->getTimestamp());
        if ($before === null || $current === null || $before <= 0) {
            return null;
        }

        return [
            'pct' => ($current - $before) / $before * 100,
            'estimated' => $collectionStart !== null && $then->lt($collectionStart),
        ];
    }

    /** @return float[] ship prices of the counted contracts open at the timestamp */
    private static function openAt(array $contracts, int $timestamp): array
    {
        $prices = [];
        foreach ($contracts as $c) {
            if ($c['counted'] && $c['issued'] <= $timestamp && $c['closed'] > $timestamp) {
                $prices[] = $c['net_price'];
            }
        }

        return $prices;
    }
}
