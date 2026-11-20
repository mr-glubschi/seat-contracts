<?php

namespace Glubschi\Seat\Contracts\Commands;

use Glubschi\Seat\Contracts\Jobs\DispatchContractItems;
use Glubschi\Seat\Contracts\Jobs\RegionContracts;
use Illuminate\Bus\Batch;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Scheduled every 30 minutes (Database\Seeders\ScheduleSeeder).
 *
 * Queues one RegionContracts job per region of SeAT's SDE as a batch, like SeAT's own esi:update:prices. When the
 * batch is finished, also with failed regions, DispatchContractItems queues the contents of new contracts.
 */
class UpdateContracts extends Command
{
    protected $signature = 'contracts:update';

    protected $description = 'Queue the jobs that read all public contracts and their contents from ESI.';

    public function handle(): int
    {
        $regions = DB::table('regions')->orderBy('region_id')->pluck('region_id')->map(fn ($id) => (int) $id);
        if ($regions->isEmpty()) {
            $this->error('SeAT has no regions yet: run eve:update:sde first.');

            return self::FAILURE;
        }

        Bus::batch($regions->map(fn ($id) => new RegionContracts($id))->all())
            ->catch(function (Batch $batch, Throwable $e) {
                logger()->error(sprintf('[Batches][%s] %s - a region failed: %s', $batch->id, $batch->name, $e->getMessage()));
            })
            ->finally(function (Batch $batch) {
                dispatch(new DispatchContractItems())->onQueue('public');
            })
            ->allowFailures()
            ->onQueue('public')
            ->name('Public Contracts')
            ->dispatch();

        $this->info(sprintf('Queued %d regions.', $regions->count()));

        return self::SUCCESS;
    }
}
