<?php

namespace Glubschi\Seat\Contracts\Tests;

use Carbon\Carbon;
use Glubschi\Seat\Contracts\Jobs\RegionContracts;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Seat\Eseye\Containers\EsiResponse as EseyeResponse;
use Seat\Eseye\Exceptions\RequestFailedException;

class RegionContractsTest extends TestCase
{
    private const FORGE = 10000002;

    private static function contract(int $id, string $type = 'item_exchange', array $extra = []): array
    {
        return $extra + [
            'contract_id' => $id,
            'type' => $type,
            'title' => 'Naglfar fit',
            'issuer_id' => 90000001,
            'issuer_corporation_id' => 98000001,
            'for_corporation' => false,
            'start_location_id' => 60003760,
            'price' => 3.1e9,
            'volume' => 1_300_000,
            'date_issued' => '2026-11-18T10:00:00Z',
            'date_expired' => '2026-12-16T10:00:00Z',
        ];
    }

    private function readPages(array $pages): void
    {
        $this->fakeEsi(fn ($uri, $data, $page) => self::response($pages[$page - 1], count($pages)));
        (new RegionContracts(self::FORGE))->handle();
    }

    public function test_stores_item_exchange_and_auctions_of_all_pages_but_no_couriers(): void
    {
        $this->readPages([
            [self::contract(1), self::contract(2, 'courier')],
            [self::contract(3, 'auction', ['buyout' => 4e9]), self::contract(4, 'loan')],
        ]);

        $rows = DB::table('glubschi_contracts')->orderBy('contract_id')->get();
        $this->assertSame([1, 3], $rows->pluck('contract_id')->map(fn ($v) => (int) $v)->all());
        $this->assertSame('2026-12-16 10:00:00', $rows[0]->date_expired);
        $this->assertEquals(4e9, $rows[1]->buyout);
        $this->assertSame('pending', $rows[0]->items_status);
        $this->assertSame([[1, self::FORGE], [2, self::FORGE]], array_map(fn ($c) => [$c[3], $c[2]['region_id']], $this->esiCalls));
    }

    public function test_marks_contracts_that_are_no_longer_listed_as_gone_and_keeps_first_seen(): void
    {
        Carbon::setTestNow('2026-11-20 10:00:00');
        $this->readPages([[self::contract(1), self::contract(2)]]);

        Carbon::setTestNow('2026-11-20 10:30:00');
        $this->readPages([[self::contract(2), self::contract(5)]]);

        $rows = DB::table('glubschi_contracts')->get()->keyBy('contract_id');
        $this->assertSame('2026-11-20 10:30:00', $rows[1]->gone_at);
        $this->assertNull($rows[2]->gone_at);
        $this->assertSame('2026-11-20 10:00:00', $rows[2]->first_seen_at);
        $this->assertSame('2026-11-20 10:30:00', $rows[2]->last_seen_at);
        $this->assertNull($rows[5]->gone_at);
        Carbon::setTestNow();
    }

    public function test_leaves_other_regions_alone(): void
    {
        $this->readPages([[self::contract(1)]]);
        DB::table('glubschi_contracts')->update(['region_id' => 10000043, 'last_seen_at' => '2020-01-01 00:00:00']);

        $this->readPages([[self::contract(7)]]);

        $this->assertNull(DB::table('glubschi_contracts')->where('contract_id', 1)->value('gone_at'));
    }

    public function test_a_failed_page_marks_nothing_as_gone(): void
    {
        $this->readPages([[self::contract(1)]]);
        DB::table('glubschi_contracts')->update(['last_seen_at' => '2020-01-01 00:00:00']);
        $this->stubRedis();

        $this->fakeEsi(function ($uri, $data, $page) {
            if ($page === 2) {
                throw new RequestFailedException(new EseyeResponse('{"error":"boom"}', [], 'now', 500));
            }

            return self::response([self::contract(9)], 2);
        });

        try {
            (new RegionContracts(self::FORGE))->handle();
            $this->fail('expected the ESI error to bubble up');
        } catch (\Throwable) {
        }

        $this->assertNull(DB::table('glubschi_contracts')->where('contract_id', 1)->value('gone_at'));
    }

    public function test_a_region_without_contracts_on_esi_is_skipped(): void
    {
        $this->stubRedis();
        $this->fakeEsi(fn () => throw new RequestFailedException(new EseyeResponse('{"error":"Not found"}', [], 'now', 404)));

        (new RegionContracts(12000001))->handle();

        $this->assertSame(0, DB::table('glubschi_contracts')->count());
    }

    /** SeAT counts ESI errors in Redis (EsiBase::incrementEsiRateLimit). */
    private function stubRedis(): void
    {
        $connection = \Mockery::mock();
        $connection->shouldReceive('ttl')->andReturn(-2);
        Redis::shouldReceive('connection')->andReturn($connection);
    }
}
