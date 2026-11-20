<?php

namespace Glubschi\Seat\Contracts\Contracts;

use Glubschi\Seat\Contracts\Pricing\JitaBuy;
use Illuminate\Support\Facades\DB;

/**
 * The contents of one contract for the page, with item names and today's Jita buy value.
 *
 * Rigs have no value here: they count as 0 ISK, as in the ship price.
 *
 * The ship price itself was computed with the Jita buy prices of the day the contents were read; the value
 * shown here is today's and can differ a little.
 */
final class Contents
{
    /**
     * @return array<int, array{type_id: int, name: string, quantity: int, included: bool, capital: bool, bpc: bool, rig: bool, runs: int|null, me: int|null, te: int|null, value: float|null}>|null
     *     null when the contract is not in the plugin's table
     */
    public static function of(int $contractId): ?array
    {
        if (! DB::table(PublicContracts::TABLE)->where('contract_id', $contractId)->exists()) {
            return null;
        }

        $items = DB::table(PublicContracts::ITEMS_TABLE . ' as i')
            ->leftJoin('invTypes as t', 't.typeID', '=', 'i.type_id')
            ->where('i.contract_id', $contractId)
            ->orderBy('i.record_id')
            ->get(['i.*', 't.typeName']);

        $prices = JitaBuy::prices($items->pluck('type_id')->map(fn ($id) => (int) $id)->all());
        $capitals = Capitals::typeIds();
        $rigs = Capitals::rigTypeIds();

        $out = [];
        foreach ($items as $i) {
            $bpc = (bool) $i->is_blueprint_copy;
            $rig = isset($rigs[(int) $i->type_id]);
            // Rigs count as 0 ISK (destroyed when taken out of the ship), as in the ship price.
            $unit = $bpc || $rig ? null : ($prices[(int) $i->type_id] ?? null);
            $out[] = [
                'type_id' => (int) $i->type_id,
                'name' => $i->typeName ?? ('#' . $i->type_id),
                'quantity' => (int) $i->quantity,
                'included' => (bool) $i->is_included,
                'capital' => isset($capitals[(int) $i->type_id]),
                'bpc' => $bpc,
                'rig' => $rig,
                'runs' => $i->runs === null ? null : (int) $i->runs,
                'me' => $i->material_efficiency === null ? null : (int) $i->material_efficiency,
                'te' => $i->time_efficiency === null ? null : (int) $i->time_efficiency,
                'value' => $unit === null ? null : $unit * (int) $i->quantity,
            ];
        }

        // Ships first, then by value, items without a price last.
        usort($out, fn ($a, $b) => [$b['capital'], $b['value'] ?? -1] <=> [$a['capital'], $a['value'] ?? -1]);

        return $out;
    }
}
