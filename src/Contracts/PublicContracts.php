<?php

namespace Glubschi\Seat\Contracts\Contracts;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The plugin's table of public contracts: writes what a region list from ESI says, and remembers when a
 * contract was gone.
 *
 * Only item exchange and auction contracts are kept: couriers and loans have no price for an item.
 */
final class PublicContracts
{
    public const TABLE = 'glubschi_contracts';

    public const ITEMS_TABLE = 'glubschi_contract_items';

    public const TYPES = ['item_exchange', 'auction'];

    /**
     * Rows for the table from one page of /contracts/public/{region_id}/.
     *
     * @param  iterable<object>  $contracts  ESI contract objects
     * @return array<int, array<string, mixed>>
     */
    public static function rows(iterable $contracts, int $regionId, Carbon $now): array
    {
        $rows = [];
        foreach ($contracts as $c) {
            if (! in_array($c->type ?? null, self::TYPES, true)) {
                continue;
            }
            $rows[] = [
                'contract_id' => (int) $c->contract_id,
                'region_id' => $regionId,
                'type' => $c->type,
                'title' => isset($c->title) && $c->title !== '' ? mb_substr($c->title, 0, 255) : null,
                'issuer_id' => (int) $c->issuer_id,
                'issuer_corporation_id' => (int) $c->issuer_corporation_id,
                'for_corporation' => (bool) ($c->for_corporation ?? false),
                'start_location_id' => isset($c->start_location_id) ? (int) $c->start_location_id : null,
                'price' => isset($c->price) ? (float) $c->price : null,
                'buyout' => isset($c->buyout) ? (float) $c->buyout : null,
                'volume' => isset($c->volume) ? (float) $c->volume : null,
                'date_issued' => self::date($c->date_issued),
                'date_expired' => self::date($c->date_expired),
                'first_seen_at' => $now->toDateTimeString(),
                'last_seen_at' => $now->toDateTimeString(),
                'gone_at' => null,
            ];
        }

        return $rows;
    }

    /**
     * Inserts new contracts and moves last_seen_at of known ones. first_seen_at, the contents and the
     * valuation of a known contract are left as they are. A contract that was gone and is listed again (ESI
     * served an older page) is open again.
     */
    public static function store(array $rows): void
    {
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table(self::TABLE)->upsert($chunk, ['contract_id'], ['last_seen_at', 'gone_at']);
        }
    }

    /**
     * Marks the open contracts of a region that the latest list did not contain as gone.
     *
     * @return int  how many were marked
     */
    public static function markGone(int $regionId, Carbon $listedSince, Carbon $now): int
    {
        return DB::table(self::TABLE)
            ->where('region_id', $regionId)
            ->whereNull('gone_at')
            ->where('last_seen_at', '<', $listedSince->toDateTimeString())
            ->update(['gone_at' => $now->toDateTimeString()]);
    }

    /** ESI dates are ISO 8601 in UTC; the tables keep them as UTC DATETIME, like SeAT does. */
    private static function date(string $value): string
    {
        return Carbon::parse($value)->utc()->toDateTimeString();
    }
}
