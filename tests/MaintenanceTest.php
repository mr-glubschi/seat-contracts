<?php

namespace Glubschi\Seat\Contracts\Tests;

use Carbon\Carbon;
use Glubschi\Seat\Contracts\Commands\PruneContracts;
use Illuminate\Support\Facades\DB;

/** contracts:prune and contracts:revalue */
class MaintenanceTest extends TestCase
{
    private function contract(int $id, array $extra = []): void
    {
        DB::table('glubschi_contracts')->insert($extra + [
            'contract_id' => $id, 'region_id' => 10000002, 'type' => 'item_exchange', 'issuer_id' => 1,
            'issuer_corporation_id' => 2, 'for_corporation' => false, 'price' => 3e9,
            'date_issued' => '2026-07-01 10:00:00', 'date_expired' => '2026-12-16 10:00:00',
            'first_seen_at' => '2026-07-01 10:00:00', 'last_seen_at' => '2026-07-01 10:00:00', 'items_status' => 'read',
        ]);
    }

    private function item(int $contract, int $record, int $type, int $qty = 1): void
    {
        DB::table('glubschi_contract_items')->insert([
            'contract_id' => $contract, 'record_id' => $record, 'type_id' => $type, 'quantity' => $qty, 'is_included' => true,
            'is_blueprint_copy' => false, 'runs' => null, 'material_efficiency' => null, 'time_efficiency' => null, 'item_id' => null,
        ]);
    }

    public function test_prune_deletes_contents_of_long_closed_contracts_and_keeps_capital_prices(): void
    {
        $old = ['gone_at' => '2026-08-01 10:00:00'];
        $this->contract(1, $old + ['valuation' => 'single', 'main_type_id' => 19722, 'net_price' => 3e9]);
        $this->contract(2, $old + ['valuation' => 'none']);
        $this->contract(3, ['date_expired' => '2026-08-01 10:00:00', 'items_status' => 'pending']);   // expired, never read
        $this->contract(4, ['gone_at' => '2026-11-10 10:00:00', 'valuation' => 'none']);              // closed recently
        $this->contract(5, ['valuation' => 'none']);                                                  // open
        foreach ([1, 2, 4, 5] as $id) {
            $this->item($id, $id * 10, 587);
        }

        $now = Carbon::parse('2026-11-20 12:00:00');
        $this->assertSame([1, 2], PruneContracts::prune($now, 90));

        $this->assertSame([1, 4, 5], DB::table('glubschi_contracts')->orderBy('contract_id')->pluck('contract_id')->map(fn ($v) => (int) $v)->all());
        $this->assertSame([4, 5], DB::table('glubschi_contract_items')->orderBy('contract_id')->pluck('contract_id')->map(fn ($v) => (int) $v)->all());
        $one = DB::table('glubschi_contracts')->where('contract_id', 1)->first();
        $this->assertEquals(3e9, $one->net_price);
        $this->assertSame('2026-11-20 12:00:00', $one->items_pruned_at);

        // Nothing left to do on a second run.
        $this->assertSame([0, 0], PruneContracts::prune($now, 90));
    }

    public function test_prune_with_zero_days_deletes_nothing(): void
    {
        config(['contracts.keep_items_days' => 0]);
        $this->contract(2, ['gone_at' => '2020-01-01 10:00:00', 'valuation' => 'none']);

        $this->artisan('contracts:prune')->assertSuccessful();

        $this->assertSame(1, DB::table('glubschi_contracts')->count());
    }

    public function test_revalue_applies_the_current_rules_to_stored_contents(): void
    {
        // Valued by v0.2: the rig was subtracted at Jita buy.
        $this->contract(1, ['valuation' => 'single', 'main_type_id' => 19722, 'extras_value' => 150e6, 'extras_unpriced' => 0, 'net_price' => 2.85e9]);
        $this->item(1, 1, 19722);
        $this->item(1, 2, 5001, 3);
        DB::table('dgmTypeEffects')->insert(['typeID' => 5001, 'effectID' => 2663]);
        $this->jitaOrder(1, 5001, 50e6);
        // A contract without a capital that stays as it is.
        $this->contract(2, ['valuation' => 'none']);
        $this->item(2, 3, 587);

        $this->artisan('contracts:revalue')->expectsOutput('Valued 2 contracts, 1 changed.')->assertSuccessful();

        $one = DB::table('glubschi_contracts')->where('contract_id', 1)->first();
        $this->assertEquals(3e9, $one->net_price);
        $this->assertEquals(0, $one->extras_value);
        $this->assertSame(3, (int) $one->extras_rigs);
    }
}
