<?php

namespace Glubschi\Seat\Contracts\Contracts;

use Illuminate\Support\Facades\DB;

/**
 * What the plugin needs to know about item types from SeAT's SDE:
 *
 * - the ship types the pages show (config contracts.groups), by group name rather than by ID so the list in the
 *   config stays readable; the page shows a name that the SDE does not know;
 * - rigs: types with the dogma effect "rigSlot" (ID 2663). A rig fitted to an assembled ship is destroyed when it
 *   is taken out, so it cannot be sold and counts as 0 ISK;
 * - faction hulls: meta group Storyline, Faction, Officer or Deadspace (invMetaTypes). Everything else, including
 *   types without a meta group, is a standard hull.
 *
 * Cached per process; a queue worker keeps them until it restarts, which SeAT does on every update.
 */
final class Capitals
{
    public const RIG_EFFECT = 2663;

    public const FACTION_META_GROUPS = [3, 4, 5, 6];

    public const STANDARD = 'standard';

    public const FACTION = 'faction';

    /** @var array<string, array<int, true>> */
    private static array $cache = [];

    /** Type IDs of all types in the configured groups, as a set. */
    public static function typeIds(): array
    {
        return self::$cache['types'] ??= self::set(
            DB::table('invTypes')
                ->join('invGroups', 'invGroups.groupID', '=', 'invTypes.groupID')
                ->whereIn('invGroups.groupName', config('contracts.groups', []))
                ->pluck('invTypes.typeID')
        );
    }

    /** Type IDs of all rigs, as a set. */
    public static function rigTypeIds(): array
    {
        return self::$cache['rigs'] ??= self::set(
            DB::table('dgmTypeEffects')->where('effectID', self::RIG_EFFECT)->pluck('typeID')
        );
    }

    /** Type IDs of faction hulls among the configured groups, as a set. */
    public static function factionTypeIds(): array
    {
        $types = array_keys(self::typeIds());

        return self::$cache['faction'] ??= $types === [] ? [] : self::set(
            DB::table('invMetaTypes')->whereIn('typeID', $types)->whereIn('metaGroupID', self::FACTION_META_GROUPS)->pluck('typeID')
        );
    }

    public static function segment(int $typeId): string
    {
        return isset(self::factionTypeIds()[$typeId]) ? self::FACTION : self::STANDARD;
    }

    /**
     * The configured group names with their ID in the SDE, or null when the SDE has no such group.
     *
     * @return array<string, int|null>
     */
    public static function groups(): array
    {
        $names = config('contracts.groups', []);
        $found = DB::table('invGroups')->whereIn('groupName', $names)->pluck('groupID', 'groupName')->all();

        $groups = [];
        foreach ($names as $name) {
            $groups[$name] = isset($found[$name]) ? (int) $found[$name] : null;
        }

        return $groups;
    }

    /** Forgets the cached sets (tests). */
    public static function flush(): void
    {
        self::$cache = [];
    }

    private static function set($ids): array
    {
        return array_fill_keys($ids->map(fn ($id) => (int) $id)->all(), true);
    }
}
