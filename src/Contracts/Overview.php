<?php

namespace Glubschi\Seat\Contracts\Contracts;

use Carbon\Carbon;
use Glubschi\Seat\Contracts\Pricing\History;
use Glubschi\Seat\Contracts\Pricing\Stats;
use Glubschi\Seat\Contracts\Pricing\Valuation;
use Illuminate\Support\Facades\DB;

/**
 * What the page shows: ship class > segment (standard / faction hulls) > hull, each with its figures, and for one
 * hull its open contracts and price history.
 *
 * Works on all capital contracts that were open at some point of the history window (config
 * contracts.history_days). Expired and gone contracts are not listed, but they count for the history and for
 * finding outliers.
 *
 * A contract is counted when it has a ship price (valuation "single", above 0) that is not an outlier:
 * - below half or above twice the median of its hull over the window (from 3 contracts on, Stats::outliers), or
 * - below 10 % of the median of its class segment over the window, which also catches 1-ISK bait for rare hulls.
 *
 * Filters (security band, region, known location only) apply to everything, figures and history included.
 */
final class Overview
{
    public const SECURITY = ['high', 'low', 'null'];

    /**
     * Classes with their segments and hulls, without the contracts (those come per hull, see hull()).
     *
     * @return array<int, array{name: string, group_id: int|null, open: int, lowest: float|null, segments: array}>
     */
    public static function build(array $filters = [], ?Carbon $now = null): array
    {
        $now ??= Carbon::now();
        $data = self::prepare($filters, $now);

        $tree = [];
        foreach (Capitals::groups() as $name => $groupId) {
            $segments = [];
            foreach ([Capitals::STANDARD, Capitals::FACTION] as $segment) {
                $hulls = [];
                $prices = [];
                foreach ($data['hulls'][$groupId][$segment] ?? [] as $typeId => $hull) {
                    $open = array_values(array_filter($hull['contracts'], fn ($c) => $c['open']));
                    if ($open === []) {
                        continue;
                    }
                    $counted = array_column(array_filter($open, fn ($c) => $c['counted']), 'net_price');
                    array_push($prices, ...$counted);
                    $hulls[] = [
                        'type_id' => $typeId,
                        'name' => $hull['name'],
                        'open' => count($open),
                        'figures' => Stats::figures($counted),
                        'trend7' => History::trend($hull['contracts'], $now, 7, $data['collection_start']),
                        'trend30' => History::trend($hull['contracts'], $now, 30, $data['collection_start']),
                    ];
                }
                if ($hulls === []) {
                    continue;
                }
                usort($hulls, fn ($a, $b) => strcmp($a['name'], $b['name']));
                $segments[] = [
                    'segment' => $segment,
                    'open' => array_sum(array_column($hulls, 'open')),
                    'figures' => Stats::figures($prices),
                    'hulls' => $hulls,
                ];
            }

            $lowest = array_filter(array_map(fn ($s) => $s['figures']['lowest'], $segments), fn ($v) => $v !== null);
            $tree[] = [
                'name' => $name,
                'group_id' => $groupId,
                'open' => array_sum(array_column($segments, 'open')),
                'lowest' => $lowest === [] ? null : min($lowest),
                'segments' => $segments,
            ];
        }

        return $tree;
    }

    /**
     * The open contracts of one hull, cheapest ship price first, and its price history.
     *
     * @return array{contracts: array, series: array, collection_start: string|null}
     */
    public static function hull(int $typeId, array $filters = [], ?Carbon $now = null): array
    {
        $now ??= Carbon::now();
        $data = self::prepare($filters, $now);

        $contracts = [];
        foreach ($data['hulls'] as $segments) {
            foreach ($segments as $hulls) {
                if (isset($hulls[$typeId])) {
                    $contracts = $hulls[$typeId]['contracts'];
                }
            }
        }

        $open = array_values(array_filter($contracts, fn ($c) => $c['open']));
        usort($open, function ($a, $b) {
            $x = $a['net_price'] ?? INF;
            $y = $b['net_price'] ?? INF;

            return $x <=> $y ?: $a['contract_id'] <=> $b['contract_id'];
        });

        return [
            'contracts' => array_map(fn ($c) => array_diff_key($c, array_flip(['issued', 'closed', 'gone', 'sold'])), $open),
            'series' => History::series($contracts, $now->copy()->subDays(self::days()), $now, $data['collection_start']),
            'collection_start' => $data['collection_start']?->toDateString(),
        ];
    }

    /** Regions with open capital contracts, for the filter: [region_id => name]. */
    public static function regions(?Carbon $now = null): array
    {
        $now ??= Carbon::now();
        $ids = DB::table(PublicContracts::TABLE)
            ->whereNull('gone_at')->where('date_expired', '>', $now->toDateTimeString())->whereNotNull('main_type_id')
            ->distinct()->pluck('region_id')->all();

        return DB::table('regions')->whereIn('region_id', $ids)->orderBy('name')->pluck('name', 'region_id')
            ->mapWithKeys(fn ($name, $id) => [(int) $id => $name])->all();
    }

    /** The filters from a request, cleaned. */
    public static function filters(array $input): array
    {
        $security = $input['security'] ?? null;

        return [
            'security' => in_array($security, self::SECURITY, true) ? $security : null,
            'region' => isset($input['region']) && ctype_digit((string) $input['region']) ? (int) $input['region'] : null,
            'located' => ! empty($input['located']),
        ];
    }

    private static function days(): int
    {
        return max(7, (int) config('contracts.history_days', 90));
    }

