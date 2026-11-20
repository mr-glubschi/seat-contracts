<?php

namespace Glubschi\Seat\Contracts\Tests;

use Glubschi\Seat\Contracts\Contracts\Status;
use Illuminate\Support\Facades\DB;

class StatusTest extends TestCase
{
    private function contract(int $id, array $extra): void
    {
        DB::table('glubschi_contracts')->insert($extra + [
            'contract_id' => $id, 'region_id' => 10000002, 'type' => 'item_exchange', 'issuer_id' => 1,
            'issuer_corporation_id' => 2, 'for_corporation' => false, 'price' => 3e9,
            'date_issued' => '2026-11-18 10:00:00', 'date_expired' => '2026-12-16 10:00:00',
            'first_seen_at' => '2026-11-20 10:00:00', 'last_seen_at' => '2026-11-20 10:00:00',
        ]);
    }

    public function test_counts_the_collection_and_the_capitals_per_group(): void
    {
        config(['contracts.groups' => ['Dreadnought', 'Carrier', 'Titan']]);
        $read = ['items_status' => 'read'];
        $this->contract(1, $read + ['valuation' => 'single', 'main_type_id' => 19722, 'net_price' => 2.9e9]);
        $this->contract(2, $read + ['valuation' => 'single', 'main_type_id' => 19722, 'net_price' => 3.2e9]);
        $this->contract(3, $read + ['valuation' => 'multiple', 'main_type_id' => 19722]);
        $this->contract(4, $read + ['valuation' => 'single', 'main_type_id' => 19722, 'net_price' => 1e9, 'gone_at' => '2026-11-20 11:00:00']);
        $this->contract(5, ['items_status' => 'pending', 'last_seen_at' => '2026-11-20 10:30:00']);
        $this->contract(6, ['items_status' => 'unavailable']);
        $this->jitaOrder(1, 34, 5.0);

        $s = Status::figures();

        $this->assertSame('2026-11-20 10:30:00', $s['last_seen']);
        $this->assertSame([5, 3, 1, 1, 1], [$s['open'], $s['read'], $s['pending'], $s['unavailable'], $s['gone']]);
        $this->assertTrue($s['jita']);
        $this->assertSame(
            [
                ['name' => 'Dreadnought', 'found' => true, 'open' => 3, 'priced' => 2, 'lowest' => 2.9e9],
                ['name' => 'Carrier', 'found' => true, 'open' => 0, 'priced' => 0, 'lowest' => null],
                ['name' => 'Titan', 'found' => false, 'open' => 0, 'priced' => 0, 'lowest' => null],
            ],
            $s['groups'],
        );
    }

    public function test_says_when_seat_has_no_jita_buy_orders(): void
    {
        $this->jitaOrder(1, 34, 5.0, false);

        $this->assertFalse(Status::figures()['jita']);
    }
}
