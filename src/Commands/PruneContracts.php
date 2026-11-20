<?php

namespace Glubschi\Seat\Contracts\Commands;

use Carbon\Carbon;
use Glubschi\Seat\Contracts\Contracts\PublicContracts;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Keeps the tables small. Scheduled once a day (Database\Seeders\ScheduleSeeder).
 *
 * For contracts that are closed (gone from the region lists or past their expiry) for more than
 * contracts.keep_items_days days:
 * - the contents are deleted; the contract row keeps its price and valuation for the price history;
 * - a contract without a capital ship (valuation "none", or contents never read) is deleted completely: without
 *   its contents it could not be valued again, so it would only take space.
 *
 * Open contracts are never touched. With keep_items_days = 0 nothing is deleted.
 */
class PruneContracts extends Command
{
    protected $signature = 'contracts:prune';

    protected $description = 'Delete the contents of long closed contracts, and contracts without a capital ship.';

    public function handle(): int
    {
        $days = (int) config('contracts.keep_items_days');
        if ($days <= 0) {
            $this->info('contracts.keep_items_days is 0: nothing deleted.');

            return self::SUCCESS;
        }

        [$items, $contracts] = self::prune(Carbon::now(), $days);
        $this->info(sprintf('Deleted the contents of %d contracts and %d contracts without a capital ship.', $items, $contracts));

        return self::SUCCESS;
    }

    /**
     * @return array{0: int, 1: int}  contracts whose contents were deleted, contracts deleted
     */
    public static function prune(Carbon $now, int $days): array
    {
        $before = $now->copy()->subDays($days)->toDateTimeString();
        $closed = fn ($q) => $q->where(fn ($w) => $w->where('gone_at', '<', $before)
            ->orWhere(fn ($e) => $e->whereNull('gone_at')->where('date_expired', '<', $before)));

        $itemsOf = 0;
        $deleted = 0;
        // chunkById, not chunk: rows are deleted while walking through them, which would shift the pages.
        DB::table(PublicContracts::TABLE)->where($closed)->whereNull('items_pruned_at')
            ->select(['contract_id', 'main_type_id'])
            ->chunkById(1000, function ($rows) use (&$itemsOf, &$deleted, $now) {
                $ids = $rows->pluck('contract_id')->all();
                $keep = $rows->whereNotNull('main_type_id')->pluck('contract_id')->all();
                $drop = array_values(array_diff($ids, $keep));

                DB::transaction(function () use ($ids, $keep, $drop, $now) {
                    DB::table(PublicContracts::ITEMS_TABLE)->whereIn('contract_id', $ids)->delete();
                    if ($keep !== []) {
                        DB::table(PublicContracts::TABLE)->whereIn('contract_id', $keep)->update(['items_pruned_at' => $now->toDateTimeString()]);
                    }
                    if ($drop !== []) {
                        DB::table(PublicContracts::TABLE)->whereIn('contract_id', $drop)->delete();
                    }
                });
                $itemsOf += count($keep);
                $deleted += count($drop);
            }, 'contract_id');

        return [$itemsOf, $deleted];
    }
}
