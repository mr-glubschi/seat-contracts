<?php

namespace Glubschi\Seat\Contracts\Tests;

use Carbon\Carbon;
use Glubschi\Seat\Contracts\Contracts\Contents;
use Glubschi\Seat\Contracts\Contracts\Overview;
use Illuminate\Support\Facades\DB;

class OverviewTest extends TestCase
{
    private const NOW = '2026-11-20 12:00:00';

    private const NAGLFAR = 19722;

    private const REVELATION = 19720;

    private const NFI = 42243;

    protected function setUp(): void
    {
        parent::setUp();
        config(['contracts.groups' => ['Dreadnought', 'Carrier']]);
        DB::table('invTypes')->insert([
            ['typeID' => self::REVELATION, 'groupID' => 485, 'typeName' => 'Revelation'],
            ['typeID' => self::NFI, 'groupID' => 485, 'typeName' => 'Naglfar Fleet Issue'],
        ]);
        DB::table('invMetaTypes')->insert([
            ['typeID' => self::NAGLFAR, 'parentTypeID' => null, 'metaGroupID' => 1],
            ['typeID' => self::NFI, 'parentTypeID' => self::NAGLFAR, 'metaGroupID' => 4],
        ]);
        DB::table('regions')->insert([['region_id' => 10000002, 'name' => 'The Forge'], ['region_id' => 10000060, 'name' => 'Delve']]);
        DB::table('solar_systems')->insert([
            ['system_id' => 30000142, 'region_id' => 10000002, 'name' => 'Jita', 'security' => 0.9459],
            ['system_id' => 30002813, 'region_id' => 10000032, 'name' => 'Tama', 'security' => 0.3],
        ]);
        DB::table('staStations')->insert([
            ['stationID' => 60003760, 'stationName' => 'Jita IV - Moon 4 - Caldari Navy Assembly Plant', 'solarSystemID' => 30000142],
            ['stationID' => 60005686, 'stationName' => 'Tama VII - Moon 9', 'solarSystemID' => 30002813],
        ]);
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
            'extras_value' => 0, 'extras_unpriced' => 0, 'extras_rigs' => 0,
        ]);
    }

    private function sample(): void
    {
        $this->contract(1, self::NAGLFAR, 3.0e9);
        $this->contract(2, self::NAGLFAR, 3.2e9);
        $this->contract(3, self::NAGLFAR, 2.8e9);
        // Below half the median over the window (2.4 B, gone and expired contracts included): outlier.
        $this->contract(4, self::NAGLFAR, 1.0e9);
        $this->contract(5, self::NAGLFAR, null, ['valuation' => 'multiple']); // listed, not counted
        $this->contract(6, self::REVELATION, 2.5e9, ['for_corporation' => true, 'start_location_id' => 1035466617946, 'region_id' => 10000060]);
        $this->contract(7, self::REVELATION, 1.0);                            // 1-ISK bait, only 2 Revelations: caught by the floor
        $this->contract(8, self::NAGLFAR, 2.0e9, ['gone_at' => '2026-11-19 11:00:00']);       // gone: history only
        $this->contract(9, self::NAGLFAR, 2.0e9, ['date_expired' => '2026-11-20 11:00:00']);  // expired: history only
        $this->contract(10, self::NFI, 4.6e9, ['start_location_id' => 60005686]);
        $this->contract(11, 587, 1e6, ['valuation' => 'none', 'main_type_id' => null]);
    }

    public function test_classes_split_into_standard_and_faction_hulls(): void
    {
        $this->sample();

        $tree = Overview::build([], Carbon::parse(self::NOW));

        $this->assertSame(['Dreadnought', 'Carrier'], array_column($tree, 'name'));
        [$dreads, $carriers] = $tree;
        $this->assertSame(8, $dreads['open']);
        $this->assertSame(2.5e9, $dreads['lowest']);
        $this->assertSame(0, $carriers['open']);
        $this->assertSame([], $carriers['segments']);

        [$standard, $faction] = $dreads['segments'];
        $this->assertSame(['standard', 'faction'], [$standard['segment'], $faction['segment']]);
        $this->assertSame(['Naglfar', 'Revelation'], array_column($standard['hulls'], 'name'));
        $this->assertSame(['count' => 4, 'lowest' => 2.5e9, 'median' => 2.9e9, 'average' => 2.875e9], $standard['figures']);
        $this->assertSame(['count' => 1, 'lowest' => 4.6e9, 'median' => 4.6e9, 'average' => 4.6e9], $faction['figures']);

        $naglfar = $standard['hulls'][0];
        $this->assertSame(5, $naglfar['open']);
        $this->assertSame(3, $naglfar['figures']['count']);
        $this->assertSame(3.0e9, $naglfar['figures']['median']);
    }

    public function test_a_hull_lists_its_open_contracts_with_outliers_marked(): void
    {
        $this->sample();

        $naglfar = Overview::hull(self::NAGLFAR, [], Carbon::parse(self::NOW));

        // Cheapest first, contracts without a ship price last; gone and expired ones are not listed.
        $this->assertSame([4, 3, 1, 2, 5], array_column($naglfar['contracts'], 'contract_id'));
        $this->assertTrue($naglfar['contracts'][0]['outlier']);
        $this->assertFalse($naglfar['contracts'][0]['counted']);
        $this->assertFalse($naglfar['contracts'][4]['counted']);
        $this->assertArrayNotHasKey('issued', $naglfar['contracts'][0]);

        $one = $naglfar['contracts'][2];
        $this->assertSame('Jita IV - Moon 4 - Caldari Navy Assembly Plant', $one['location']);
        $this->assertSame('Jita', $one['system']);
        $this->assertSame('The Forge', $one['region']);
        $this->assertSame('Seller One', $one['issuer']);
        $this->assertSame(4, $one['days_left']);

        $revelation = Overview::hull(self::REVELATION, [], Carbon::parse(self::NOW));
        [$cheap, $corp] = $revelation['contracts'];
        $this->assertTrue($cheap['outlier']);     // floor: below 10 % of the standard hulls' median
        $this->assertTrue($corp['structure']);
        $this->assertNull($corp['location']);
        $this->assertSame('Seller Corp', $corp['issuer']);
    }

    public function test_the_history_contains_gone_contracts_and_probable_sales(): void
    {
        $this->sample();

        $naglfar = Overview::hull(self::NAGLFAR, [], Carbon::parse(self::NOW));
        $days = array_column($naglfar['series'], null, 'date');

        $this->assertCount(91, $naglfar['series']);
        $this->assertSame('2026-11-20', $naglfar['collection_start']);
        // 18.11.: contracts 1-3, 8 and 9 open (4 is an outlier); 8 was gone on the 19th before its expiry.
        $this->assertSame(5, $days['2026-11-18']['open']);
        $this->assertSame(2.8e9, $days['2026-11-18']['median']);
        $this->assertTrue($days['2026-11-18']['estimated']);
        $this->assertSame(1, $days['2026-11-19']['sold']);
        $this->assertSame(2.0e9, $days['2026-11-19']['sold_median']);
        $this->assertSame(3, $days['2026-11-20']['open']);
        $this->assertFalse($days['2026-11-20']['estimated']);
        $this->assertSame(0, $days['2026-08-30']['open']);
    }

    public function test_filters_apply_to_figures_and_lists(): void
    {
        $this->sample();
        $now = Carbon::parse(self::NOW);

        $low = Overview::build(['security' => 'low'], $now)[0];
        $this->assertSame(1, $low['open']);
        $this->assertSame('faction', $low['segments'][0]['segment']);

        $delve = Overview::build(['region' => '10000060'], $now)[0];
        $this->assertSame(['Revelation'], array_column($delve['segments'][0]['hulls'], 'name'));

        $located = Overview::hull(self::REVELATION, ['located' => '1'], $now);
        $this->assertSame([7], array_column($located['contracts'], 'contract_id'));

        $this->assertSame(['security' => null, 'region' => null, 'located' => false], Overview::filters(['security' => 'x', 'region' => 'abc']));
        $this->assertSame([10000060 => 'Delve', 10000002 => 'The Forge'], Overview::regions($now));
    }

    public function test_contents_with_names_rigs_and_todays_jita_value(): void
    {
        $this->contract(1, self::NAGLFAR, 3.0e9);
        DB::table('invTypes')->insert([
            ['typeID' => 3001, 'groupID' => 1, 'typeName' => 'Siege Module'],
            ['typeID' => 5001, 'groupID' => 2, 'typeName' => 'Capital Trimark Armor Pump I'],
        ]);
        DB::table('dgmTypeEffects')->insert(['typeID' => 5001, 'effectID' => 2663]);
        $row = fn ($record, $type, $qty, $bpc = false, $extra = []) => $extra + [
            'contract_id' => 1, 'record_id' => $record, 'type_id' => $type, 'quantity' => $qty, 'is_included' => true,
            'is_blueprint_copy' => $bpc, 'runs' => null, 'material_efficiency' => null, 'time_efficiency' => null, 'item_id' => null,
        ];
        DB::table('glubschi_contract_items')->insert([
            $row(1, 3001, 2),
            $row(2, self::NAGLFAR, 1),
            $row(3, 587, 1, true, ['runs' => 5, 'material_efficiency' => 10, 'time_efficiency' => 20]),
            $row(4, 5001, 3),
        ]);
        $this->jitaOrder(1, 3001, 10e6);
        $this->jitaOrder(2, 587, 1e6);
        $this->jitaOrder(3, 5001, 50e6);

        $items = Contents::of(1);

        $this->assertSame(['Naglfar', 'Siege Module'], array_slice(array_column($items, 'name'), 0, 2));
        $this->assertTrue($items[0]['capital']);
        $this->assertSame(20e6, $items[1]['value']);
        $byName = array_column($items, null, 'name');
        $this->assertNull($byName['Rifter']['value']);                         // blueprint copy
        $this->assertSame([5, 10, 20], [$byName['Rifter']['runs'], $byName['Rifter']['me'], $byName['Rifter']['te']]);
        $this->assertTrue($byName['Capital Trimark Armor Pump I']['rig']);
        $this->assertNull($byName['Capital Trimark Armor Pump I']['value']);   // rig: 0 ISK
        $this->assertNull(Contents::of(999));
    }
}
