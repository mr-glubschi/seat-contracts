<?php

namespace Glubschi\Seat\Contracts\Http\Controllers;

use Glubschi\Seat\Contracts\Contracts\Contents;
use Glubschi\Seat\Contracts\Contracts\Overview;
use Glubschi\Seat\Contracts\Contracts\PublicContracts;
use Glubschi\Seat\Contracts\Contracts\Status;
use Glubschi\Seat\Contracts\Jobs\OpenContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Seat\Eseye\Exceptions\RequestFailedException;
use Seat\Eveapi\Models\RefreshToken;
use Seat\Web\Http\Controllers\Controller;

/**
 * The Contracts page: capital ships by class and hull with their contracts, the contents of a contract on demand,
 * and opening a contract in the game for one of the user's characters.
 */
class ContractsController extends Controller
{
    /** User setting: the character that opens contracts in the game. */
    public const CHARACTER_SETTING = 'glubschi.contracts.character';

    public function index()
    {
        $characters = $this->overview();
        $saved = (int) setting(self::CHARACTER_SETTING);
        $ids = array_column($characters, 'id');
        $main = (int) (auth()->user()->main_character_id ?? 0);
        $selected = in_array($saved, $ids, true) ? $saved : (in_array($main, $ids, true) ? $main : ($ids[0] ?? null));

        return view('contracts::index', [
            'tree' => Overview::build(),
            'status' => Status::figures(),
            'characters' => $characters,
            'selected' => $selected,
        ]);
    }

    /** The contents of one contract, for the row the user opens. */
    public function items(int $contractId): JsonResponse
    {
        $items = Contents::of($contractId);
        if ($items === null) {
            return $this->fail(trans('contracts::global.error_contract'), 404);
        }

        return response()->json(['ok' => true, 'items' => $items]);
    }

    /** Opens the contract window of a contract in the game client of one of the user's characters. */
    public function openWindow(Request $request): JsonResponse
    {
        $request->validate([
            'character_id' => 'required|integer',
            'contract_id' => 'required|integer',
        ]);

        $characters = $this->characters();
        $characterId = (int) $request->input('character_id');
        if (! array_key_exists($characterId, $characters)) {
            return $this->fail(trans('contracts::global.error_character'), 403);
        }

        $token = RefreshToken::find($characterId);
        if ($token === null || ! $token->hasScope(OpenContract::SCOPE)) {
            return $this->fail(trans('contracts::global.error_scope', ['character' => $characters[$characterId]]), 422);
        }

        $contractId = (int) $request->input('contract_id');
        if (! DB::table(PublicContracts::TABLE)->where('contract_id', $contractId)->exists()) {
            return $this->fail(trans('contracts::global.error_contract'), 404);
        }

        setting([self::CHARACTER_SETTING, $characterId]);

        try {
            (new OpenContract($token, $contractId))->handle();
        } catch (RequestFailedException $e) {
            $response = $e->getEsiResponse();

            return $this->fail(trans('contracts::global.error_esi', ['status' => $response->getErrorCode(), 'error' => $response->error()]), 502);
        } catch (\Throwable $e) {
            report($e);

            return $this->fail(trans('contracts::global.error_esi', ['status' => $e->getCode() ?: '?', 'error' => $e->getMessage()]), 502);
        }

        // For a character who is not logged in, nothing happens in the game and ESI reports no error either,
        // so the page says when SeAT last saw the character offline.
        $online = DB::table('character_onlines')->where('character_id', $characterId)->value('online');
        $offline = $online !== null && ! (bool) $online;

        return response()->json([
            'ok' => true,
            'offline' => $offline,
            'message' => trans($offline ? 'contracts::global.opened_offline' : 'contracts::global.opened', ['character' => $characters[$characterId]]),
        ]);
    }

    /**
     * The user's characters with scope and online status, for the "open with" choice.
     *
     * @return array<int, array{id: int, name: string, scope: bool, online: ?bool}>
     */
    private function overview(): array
    {
        $characters = $this->characters();
        $ids = array_keys($characters);
        $online = DB::table('character_onlines')->whereIn('character_id', $ids)->pluck('online', 'character_id');
        $tokens = RefreshToken::whereIn('character_id', $ids)->get()->keyBy('character_id');

        $out = [];
        foreach ($characters as $id => $name) {
            $out[] = [
                'id' => $id,
                'name' => $name,
                'scope' => isset($tokens[$id]) && $tokens[$id]->hasScope(OpenContract::SCOPE),
                'online' => isset($online[$id]) ? (bool) $online[$id] : null,
            ];
        }

        return $out;
    }

    /** @return array<int, string> names of the user's characters by ID */
    private function characters(): array
    {
        return auth()->user()->characters()->orderBy('character_infos.name')->pluck('character_infos.name', 'character_infos.character_id')
            ->mapWithKeys(fn ($name, $id) => [(int) $id => $name])->all();
    }

    private function fail(string $message, int $status): JsonResponse
    {
        return response()->json(['ok' => false, 'message' => $message], $status);
    }
}
