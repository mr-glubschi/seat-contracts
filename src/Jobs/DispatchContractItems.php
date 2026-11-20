<?php

namespace Glubschi\Seat\Contracts\Jobs;

use Carbon\Carbon;
use Glubschi\Seat\Contracts\Contracts\PublicContracts;
use Glubschi\Seat\Contracts\Contracts\Resolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/**
 * Queues ContractItems jobs for every open contract whose contents were not read yet.
 *
 * Runs after the region lists, so new contracts are in the table. A contract is marked as queued, so the next
 * run 30 minutes later does not queue it a second time while the first run is still working through the
 * queue; after contracts.requeue_after_minutes it is queued again.
 */
class DispatchContractItems implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        foreach (self::jobs(Carbon::now()) as $job) {
            dispatch($job)->onQueue('public');
        }

        // Names of issuers and locations for the page; a failure here must not stop the contents.
        try {
            Resolver::dispatch();
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * The jobs to queue, with their contracts marked as queued.
     *
     * @return ContractItems[]
     */
    public static function jobs(Carbon $now): array
    {
        $requeue = $now->copy()->subMinutes((int) config('contracts.requeue_after_minutes', 120))->toDateTimeString();

        $ids = DB::table(PublicContracts::TABLE)
            ->whereNull('gone_at')
            ->where('items_status', 'pending')
            ->where(fn ($q) => $q->whereNull('items_queued_at')->orWhere('items_queued_at', '<', $requeue))
            ->orderBy('contract_id')
            ->pluck('contract_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $jobs = [];
        foreach (array_chunk($ids, max(1, (int) config('contracts.items_per_job', 100))) as $chunk) {
            DB::table(PublicContracts::TABLE)->whereIn('contract_id', $chunk)->update(['items_queued_at' => $now->toDateTimeString()]);
            $jobs[] = new ContractItems($chunk);
        }

        return $jobs;
    }
}