    /**
     * All capital contracts of the window as arrays, filtered, with outliers marked, by group, segment and hull.
     *
     * @return array{hulls: array<int, array<string, array<int, array{name: string, contracts: array}>>>, collection_start: Carbon|null}
     */
    private static function prepare(array $filters, Carbon $now): array
    {
        $filters = self::filters($filters);
        $capitals = array_keys(Capitals::typeIds());
        $from = $now->copy()->subDays(self::days())->startOfDay();

        $first = DB::table(PublicContracts::TABLE)->min('first_seen_at');
        $collectionStart = $first === null ? null : Carbon::parse($first);

        $rows = $capitals === [] ? collect() : DB::table(PublicContracts::TABLE . ' as c')
            ->join('invTypes as t', 't.typeID', '=', 'c.main_type_id')
            ->whereIn('c.main_type_id', $capitals)
            ->where('c.date_expired', '>=', $from->toDateTimeString())
            ->where(fn ($q) => $q->whereNull('c.gone_at')->orWhere('c.gone_at', '>=', $from->toDateTimeString()))
            ->when($filters['region'], fn ($q, $region) => $q->where('c.region_id', $region))
            ->get(['c.*', 't.typeName as hull', 't.groupID as group_id']);

        $locations = Locations::resolve($rows->pluck('start_location_id')->map(fn ($id) => (int) $id)->all());
        $regions = DB::table('regions')->whereIn('region_id', $rows->pluck('region_id')->unique()->all())->pluck('name', 'region_id');
        $issuers = DB::table('universe_names')
            ->whereIn('entity_id', $rows->pluck('issuer_id')->concat($rows->pluck('issuer_corporation_id'))->unique()->all())
            ->pluck('name', 'entity_id');

        $hulls = [];
        foreach ($rows as $row) {
            $c = self::contract($row, $locations, $regions, $issuers, $now);
            if (! self::passes($c, $filters)) {
                continue;
            }
            $typeId = (int) $row->main_type_id;
            $slot = &$hulls[(int) $row->group_id][Capitals::segment($typeId)][$typeId];
            $slot['name'] = $row->hull;
            $slot['contracts'][] = $c;
            unset($slot);
        }

        foreach ($hulls as &$segments) {
            foreach ($segments as &$byHull) {
                self::markOutliers($byHull);
            }
        }
        unset($segments, $byHull);

        return ['hulls' => $hulls, 'collection_start' => $collectionStart];
    }

    /** Marks outliers and counted contracts in all hulls of one class segment. */
    private static function markOutliers(array &$byHull): void
    {
        $segmentPrices = [];
        $outliers = [];
        foreach ($byHull as $hull) {
            $priced = [];
            foreach ($hull['contracts'] as $c) {
                if ($c['valuation'] === Valuation::SINGLE && $c['net_price'] !== null && $c['net_price'] > 0) {
                    $priced[$c['contract_id']] = $c['net_price'];
                }
            }
            $outliers += Stats::outliers($priced);
            array_push($segmentPrices, ...array_values($priced));
        }
        $floor = $segmentPrices === [] ? 0.0 : Stats::median($segmentPrices) * Stats::FLOOR;

        foreach ($byHull as &$hull) {
            foreach ($hull['contracts'] as &$c) {
                $priced = $c['valuation'] === Valuation::SINGLE && $c['net_price'] !== null && $c['net_price'] > 0;
                $c['outlier'] = $priced && (isset($outliers[$c['contract_id']]) || $c['net_price'] < $floor);
                $c['counted'] = $priced && ! $c['outlier'];
            }
        }
    }

    private static function passes(array $c, array $filters): bool
    {
        if ($filters['located'] && $c['location'] === null) {
            return false;
        }
        if ($filters['security'] !== null) {
            if ($c['security'] === null) {
                return false;
            }
            $band = self::band($c['security']);
            if ($band !== $filters['security']) {
                return false;
            }
        }

        return true;
    }

    /** Security band as the game rounds it: 0.45 and up is high-sec, above 0 low-sec, else null-sec. */
    public static function band(float $security): string
    {
        return $security >= 0.45 ? 'high' : ($security > 0 ? 'low' : 'null');
    }

    private static function contract(object $row, array $locations, $regions, $issuers, Carbon $now): array
    {
        $locationId = (int) $row->start_location_id;
        $location = $locations[$locationId] ?? null;
        $issued = Carbon::parse($row->date_issued);
        $expires = Carbon::parse($row->date_expired);
        $gone = $row->gone_at === null ? null : Carbon::parse($row->gone_at);
        $closed = $gone !== null && $gone->lt($expires) ? $gone : $expires;

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
            'extras_rigs' => (int) ($row->extras_rigs ?? 0),
            'location' => $location['name'] ?? null,
            'structure' => Locations::isStructure($locationId),
            'system' => $location['system'] ?? null,
            'security' => $location['security'] ?? null,
            'region' => $regions[$row->region_id] ?? null,
            // A contract for the corporation shows the corporation, as the game does.
            'issuer' => $issuers[$row->for_corporation ? $row->issuer_corporation_id : $row->issuer_id] ?? null,
            'for_corporation' => (bool) $row->for_corporation,
            'issued_date' => $issued->toDateString(),
            'expires' => $expires->toDateString(),
            'days_left' => max(0, (int) floor($now->diffInHours($expires, false) / 24)),
            'open' => $gone === null && $expires->gt($now),
            // For the history (History): timestamps, and whether it was probably sold.
            'issued' => $issued->getTimestamp(),
            'closed' => $closed->getTimestamp(),
            'gone' => $gone?->getTimestamp(),
            'sold' => $gone !== null && $gone->getTimestamp() < $expires->getTimestamp() - History::SOLD_MARGIN,
        ];
    }
}
