<?php

namespace Glubschi\Seat\Contracts\Tests;

use Carbon\Carbon;
use Glubschi\Seat\Contracts\Jobs\ContractItems;
use Glubschi\Seat\Contracts\Jobs\DispatchContractItems;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Seat\Eseye\Containers\EsiResponse as EseyeResponse;
use Seat\Eseye\Exceptions\RequestFailedException;

class ContractItemsTest extends TestCase
{
    private function contract(int $id, array $extra = []): void
    {
        DB::table('glubschi_contracts')->insert($extra + [
            'contract_id' => $id, 'region_id' => 10000002, 'type' => 'item_exchange', 'issuer_id' => 1,
            'issuer_corporation_id' => 2, 'for_corporation' => false, 'price' => 3e9,
            'date_issued' => '2026-11-18 10:00:00', 'date_expired' => '2026-12-16 10:00:00',
            'first_seen_at' => '2026-11-20 10:00:00', 'last_seen_at' => '2026-11-20 10:00:00',
        ]);
    }

    private static function item(int $record, int $type, int $qty = 1, array $extra = []): array
    {
        return $extra + ['record_id' => $record, 'type_id' => $type, 'quantity' => $qty, 'is_included' => true];
    }

    public function test_reads_and_values_each_contract(): void
    {
        $this->contract(1);
        $this->contract(2);
        $this->jitaOrder(1, 3001, 9e6);
        $this->jitaOrder(2, 3001, 10e6);          // highest buy order counts
        $this->jitaOrder(3, 3001, 50e6, false);   // sell order: ignored
        $this->jitaOrder(4, 3001, 99e6, true, 60008494); // other station: ignored

        $this->fakeEsi(fn ($uri, $data) => self::response(match ($data['contract_id']) {
            1 => [self::item(11, 19722), self::item(12, 3001, 2)],
            2 => [self::item(21, 587, 5, ['is_blueprint_copy' => true, 'runs' => 10, 'material_efficiency' => 10, 'time_efficiency' => 20])],
        }, 1));

        (new ContractItems([1, 2]))->handle();

        $one = DB::table('glubschi_contracts')->where('contract_id', 1)->first();
        $this->assertSame('read', $one->items_status);
        $this->assertSame('single', $one->valuation);
        $this->assertSame(19722, (int) $one->main_type_id);
        $this->assertEquals(20e6, $one->extras_value);
        $this->assertEquals(2.98e9, $one->net_price);

        $two = DB::table('glubschi_contracts')->where('contract_id', 2)->first();
        $this->assertSame('none', $two->valuation);
        $bpc = DB::table('glubschi_contract_items')->where('contract_id', 2)->first();
        $this->assertSame([10, 10, 20], [(int) $bpc->runs, (int) $bpc->material_efficiency, (int) $bpc->time_efficiency]);
        $this->assertSame(3, DB::table('glubschi_contract_items')->count());
    }

    public function test_marks_contracts_esi_no_longer_shows_as_unavailable(): void
    {
        $this->contract(1);
        $this->contract(2);
        $this->contract(3);
        $connection = \Mockery::mock();
        $connection->shouldReceive('ttl')->andReturn(-2);
        Redis::shouldReceive('connection')->andReturn($connection);

        $this->fakeEsi(fn ($uri, $data) => match ($data['contract_id']) {
            1 => self::response(null, 0, 204),
            2 => throw new RequestFailedException(new EseyeResponse('{"error":"Contract not found!"}', [], 'now', 404)),
            3 => self::response([self::item(31, 587)], 1),
        });

        (new ContractItems([1, 2, 3]))->handle();

        $status = DB::table('glubschi_contracts')->orderBy('contract_id')->pluck('items_status')->all();
        $this->assertSame(['unavailable', 'unavailable', 'read'], $status);
    }

    public function test_skips_contracts_already_read(): void
    {
        $this->contract(1, ['items_status' => 'read']);
        $this->fakeEsi(fn () => $this->fail('no request expected'));

        (new ContractItems([1]))->handle();

        $this->assertSame([], $this->esiCalls);
    }

    public function test_reads_every_page_of_a_large_contract(): void
    {
        $this->contract(1);
        $this->fakeEsi(fn ($uri, $data, $page) => self::response([self::item($page, 587)], 3));

        (new ContractItems([1]))->handle();

        $this->assertSame(3, DB::table('glubschi_contract_items')->count());
    }

    public function test_dispatch_queues_open_unread_contracts_in_groups_once(): void
    {
        config(['contracts.items_per_job' => 2]);
        foreach ([1, 2, 3] as $id) {
            $this->contract($id);
        }
        $this->contract(4, ['gone_at' => '2026-11-20 09:00:00']);
        $this->contract(5, ['items_status' => 'read']);

        $now = Carbon::parse('2026-11-20 11:00:00');
        $jobs = DispatchContractItems::jobs($now);

        $this->assertSame([[1, 2], [3]], array_map(fn ($j) => $j->contractIds(), $jobs));
        $this->assertSame([], DispatchContractItems::jobs($now->copy()->addMinutes(30)));
        $this->assertCount(2, DispatchContractItems::jobs($now->copy()->addMinutes(121)));
    }
}
