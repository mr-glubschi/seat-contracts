<?php

namespace Glubschi\Seat\Contracts\Tests;

use Carbon\Carbon;
use Glubschi\Seat\Contracts\Contracts\Overview;
use Glubschi\Seat\Contracts\Contracts\Status;
use Illuminate\Support\Facades\DB;

/** Renders the page with SeAT's layout replaced by a stub, so Blade errors show up in the tests. */
class ViewTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['view']->addNamespace('web', __DIR__ . '/stubs/web');
    }

    public function test_renders_classes_hulls_and_contracts(): void
    {
        config(['contracts.groups' => ['Dreadnought', 'Titan']]);
        DB::table('glubschi_contracts')->insert([
            'contract_id' => 1, 'region_id' => 10000002, 'type' => 'auction', 'issuer_id' => 1, 'issuer_corporation_id' => 2,
            'for_corporation' => false, 'start_location_id' => 1035466617946, 'price' => 2e9, 'buyout' => 3.1e9,
            'date_issued' => '2026-11-18 10:00:00', 'date_expired' => '2026-11-25 10:00:00',
            'first_seen_at' => '2026-11-20 10:00:00', 'last_seen_at' => '2026-11-20 10:00:00', 'items_status' => 'read',
            'valuation' => 'single', 'main_type_id' => 19722, 'net_price' => 3.05e9, 'extras_value' => 5e7, 'extras_unpriced' => 2,
        ]);

        $html = view('contracts::index', [
            'tree' => Overview::build(Carbon::parse('2026-11-20 12:00:00')),
            'status' => Status::figures(),
            'characters' => [['id' => 7, 'name' => 'Pilot', 'scope' => true, 'online' => true], ['id' => 8, 'name' => 'Alt', 'scope' => false, 'online' => null]],
            'selected' => 7,
        ])->render();

        $this->assertStringContainsString('Naglfar', $html);
        $this->assertStringContainsString('3.05 B', $html);
        $this->assertStringContainsString('50.00 M', $html);
        $this->assertStringContainsString('2 without price', $html);
        $this->assertStringContainsString('Structure (unknown to SeAT)', $html);
        $this->assertStringContainsString('data-contract="1"', $html);
        $this->assertStringContainsString('not in SDE', $html);   // Titan is not in the test SDE
        $this->assertMatchesRegularExpression('/<option value="8"[^>]*disabled/', $html);
    }
}
