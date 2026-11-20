<?php

namespace Glubschi\Seat\Contracts\Contracts;

use Carbon\Carbon;
use Glubschi\Seat\Contracts\Pricing\Stats;
use Glubschi\Seat\Contracts\Pricing\Valuation;
use Illuminate\Support\Facades\DB;

/**
 * The tree the page shows: ship class > hull > contract, each level with its price figures.
 *
 * Only open contracts count: not gone from the region lists and not past their expiry date. Expired contracts
 * stay in the table for the price history (0.3) but are not listed here.
 *
 * A contract is counted in the figures when it has a ship price (valuation "single", price above 0) and that
 * price is not an outlier for its hull (Pricing\Stats). The class figures are taken over the counted contracts
 * of all its hulls.
 */
final class Overview
{
    /**
     * @return array<int, array{name: string, group_id: int|null, figures: array, hulls: array}>
     */
    public static function build(?Carbon $now = null): array
    {
        $now ??= Carbon::now();
        $capitals = array_keys(Capitals::typeIds());

        $rows = $capitals === [] ? collect() : DB::table(PublicContracts::TABLE . ' as c')
            ->join('invTypes as t', 't.typeID', '=', 'c.main_type_id')
            ->whereNull('c.gone_at')
            ->where('c.date_expired', '>', $now->toDateTimeString())
            ->whereIn('c.main_type_id', $capitals)
            ->get(['c.*', 't.typeName as hull', 't.groupID as group_id']);

        $locations = Locations::resolve($rows->pluck('start_location_id')->map(fn ($id) => (int) $id)->all());
        $regions = DB::table('regions')->whereIn('region_id', $rows->pluck('region_id')->unique()->all())->pluck('name', 'region_id');
        $issuers = DB::table('universe_names')
            ->whereIn('entity_id', $rows->pluck('issuer_id')->concat($rows->pluck('issuer_corporation_id'))->unique()->all())
            ->pluck('name', 'entity_id');

        // Hulls by group, each with its contracts.
        $byGroup = [];
        foreach ($rows as $row) {
            $byGroup[(int) $row->group_id][(int) $row->main_type_id]['name'] = $row->hull;
            $byGroup[(int) $row->group_id][(int) $row->main_type_id]['contracts'][] = self::contract($row, $locations, $regions, $issuers, $now);
        }

        $tree = [];
        foreach (Capitals::groups() as $name => $groupId) {
            $hulls = [];
            $groupPrices = [];
            foreach ($byGroup[$groupId] ?? [] as $typeId => $hull) {
                $contracts = self::mark($hull['contracts']);
                $prices = array_column(array_filter($contracts, fn ($c) => $c['counted']), 'net_price');
                array_push($groupPrices, ...$prices);
                $hulls[] = [
                    'type_id' => $typeId,
                    'name' => $hull['name'],
                    'open' => count($contracts),
                    'figures' => Stats::figures($prices),
                    'contracts' => $contracts,
                ];
            }
            usort($hulls, fn ($a, $b) => strcmp($a['name'], $b['name']));

            $tree[] = [
                'name' => $name,
                'group_id' => $groupId,
                'open' => array_sum(array_column($hulls, 'open')),
                'figures' => Stats::figures($groupPrices),
                'hulls' => $hulls,
            ];
        }

        return $tree;
    }

    /**
     * Marks outliers and counted contracts of one hull, cheapest ship price first, contracts without a ship
     * price last.
     */
    private static function mark(array $contracts): array
    {
        $priced = [];
        foreach ($contracts as $c) {
            if ($c['valuation'] === Valuation::SINGLE && $c['net_price'] !== null && $c['net_price'] > 0) {
                $priced[$c['contract_id']] = $c['net_price'];
            }
        }
        $outliers = Stats::outliers($priced);

        foreach ($contracts as &$c) {
            $c['outlier'] = isset($outliers[$c['contract_id']]);
            $c['counted'] = isset($priced[$c['contract_id']]) && ! $c['outlier'];
        }
        unset($c);

        usort($contracts, function ($a, $b) {
            $x = $a['net_price'] ?? INF;
            $y = $b['net_price'] ?? INF;

            return $x <=> $y ?: $a['contract_id'] <=> $b['contract_id'];
        });

        return $contracts;
    }

    private static function contract(object $row, array $locations, $regions, $issuers, Carbon $now): array
    {
        $locationId = (int) $row->start_location_id;
        $location = $locations[$locationId] ?? null;
        $expires = Carbon::parse($row->date_expired);

        return [
            'contract_id' => (int) $row->contract_id,
            'type' => $row->type,
            'title' => $row->title,
            'price' => $row->price === null ? null : (float) $row->price,
            'buyout' => $row->buyout === null ? null : (float) $row->buyout,
            'valuation' => $row->valuation,
            'net_price' => $row->net_price === null ? null : (float) $row->net_price,
            'extras_value' => $row->extras_value === null ? null : (float) $row->extras_value,
            'extras_unpriced' => (int) ($row->extras_unpriced ?? 0),
            'location' => $location['name'] ?? null,
            'structure' => Locations::isStructure($locationId),
            'system' => $location['system'] ?? null,
            'security' => $location['security'] ?? null,
            'region' => $regions[$row->region_id] ?? null,
            // A contract for the corporation shows the corporation, as the game does.
            'issuer' => $issuers[$row->for_corporation ? $row->issuer_corporation_id : $row->issuer_id] ?? null,
            'for_corporation' => (bool) $row->for_corporation,
            'issued' => Carbon::parse($row->date_issued)->toDateString(),
            'expires' => $expires->toDateString(),
            'days_left' => max(0, (int) floor($now->diffInHours($expires, false) / 24)),
        ];
    }
}
