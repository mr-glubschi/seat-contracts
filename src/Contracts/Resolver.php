<?php

namespace Glubschi\Seat\Contracts\Contracts;

use Illuminate\Support\Facades\DB;
use Seat\Eveapi\Jobs\Universe\Names;
use Seat\Eveapi\Jobs\Universe\Structures\StructureBatch;
use Seat\Eveapi\Models\RefreshToken;

/**
 * Asks SeAT's own jobs for the names the page shows for open capital contracts and that SeAT does not know yet:
 *
 * - issuers and their corporations: SeAT's Universe\Names (POST /universe/names/, public), into universe_names;
 * - stations and Upwell structures: SeAT's StructureBatch, into universe_stations / universe_structures. A
 *   structure can only be read with the token of a character who may dock there. Each run uses the next
 *   character with the scope, so over time every one of SeAT's characters is tried; SeAT remembers who was
 *   refused (CitadelAccessCache) and does not ask again for that one.
 *
 * Runs after every update (DispatchContractItems), for the contracts valued in earlier runs.
 */
final class Resolver
{
    public const STRUCTURE_SCOPE = 'esi-universe.read_structures.v1';

    private const TURN_KEY = 'glubschi.contracts.structure-token-turn';

    public static function dispatch(): void
    {
        $open = DB::table(PublicContracts::TABLE)
            ->whereNull('gone_at')
            ->whereNotNull('main_type_id')
            ->get(['issuer_id', 'issuer_corporation_id', 'start_location_id']);
        if ($open->isEmpty()) {
            return;
        }

        $entities = $open->pluck('issuer_id')->concat($open->pluck('issuer_corporation_id'))->map(fn ($id) => (int) $id)->unique()->values();
        $known = DB::table('universe_names')->whereIn('entity_id', $entities->all())->pluck('entity_id')->map(fn ($id) => (int) $id);
        $missing = $entities->diff($known)->values();
        if ($missing->isNotEmpty()) {
            dispatch(new Names($missing))->onQueue('public');
        }

        $locations = $open->pluck('start_location_id')->filter()->map(fn ($id) => (int) $id)->unique();
        $batch = new StructureBatch();
        foreach ($locations as $id) {
            $batch->addStructure($id);
        }
        $batch->submitJobs(self::nextToken());
    }

    /** The next token with the structure scope, in turn, or null when no character has it. */
    private static function nextToken(): ?RefreshToken
    {
        $tokens = RefreshToken::all()->filter(fn ($t) => $t->hasScope(self::STRUCTURE_SCOPE))->sortBy('character_id')->values();
        if ($tokens->isEmpty()) {
            return null;
        }
        $turn = (int) cache()->get(self::TURN_KEY, 0);
        cache()->forever(self::TURN_KEY, $turn + 1);

        return $tokens[$turn % $tokens->count()];
    }
}
