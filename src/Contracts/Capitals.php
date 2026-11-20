<?php

namespace Glubschi\Seat\Contracts\Contracts;

use Illuminate\Support\Facades\DB;

/**
 * The ship types the pages show (config contracts.groups), read from SeAT's SDE by group name.
 *
 * By name rather than by ID so the list in the config stays readable; the status page shows a name that the
 * SDE does not know.
 */
final class Capitals
{
    /** @var array<int, true>|null */
    private static ?array $types = null;

    /** Type IDs of all types in the configured groups, as a set. */
    public static function typeIds(): array
    {
        return self::$types ??= array_fill_keys(
            DB::table('invTypes')
                ->join('invGroups', 'invGroups.groupID', '=', 'invTypes.groupID')
                ->whereIn('invGroups.groupName', config('contracts.groups', []))
                ->pluck('invTypes.typeID')
                ->map(fn ($id) => (int) $id)
                ->all(),
            true,
        );
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

    /** Forgets the cached type IDs (tests; a queue worker keeps them until it restarts). */
    public static function flush(): void
    {
        self::$types = null;
    }
}
