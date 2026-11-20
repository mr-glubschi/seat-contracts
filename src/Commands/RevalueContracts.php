<?php

namespace Glubschi\Seat\Contracts\Commands;

use Glubschi\Seat\Contracts\Contracts\PublicContracts;
use Glubschi\Seat\Contracts\Jobs\ContractItems;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Values every contract with stored contents again: after a change to the valuation (v0.3: rigs count as 0 ISK),
 * or after new groups were added to config contracts.groups or the SDE got a new group.
 *
 * Uses the Jita buy prices of today; the prices of the day the contents were read are not kept per item. Contracts
 * whose items were deleted by contracts:prune keep their valuation.
 */
class RevalueContracts extends Command
{
    protected $signature = 'contracts:revalue {--chunk=500 : contracts per database round}';

    protected $description = 'Value all contracts with stored contents again (ship price without fitting).';

    public function handle(): int
    {
        $done = 0;
        $changed = 0;
        DB::table(PublicContracts::TABLE)
            ->where('items_status', 'read')
            ->whereNull('items_pruned_at')
            ->chunkById(max(1, (int) $this->option('chunk')), function ($contracts) use (&$done, &$changed) {
                $items = DB::table(PublicContracts::ITEMS_TABLE)
                    ->whereIn('contract_id', $contracts->pluck('contract_id')->all())
                    ->get()
                    ->groupBy('contract_id');

                foreach ($contracts as $contract) {
                    $rows = ($items[$contract->contract_id] ?? collect())->map(fn ($i) => (array) $i)->all();
                    $valuation = ContractItems::valuate($contract, $rows);
                    $done++;
                    if (self::differs($contract, $valuation)) {
                        DB::table(PublicContracts::TABLE)->where('contract_id', $contract->contract_id)->update($valuation);
                        $changed++;
                    }
                }
            }, 'contract_id');

        $this->info(sprintf('Valued %d contracts, %d changed.', $done, $changed));

        return self::SUCCESS;
    }

    private static function differs(object $contract, array $valuation): bool
    {
        foreach ($valuation as $key => $value) {
            $old = $contract->$key ?? null;
            if (is_float($value) || is_float($old)) {
                if ($old === null || $value === null ? $old !== $value : abs((float) $old - (float) $value) > 0.005) {
                    return true;
                }
            } elseif ((string) $old !== (string) $value) {
                return true;
            }
        }

        return false;
    }
}
