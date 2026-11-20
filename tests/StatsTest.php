<?php

namespace Glubschi\Seat\Contracts\Tests;

use Glubschi\Seat\Contracts\Pricing\Stats;
use PHPUnit\Framework\TestCase;

class StatsTest extends TestCase
{
    public function test_median_of_odd_and_even_counts(): void
    {
        $this->assertSame(3.0, Stats::median([5, 1, 3]));
        $this->assertSame(2.5, Stats::median([4, 1, 3, 2]));
    }

    public function test_figures(): void
    {
        $this->assertSame(['count' => 3, 'lowest' => 1.0, 'median' => 2.0, 'average' => 3.0], Stats::figures([1.0, 6.0, 2.0]));
        $this->assertSame(['count' => 0, 'lowest' => null, 'median' => null, 'average' => null], Stats::figures([]));
    }

    public function test_outliers_are_below_half_or_above_twice_the_median(): void
    {
        $prices = [1 => 3e9, 2 => 3.1e9, 3 => 2.9e9, 4 => 1.4e9, 5 => 1.5e9, 6 => 6.3e9, 7 => 1.0];

        // Median 2.9 B: 1.4 B and 1 ISK are below 1.45 B, 6.3 B is above 5.8 B.
        $this->assertSame([4 => true, 6 => true, 7 => true], Stats::outliers($prices));
    }

    public function test_no_outliers_with_fewer_than_three_prices(): void
    {
        $this->assertSame([], Stats::outliers([1 => 1.0, 2 => 3e9]));
    }
}
