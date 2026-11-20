<?php

namespace Glubschi\Seat\Contracts\Jobs;

use Carbon\Carbon;
use Glubschi\Seat\Contracts\Contracts\Capitals;
use Glubschi\Seat\Contracts\Contracts\PublicContracts;
use Glubschi\Seat\Contracts\Pricing\JitaBuy;
use Glubschi\Seat\Contracts\Pricing\Valuation;
use Illuminate\Support\Facades\DB;
use Seat\Eseye\Exceptions\RequestFailedException;
use Seat\Eveapi\Jobs\EsiBase;
use Seat\Services\Contracts\EsiResponse;

/**
 * Reads the contents of a group of public contracts, one after the other, and values each one.
 *
 * /contracts/public/items/{contract_id}/ is public, cached by ESI for one hour, and answers 204 when the
 * contract has expired or was just accepted; 403 and 404 mean the same for this purpose. Each contract is saved
 * as soon as it is read, so when the job fails half-way and SeAT retries it, the retry goes on with the rest.
 */
class ContractItems extends EsiBase
{
    protected $method = 'get';

    protected $endpoint = '/contracts/public/items/{contract_id}/';

    protected string $compatibility_date = '2025-07-20';

    protected $tags = ['public', 'contracts', 'glubschi-contracts'];

    protected $page = 1;

    /**
     * @param  int[]  $contractIds
     */
    public function __construct(protected array $contractIds)
    {
        parent::__construct();
    }

    /** @return int[] */
    public function contractIds(): array
    {
        return $this->contractIds;
    }

    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $contracts = DB::table(PublicContracts::TABLE)
            ->whereIn('contract_id', $this->contractIds)
            ->where('items_status', 'pending')
            ->get()
            ->keyBy('contract_id');

        foreach ($this->contractIds as $contractId) {
            $contract = $contracts[$contractId] ?? null;
            if ($contract === null) {
                continue;
            }
            $items = $this->read($contractId);
            self::save($contract, $items, Carbon::now());
        }
    }

    /**
     * All pages of one contract's contents, or null when ESI no longer shows them.
     *
     * @return array<int, object>|null
     */
    private function read(int $contractId): ?array
    {
        $this->page = 1;
        $items = [];
        try {
            while (true) {
                $response = $this->retrieve(['contract_id' => $contractId]);
                $body = $response->getBody();
                if ($response->getStatusCode() === 204 || ! is_array($body)) {
                    return null;
                }
                array_push($items, ...$body);
                if (! $this->nextPage($response->getPagesCount())) {
                    break;
                }
            }
        } catch (RequestFailedException $e) {
            if (in_array($e->getEsiResponse()->getErrorCode(), [403, 404], true)) {
                return null;
            }
            throw $e;
        }

        return $items;
    }

    /**
     * Stores the contents of a contract and its valuation; null contents mark it as unavailable.
     *
     * @param  object  $contract  row of glubschi_contracts
     * @param  array<int, object>|null  $items  ESI item objects
     */
    public static function save(object $contract, ?array $items, Carbon $now): void
    {
        if ($items === null) {
            DB::table(PublicContracts::TABLE)->where('contract_id', $contract->contract_id)->update([
                'items_status' => 'unavailable',
                'items_read_at' => $now->toDateTimeString(),
            ]);

            return;
        }

        $rows = array_map(fn ($i) => [
            'contract_id' => (int) $contract->contract_id,
            'record_id' => (int) $i->record_id,
            'type_id' => (int) $i->type_id,
            'quantity' => (int) $i->quantity,
            'is_included' => (bool) $i->is_included,
            'is_blueprint_copy' => isset($i->is_blueprint_copy) ? (bool) $i->is_blueprint_copy : null,
            'runs' => $i->runs ?? null,
            'material_efficiency' => $i->material_efficiency ?? null,
            'time_efficiency' => $i->time_efficiency ?? null,
            'item_id' => $i->item_id ?? null,
        ], $items);

        $valuation = self::valuate($contract, $rows);

        DB::transaction(function () use ($contract, $rows, $valuation, $now) {
            DB::table(PublicContracts::ITEMS_TABLE)->where('contract_id', $contract->contract_id)->delete();
            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table(PublicContracts::ITEMS_TABLE)->insert($chunk);
            }
            DB::table(PublicContracts::TABLE)->where('contract_id', $contract->contract_id)->update([
                'items_status' => 'read',
                'items_read_at' => $now->toDateTimeString(),
            ] + $valuation);
        });
    }

    /**
     * The valuation of a contract from its item rows, with the Jita buy prices of today. Used when the contents
     * are read, and by contracts:revalue.
     *
     * @param  object  $contract  row of glubschi_contracts (type, price, buyout)
     * @param  array<int, array>  $rows  item rows as stored in glubschi_contract_items
     */
    public static function valuate(object $contract, array $rows): array
    {
        return Valuation::value(
            $rows,
            Capitals::typeIds(),
            self::pricesFor($rows),
            $contract->type,
            $contract->price === null ? null : (float) $contract->price,
            $contract->buyout === null ? null : (float) $contract->buyout,
            Capitals::rigTypeIds(),
        );
    }

    /**
     * SeAT logs a warning and queues an analytics job when a paged request comes back without X-Pages. ESI's 204
     * for an expired contract has no X-Pages, and thousands of them are normal here, so they are not reported.
     */
    public function warning(EsiResponse $response): void
    {
        if ($response->getStatusCode() === 204) {
            return;
        }
        parent::warning($response);
    }

    /** Jita buy prices are only looked up for contracts with a capital ship; the others need none. */
    private static function pricesFor(array $rows): array
    {
        $capitals = Capitals::typeIds();
        foreach ($rows as $row) {
            if (isset($capitals[(int) $row['type_id']])) {
                return JitaBuy::prices(array_map(fn ($r) => (int) $r['type_id'], $rows));
            }
        }

        return [];
    }
}
