<?php

namespace Glubschi\Seat\Contracts\Tests;

use Carbon\Carbon;
use Glubschi\Seat\Contracts\Contracts\Contents;
use Glubschi\Seat\Contracts\Contracts\Overview;
use Illuminate\Support\Facades\DB;

class OverviewTest extends TestCase
{
    private const NOW = '2026-11-20 12:00:00';

    protected function setUp(): void
    {
        parent::setUp();
        config(['contracts.groups' => ['Dreadnought', 'Carrier']]);
        DB::table('invTypes')->insert(['typeID' => 19720, 'groupID' => 485, 'typeName' => 'Revelation']);
        DB::table('regions')->insert([['region_id' => 10000002, 'name' => 'The Forge'], ['region_id' => 10000060, 'name' => 'Delve']]);
        DB::table('solar_systems')->insert(['system_id' => 30000142, 'region_id' => 10000002, 'name' => 'Jita', 'security' => 0.9459]);
        DB::table('staStations')->insert(['stationID' => 60003760, 'stationName' => 'Jita IV - Moon 4 - Caldari Navy Assembly Plant', 'solarSystemID' => 30000142]);
        DB::table('universe_names')->insert([
            ['entity_id' => 90000001, 'name' => 'Seller One', 'category' => 'character'],
            ['entity_id' => 98000001, 'name' => 'Seller Corp', 'category' => 'corporation'],
        ]);
    }

    private function contract(int $id, int $type, ?float $net, array $extra = []): void
    {
        DB::table('glubschi_contracts')->insert($extra + [
            'contract_id' => $id, 'region_id' => 10000002, 'type' => 'item_exchange', 'issuer_id' => 90000001,
            'issuer_corporation_id' => 98000001, 'for_corporation' => false, 'start_location_id' => 60003760,
            'price' => $net, 'date_issued' => '2026-11-18 10:00:00', 'date_expired' => '2026-11-25 10:00:00',
            'first_seen_at' => '2026-11-20 10:00:00', 'last_seen_at' => '2026-11-20 10:00:00',
            'items_status' => 'read', 'valuation' => 'single', 'main_type_id' => $type, 'net_price' => $net,
            'extras_value' => 0, 'extras_unpriced' => 0,
        ]);
    }

    public function test_groups_hulls_and_contracts_with_figures(): void
    {
        $this->contract(1, 19722, 3.0e9);
        $this->contract(2, 19722, 3.2e9);
        $this->contract(3, 19722, 2.8e9);
        $this->contract(4, 19722, 1.0);                                   // bait: outlier
        $this->contract(5, 19722, null, ['valuation' => 'multiple']);     // shown, not counted
        $this->contract(6, 19720, 2.5e9, ['for_corporation' => true, 'start_location_id' => 1035466617946, 'region_id' => 10000060]);
        $this->contract(7, 19722, 2.0e9, ['gone_at' => '2026-11-20 11:00:00']);        // gone: not listed
        $this->contract(8, 19722, 2.0e9, ['date_expired' => '2026-11-20 11:00:00']);   // expired: not listed
        $this->contract(9, 587, 1e6, ['valuation' => 'none']);            // no capital

        $tree = Overview::build(Carbon::parse(self::NOW));

        $this->assertSame(['Dreadnought', 'Carrier'], array_column($tree, 'name'));
        [$dreads, $carriers] = $tree;
        $this->assertSame(6, $dreads['open']);
        $this->assertSame(['count' => 4, 'lowest' => 2.5e9, 'median' => 2.9e9, 'average' => 2.875e9], $dreads['figures']);
        $this->assertSame(0, $carriers['open']);
        $this->assertSame([], $carriers['hulls']);

        [$naglfar, $revelation] = [$dreads['hulls'][0], $dreads['hulls'][1]];
        $this->assertSame(['Naglfar', 'Revelation'], [$naglfar['name'], $revelation['name']]);
        $this->assertSame(5, $naglfar['open']);
        $this->assertSame(3, $naglfar['figures']['count']);
        $this->assertSame(3.0e9, $naglfar['figures']['median']);

        // Cheapest first, the bait marked as outlier, contracts without a ship price last.
        $this->assertSame([4, 3, 1, 2, 5], array_column($naglfar['contracts'], 'contract_id'));
        $bait = $naglfar['contracts'][0];
        $this->assertTrue($bait['outlier']);
        $this->assertFalse($bait['counted']);
        $this->assertFalse($naglfar['contracts'][4]['counted']);

        $one = $naglfar['contracts'][1];
        $this->assertSame('Jita IV - Moon 4 - Caldari Navy Assembly Plant', $one['location']);
        $this->assertSame('Jita', $one['system']);
        $this->assertEqualsWithDelta(0.9459, $one['security'], 1e-9);
        $this->assertSame('The Forge', $one['region']);
        $this->assertSame('Seller One', $one['issuer']);
        $this->assertSame(4, $one['days_left']);

        $rev = $revelation['contracts'][0];
        $this->assertNull($rev['location']);
        $this->assertTrue($rev['structure']);
        $this->assertSame('Delve', $rev['region']);
        $this->assertSame('Seller Corp', $rev['issuer']);
    }

    public function test_contents_with_names_and_todays_jita_value(): void
    {
        $this->contract(1, 19722, 3.0e9);
        DB::table('invTypes')->insert(['typeID' => 3001, 'groupID' => 1, 'typeName' => 'Siege Module']);
        DB::table('glubschi_contract_items')->insert([
            ['contract_id' => 1, 'record_id' => 1, 'type_id' => 3001, 'quantity' => 2, 'is_included' => true, 'is_blueprint_copy' => false, 'runs' => null, 'material_efficiency' => null, 'time_efficiency' => null, 'item_id' => null],
            ['contract_id' => 1, 'record_id' => 2, 'type_id' => 19722, 'quantity' => 1, 'is_included' => true, 'is_blueprint_copy' => false, 'runs' => null, 'material_efficiency' => null, 'time_efficiency' => null, 'item_id' => null],
            ['contract_id' => 1, 'record_id' => 3, 'type_id' => 587, 'quantity' => 1, 'is_included' => true, 'is_blueprint_copy' => true, 'runs' => 5, 'material_efficiency' => 10, 'time_efficiency' => 20, 'item_id' => null],
        ]);
        $this->jitaOrder(1, 3001, 10e6);
        $this->jitaOrder(2, 587, 1e6);

        $items = Contents::of(1);

        $this->assertSame(['Naglfar', 'Siege Module', 'Rifter'], array_column($items, 'name'));
        $this->assertTrue($items[0]['capital']);
        $this->assertSame(20e6, $items[1]['value']);
        $this->assertNull($items[2]['value']);      // blueprint copy: no market price
        $this->assertSame([5, 10, 20], [$items[2]['runs'], $items[2]['me'], $items[2]['te']]);
        $this->assertNull(Contents::of(999));
    }
}
