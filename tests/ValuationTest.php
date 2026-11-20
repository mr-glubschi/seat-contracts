<?php

namespace Glubschi\Seat\Contracts\Tests;

use Glubschi\Seat\Contracts\Pricing\Valuation;
use PHPUnit\Framework\TestCase;

class ValuationTest extends TestCase
{
    private const NAGLFAR = 19722;

    private const ARCHON = 23757;

    private const CAPITALS = [self::NAGLFAR => true, self::ARCHON => true];

    private static function item(int $type, int $qty = 1, bool $included = true, bool $bpc = false): array
    {
        return ['type_id' => $type, 'quantity' => $qty, 'is_included' => $included, 'is_blueprint_copy' => $bpc];
    }

    public function test_subtracts_the_fitting_at_jita_buy(): void
    {
        $items = [self::item(self::NAGLFAR), self::item(3001, 4), self::item(3002, 1000)];
        $prices = [3001 => 10_000_000.0, 3002 => 500.0];

        $v = Valuation::value($items, self::CAPITALS, $prices, 'item_exchange', 3_000_000_000.0, null);

        $this->assertSame(Valuation::SINGLE, $v['valuation']);
        $this->assertSame(self::NAGLFAR, $v['main_type_id']);
        $this->assertSame(40_500_000.0, $v['extras_value']);
        $this->assertSame(0, $v['extras_unpriced']);
        $this->assertSame(2_959_500_000.0, $v['net_price']);
    }

    public function test_counts_items_without_a_buy_price_and_blueprint_copies_as_unpriced(): void
    {
        $items = [self::item(self::NAGLFAR), self::item(4001), self::item(4002, 1, true, true)];
        // A buy price for 4002 exists, but it is for the original, not for the copy.
        $prices = [4002 => 1_000_000_000.0];

        $v = Valuation::value($items, self::CAPITALS, $prices, 'item_exchange', 2_000_000_000.0, null);

        $this->assertSame(0.0, $v['extras_value']);
        $this->assertSame(2, $v['extras_unpriced']);
        $this->assertSame(2_000_000_000.0, $v['net_price']);
    }

    public function test_a_contract_with_two_capitals_has_no_ship_price(): void
    {
        foreach ([[self::item(self::NAGLFAR), self::item(self::ARCHON)], [self::item(self::NAGLFAR, 2)]] as $items) {
            $v = Valuation::value($items, self::CAPITALS, [], 'item_exchange', 5e9, null);

            $this->assertSame(Valuation::MULTIPLE, $v['valuation']);
            $this->assertSame(self::NAGLFAR, $v['main_type_id']);
            $this->assertNull($v['net_price']);
        }
    }

    public function test_a_contract_that_asks_for_items_has_no_ship_price(): void
    {
        $items = [self::item(self::NAGLFAR), self::item(self::ARCHON, 1, false)];

        $v = Valuation::value($items, self::CAPITALS, [], 'item_exchange', 0.0, null);

        $this->assertSame(Valuation::REQUESTS, $v['valuation']);
        $this->assertNull($v['net_price']);
    }

    public function test_a_contract_that_asks_for_a_capital_is_not_a_capital_offer(): void
    {
        $v = Valuation::value([self::item(587), self::item(self::NAGLFAR, 1, false)], self::CAPITALS, [], 'item_exchange', 1e9, null);

        $this->assertSame(Valuation::NONE, $v['valuation']);
        $this->assertNull($v['main_type_id']);
    }

    public function test_an_auction_counts_with_its_buyout_only(): void
    {
        $items = [self::item(self::NAGLFAR)];

        $withBuyout = Valuation::value($items, self::CAPITALS, [], 'auction', 1e9, 2.5e9);
        $withoutBuyout = Valuation::value($items, self::CAPITALS, [], 'auction', 1e9, null);

        $this->assertSame(2.5e9, $withBuyout['net_price']);
        $this->assertSame(Valuation::SINGLE, $withoutBuyout['valuation']);
        $this->assertNull($withoutBuyout['net_price']);
    }

    public function test_no_capital(): void
    {
        $v = Valuation::value([self::item(587, 10)], self::CAPITALS, [], 'item_exchange', 1e6, null);

        $this->assertSame(Valuation::NONE, $v['valuation']);
        $this->assertNull($v['extras_value']);
    }

    public function test_rigs_count_as_zero_and_are_counted_separately(): void
    {
        $items = [self::item(self::NAGLFAR), self::item(5001, 3), self::item(3001)];
        $prices = [5001 => 100_000_000.0, 3001 => 10_000_000.0];

        $v = Valuation::value($items, self::CAPITALS, $prices, 'item_exchange', 3e9, null, [5001 => true]);

        $this->assertSame(10_000_000.0, $v['extras_value']);
        $this->assertSame(3, $v['extras_rigs']);
        $this->assertSame(0, $v['extras_unpriced']);
        $this->assertSame(2_990_000_000.0, $v['net_price']);
    }
}
