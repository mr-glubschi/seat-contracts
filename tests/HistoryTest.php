<?php

namespace Glubschi\Seat\Contracts\Tests;

use Carbon\Carbon;
use Glubschi\Seat\Contracts\Pricing\History;
use PHPUnit\Framework\TestCase;

class HistoryTest extends TestCase
{
    private static function c(string $issued, string $closed, float $price, bool $counted = true, ?string $gone = null, bool $sold = false): array
    {
        return [
            'issued' => Carbon::parse($issued)->getTimestamp(),
            'closed' => Carbon::parse($closed)->getTimestamp(),
            'net_price' => $price,
            'counted' => $counted,
            'gone' => $gone === null ? null : Carbon::parse($gone)->getTimestamp(),
            'sold' => $sold,
        ];
    }

    public function test_median_at_counts_only_counted_contracts_open_at_that_moment(): void
    {
        $contracts = [
            self::c('2026-11-01', '2026-11-30', 3e9),
            self::c('2026-11-01', '2026-11-10', 2e9),
            self::c('2026-11-05', '2026-11-30', 4e9),
            self::c('2026-11-01', '2026-11-30', 1.0, false),
        ];

        $this->assertSame(2.5e9, History::medianAt($contracts, Carbon::parse('2026-11-03')->getTimestamp()));
        $this->assertSame(3e9, History::medianAt($contracts, Carbon::parse('2026-11-07')->getTimestamp()));
        $this->assertSame(3.5e9, History::medianAt($contracts, Carbon::parse('2026-11-20')->getTimestamp()));
        $this->assertNull(History::medianAt($contracts, Carbon::parse('2026-10-20')->getTimestamp()));
    }

    public function test_trend_in_percent_marked_estimated_before_the_first_run(): void
    {
        $contracts = [
            self::c('2026-10-01', '2026-11-12', 4e9),
            self::c('2026-11-10', '2026-12-30', 3e9),
        ];
        $now = Carbon::parse('2026-11-20 12:00');

        $trend = History::trend($contracts, $now, 10, Carbon::parse('2026-11-15'));
        // 10 days ago both were open (median 3.5 B), now only the second (3 B).
        $this->assertEqualsWithDelta(-100 / 7, $trend['pct'], 1e-9);
        $this->assertTrue($trend['estimated']);

        $this->assertNull(History::trend($contracts, $now, 60, null));
    }

    public function test_series_per_day_with_sales(): void
    {
        $contracts = [
            self::c('2026-11-18 08:00', '2026-11-30', 3e9),
            self::c('2026-11-18 08:00', '2026-11-19 15:00', 2e9, true, '2026-11-19 15:00', true),
        ];
        $now = Carbon::parse('2026-11-20 12:00');

        $series = History::series($contracts, Carbon::parse('2026-11-17'), $now, Carbon::parse('2026-11-19 00:00'));

        $this->assertSame(['2026-11-17', '2026-11-18', '2026-11-19', '2026-11-20'], array_column($series, 'date'));
        $this->assertSame([0, 2, 1, 1], array_column($series, 'open'));
        $this->assertSame([null, 2.5e9, 3e9, 3e9], array_column($series, 'median'));
        $this->assertSame([0, 0, 1, 0], array_column($series, 'sold'));
        $this->assertSame(2e9, $series[2]['sold_median']);
        $this->assertSame([true, true, false, false], array_column($series, 'estimated'));
    }
}
