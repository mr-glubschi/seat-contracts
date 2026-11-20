<?php

namespace Glubschi\Seat\Contracts\Tests;

use Glubschi\Seat\Contracts\Contracts\Capitals;
use Glubschi\Seat\Contracts\ContractsServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use Seat\Eveapi\Containers\EsiResponse;
use Seat\Services\Contracts\EsiClient;

/**
 * Boots a bare Laravel app with only the Contracts provider. SeAT's own providers are not loaded: they need
 * MariaDB, Redis and the SDE. The plugin's migrations run in SQLite; the SeAT tables it reads are created here.
 */
abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    /** Requests the fake ESI client got: [method, uri, uri_data, page]. */
    protected array $esiCalls = [];

    protected function getPackageProviders($app): array
    {
        return [ContractsServiceProvider::class];
    }

    /** SQLite in memory instead of MariaDB, and the array cache instead of Redis. */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $app['config']->set('cache.default', 'array');

        // SeAT's ESI client is bound by SeAT's own providers, which these tests do not boot.
        $app->instance(EsiClient::class, \Mockery::mock(EsiClient::class));
    }

    protected function setUp(): void
    {
        parent::setUp();
        Capitals::flush();
        // SeAT's EsiBase queues analytics jobs for odd responses (e.g. a 204 without X-Pages); keep them out.
        Queue::fake();
        $this->seatTables();
    }

    /** The SeAT and SDE tables the plugin reads, with only the columns it uses. */
    private function seatTables(): void
    {
        Schema::create('invGroups', function (Blueprint $t) {
            $t->integer('groupID')->primary();
            $t->string('groupName');
        });
        Schema::create('invTypes', function (Blueprint $t) {
            $t->integer('typeID')->primary();
            $t->integer('groupID');
            $t->string('typeName');
        });
        Schema::create('regions', function (Blueprint $t) {
            $t->integer('region_id')->primary();
            $t->string('name');
        });
        Schema::create('market_orders', function (Blueprint $t) {
            $t->bigInteger('order_id')->primary();
            $t->boolean('is_buy_order');
            $t->bigInteger('location_id');
            $t->decimal('price', 30, 2);
            $t->bigInteger('type_id');
        });

        Schema::create('staStations', function (Blueprint $t) {
            $t->integer('stationID')->primary();
            $t->string('stationName');
            $t->integer('solarSystemID');
        });
        Schema::create('solar_systems', function (Blueprint $t) {
            $t->integer('system_id')->primary();
            $t->integer('region_id');
            $t->string('name');
            $t->double('security');
        });
        Schema::create('universe_structures', function (Blueprint $t) {
            $t->bigInteger('structure_id')->primary();
            $t->string('name');
            $t->integer('solar_system_id');
        });
        Schema::create('universe_stations', function (Blueprint $t) {
            $t->bigInteger('station_id')->primary();
            $t->string('name');
        });
        Schema::create('universe_names', function (Blueprint $t) {
            $t->bigInteger('entity_id')->primary();
            $t->string('name');
            $t->string('category');
        });
        Schema::create('character_onlines', function (Blueprint $t) {
            $t->bigInteger('character_id')->primary();
            $t->boolean('online');
        });

        // Two capital groups and one ordinary one.
        DB::table('invGroups')->insert([
            ['groupID' => 485, 'groupName' => 'Dreadnought'],
            ['groupID' => 547, 'groupName' => 'Carrier'],
            ['groupID' => 25, 'groupName' => 'Frigate'],
        ]);
        DB::table('invTypes')->insert([
            ['typeID' => 19722, 'groupID' => 485, 'typeName' => 'Naglfar'],
            ['typeID' => 23757, 'groupID' => 547, 'typeName' => 'Archon'],
            ['typeID' => 587, 'groupID' => 25, 'typeName' => 'Rifter'],
        ]);
    }

    /** A Jita 4-4 order. */
    protected function jitaOrder(int $id, int $typeId, float $price, bool $buy = true, ?int $location = null): void
    {
        DB::table('market_orders')->insert([
            'order_id' => $id, 'is_buy_order' => $buy, 'location_id' => $location ?? 60003760, 'price' => $price, 'type_id' => $typeId,
        ]);
    }

    /** An ESI response as SeAT's client returns it. */
    protected static function response(mixed $body, int $pages = 0, int $status = 200): EsiResponse
    {
        $headers = $pages > 0 ? ['X-Pages' => [(string) $pages]] : [];

        return new EsiResponse($body === null ? '' : json_encode($body), $headers, $status, false);
    }

    /**
     * Replaces SeAT's ESI client with one that answers from $answer(uri, uri_data, page), which returns an
     * EsiResponse or throws.
     */
    protected function fakeEsi(callable $answer): void
    {
        $page = 1;
        $client = \Mockery::mock(EsiClient::class);
        $client->shouldReceive('setCompatibilityDate', 'setBody', 'setQueryString')->andReturnSelf();
        $client->shouldReceive('page')->andReturnUsing(function ($p) use (&$page, $client) {
            $page = $p;

            return $client;
        });
        $client->shouldReceive('getLogger')->andReturn(new \Psr\Log\NullLogger());
        $client->shouldReceive('invoke')->andReturnUsing(function ($method, $uri, $data) use (&$page, $answer) {
            $this->esiCalls[] = [$method, $uri, $data, $page];

            return $answer($uri, $data, $page);
        });
        $this->app->instance(EsiClient::class, $client);
    }
}
