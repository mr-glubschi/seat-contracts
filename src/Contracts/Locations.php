<?php

namespace Glubschi\Seat\Contracts\Contracts;

use Illuminate\Support\Facades\DB;

/**
 * Names for the start locations of contracts: NPC stations from the SDE (staStations), Upwell structures from
 * SeAT's universe_structures, each with its solar system and security status from solar_systems.
 *
 * SeAT only knows a structure when one of its characters could read it, and public contracts of capitals are
 * mostly in null-sec structures. An unknown structure therefore has no name and no system, only its region
 * (from the contract).
 */
final class Locations
{
    /**
     * @param  int[]  $ids
     * @return array<int, array{name: string, system: string|null, security: float|null}>
     */
    public static function resolve(array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids)));
        if ($ids === []) {
            return [];
        }

        $found = [];
        $stations = DB::table('staStations as s')
            ->leftJoin('solar_systems as y', 'y.system_id', '=', 's.solarSystemID')
            ->whereIn('s.stationID', $ids)
            ->get(['s.stationID as id', 's.stationName as name', 'y.name as system', 'y.security']);
        $structures = DB::table('universe_structures as s')
            ->leftJoin('solar_systems as y', 'y.system_id', '=', 's.solar_system_id')
            ->whereIn('s.structure_id', $ids)
            ->get(['s.structure_id as id', 's.name', 'y.name as system', 'y.security']);

        foreach ($stations->concat($structures) as $row) {
            $found[(int) $row->id] = [
                'name' => $row->name,
                'system' => $row->system,
                'security' => $row->security === null ? null : (float) $row->security,
            ];
        }

        return $found;
    }

    /** NPC station IDs are below 100 million; everything above is a player structure. */
    public static function isStructure(int $id): bool
    {
        return $id > 100_000_000;
    }
}
