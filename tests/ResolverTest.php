<?php

namespace Glubschi\Seat\Contracts\Tests;

use Glubschi\Seat\Contracts\Contracts\Resolver;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Seat\Eveapi\Jobs\Universe\Names;
use Seat\Eveapi\Jobs\Universe\Structures\Citadel;
use Seat\Eveapi\Jobs\Universe\Structures\Station;

class ResolverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('refresh_tokens', function (Blueprint $t) {
            $t->bigInteger('character_id')->primary();
            $t->text('scopes');
            $t->softDeletes();
        });
        Bus::fake();
        // Bound by SeAT's eveapi provider, which the tests do not boot.
        $this->app->bind(\Seat\Eveapi\Contracts\CitadelAccessCache::class, \Seat\Eveapi\Jobs\Universe\Structures\CacheCitadelAccessCache::class);
    }

    private function contract(int $id, int $issuer, int $corp, int $location, array $extra = []): void
    {
        DB::table('glubschi_contracts')->insert($extra + [
            'contract_id' => $id, 'region_id' => 10000002, 'type' => 'item_exchange', 'issuer_id' => $issuer,
            'issuer_corporation_id' => $corp, 'for_corporation' => false, 'start_location_id' => $location, 'price' => 3e9,
            'date_issued' => '2026-11-18 10:00:00', 'date_expired' => '2026-12-16 10:00:00',
            'first_seen_at' => '2026-11-20 10:00:00', 'last_seen_at' => '2026-11-20 10:00:00',
            'items_status' => 'read', 'valuation' => 'single', 'main_type_id' => 19722,
        ]);
    }

    public function test_asks_seat_for_unknown_names_and_locations_of_open_capital_contracts(): void
    {
        DB::table('universe_names')->insert(['entity_id' => 90000001, 'name' => 'Known', 'category' => 'character']);
        $this->contract(1, 90000001, 98000001, 60003760);
        $this->contract(2, 90000002, 98000001, 1035466617946);
        $this->contract(3, 90000003, 98000003, 1035466617947, ['gone_at' => '2026-11-20 11:00:00']);   // gone: ignored
        $this->contract(4, 90000004, 98000004, 1035466617948, ['main_type_id' => null]);               // no capital: ignored
        DB::table('refresh_tokens')->insert(['character_id' => 7, 'scopes' => json_encode([Resolver::STRUCTURE_SCOPE])]);

        Resolver::dispatch();

        Bus::assertDispatched(Names::class, function (Names $job) {
            $ids = (new \ReflectionProperty($job, 'entity_ids'))->getValue($job)->sort()->values()->all();

            return $ids === [90000002, 98000001];
        });
        Bus::assertBatched(function ($batch) {
            $kinds = collect($batch->jobs)->map(fn ($j) => get_class($j))->sort()->values()->all();

            return $kinds === [Citadel::class, Station::class];
        });
    }

    public function test_without_a_structure_token_only_stations_are_asked_for(): void
    {
        $this->contract(1, 90000001, 98000001, 60003760);
        $this->contract(2, 90000001, 98000001, 1035466617946);

        Resolver::dispatch();

        Bus::assertBatched(fn ($batch) => collect($batch->jobs)->map(fn ($j) => get_class($j))->all() === [Station::class]);
    }
}
