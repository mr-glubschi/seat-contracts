<?php

namespace Glubschi\Seat\Contracts\Contracts;

use Glubschi\Seat\Contracts\Pricing\JitaBuy;
use Glubschi\Seat\Contracts\Pricing\Valuation;
use Illuminate\Support\Facades\DB;

/**
 * Figures for the status page of v0.1: how far the collection is, and how many capital contracts it found.
 */
final class Status
{
    /**
     * @return array{
     *     last_seen: string|null, open: int, gone: int, read: int, pending: int, unavailable: int, items: int,
     *     jita: bool, groups: array<int, array{name: string, found: bool, open: int, priced: int, lowest: float|null}>
     * }
     */
    public static function figures(): array
    {
        $open = DB::table(PublicContracts::TABLE)->whereNull('gone_at')
            ->selectRaw('items_status, COUNT(*) as n')->groupBy('items_status')->pluck('n', 'items_status');

        return [
            'last_seen' => DB::table(PublicContracts::TABLE)->max('last_seen_at'),
            'open' => (int) $open->sum(),
            'gone' => DB::table(PublicContracts::TABLE)->whereNotNull('gone_at')->count(),
            'read' => (int) ($open['read'] ?? 0),
            'pending' => (int) ($open['pending'] ?? 0),
            'unavailable' => (int) ($open['unavailable'] ?? 0),
            'items' => DB::table(PublicContracts::ITEMS_TABLE)->count(),
            'jita' => JitaBuy::available(),
            'groups' => self::groups(),
        ];
    }

    /** Open contracts per configured group: all with a ship of the group, those with a ship price, the lowest. */
    private static function groups(): array
    {
        $byGroup = DB::table(PublicContracts::TABLE . ' as c')
            ->join('invTypes as t', 't.typeID', '=', 'c.main_type_id')
            ->whereNull('c.gone_at')
            ->whereNotNull('c.main_type_id')
            ->groupBy('t.groupID')
            ->selectRaw('t.groupID as group_id, COUNT(*) as open')
            ->selectRaw("SUM(CASE WHEN c.valuation = ? AND c.net_price IS NOT NULL THEN 1 ELSE 0 END) as priced", [Valuation::SINGLE])
            ->selectRaw("MIN(CASE WHEN c.valuation = ? AND c.net_price > 0 THEN c.net_price END) as lowest", [Valuation::SINGLE])
            ->get()
            ->keyBy('group_id');

        $groups = [];
        foreach (Capitals::groups() as $name => $groupId) {
            $row = $groupId === null ? null : ($byGroup[$groupId] ?? null);
            $groups[] = [
                'name' => $name,
                'found' => $groupId !== null,
                'open' => (int) ($row->open ?? 0),
                'priced' => (int) ($row->priced ?? 0),
                'lowest' => isset($row->lowest) ? (float) $row->lowest : null,
            ];
        }

        return $groups;
    }
}
