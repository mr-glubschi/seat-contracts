<?php

namespace Glubschi\Seat\Contracts\Database\Seeders;

use Seat\Services\Seeding\AbstractScheduleSeeder;

/**
 * Adds the update to SeAT's schedule (Settings > Schedule), where an admin can change or remove it.
 *
 * Every 30 minutes, as long as ESI caches the public contract lists. The minute is offset from :00 and :30 so
 * that not every SeAT install hits ESI at the same moment, as SeAT does for its own contract updates.
 */
class ScheduleSeeder extends AbstractScheduleSeeder
{
    public function getSchedules(): array
    {
        return [
            [
                'command' => 'contracts:update',
                'expression' => '7,37 * * * *',
                'allow_overlap' => false,
                'allow_maintenance' => false,
                'ping_before' => null,
                'ping_after' => null,
            ],
        ];
    }

    public function getDeprecatedSchedules(): array
    {
        return [];
    }
}
