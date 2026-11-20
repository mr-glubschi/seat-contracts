<?php

namespace Glubschi\Seat\Contracts\Pricing;

use Illuminate\Support\Facades\DB;

/**
 * Jita buy: the highest buy order in Jita 4-4, what an item brings when it is sold right away.
 *
 * Read from SeAT's own table market_orders, which SeAT's job esi:update:prices fills once a day with every order
 * of the market region (The Forge unless an admin chose another one under Settings). So the plugin needs no
 * market requests of its own. Only buy orders placed in the station count; buy orders elsewhere whose range
 * reaches Jita 4-4 are not included.
 */
final class JitaBuy
{
    /**
     * @param  int[]  $typeIds
     * @return array<int, float>  type ID => price; a type nobody buys in Jita is missing
     */
    public static function prices(array $typeIds): array
    {
        if ($typeIds === []) {
            return [];
        }

        return DB::table('market_orders')
            ->where('location_id', config('contracts.jita_station_id'))
            ->where('is_buy_order', true)
            ->whereIn('type_id', array_values(array_unique($typeIds)))
            ->groupBy('type_id')
            ->selectRaw('type_id, MAX(price) as price')
            ->pluck('price', 'type_id')
            ->mapWithKeys(fn ($price, $typeId) => [(int) $typeId => (float) $price])
            ->all();
    }

    /** False when SeAT has no Jita buy orders at all (other market region, or prices never read). */
    public static function available(): bool
    {
        return DB::table('market_orders')
            ->where('location_id', config('contracts.jita_station_id'))
            ->where('is_buy_order', true)
            ->exists();
    }
}
