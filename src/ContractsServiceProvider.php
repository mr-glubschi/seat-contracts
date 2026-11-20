<?php

namespace Glubschi\Seat\Contracts;

use Glubschi\Seat\Contracts\Commands\UpdateContracts;
use Glubschi\Seat\Contracts\Database\Seeders\ScheduleSeeder;
use Seat\Services\AbstractSeatPlugin;

/**
 * Registers Contracts with SeAT.
 *
 * Extends AbstractSeatPlugin (not Laravel's plain ServiceProvider) as the SeAT package development guide
 * requires: only then is the plugin listed under Settings > SeAT Settings > SeAT Module Versions and can it
 * register permissions and schedule seeders.
 *
 * Unlike the sister plugins, Contracts has tables of its own: ESI has no history for public contracts, so the
 * plugin keeps every contract it has seen (see the migrations).
 */
class ContractsServiceProvider extends AbstractSeatPlugin
{
    /** Name used for views, translations and config: view('contracts::…'), trans('contracts::…'), config('contracts.…'). */
    public const NS = 'contracts';

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/Http/routes.php');
        $this->loadViewsFrom(__DIR__ . '/resources/views', self::NS);
        $this->loadTranslationsFrom(__DIR__ . '/resources/lang', self::NS);
        $this->loadMigrationsFrom(__DIR__ . '/Database/Migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([UpdateContracts::class]);
        }
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/Config/contracts.php', self::NS);

        // Sidebar entry, merged into SeAT's menu config (package.sidebar).
        $this->mergeConfigFrom(__DIR__ . '/Config/Menu/package.sidebar.php', 'package.sidebar');

        // Permissions: the keys in this file become "contracts.<key>" (e.g. contracts.view).
        $this->registerPermissions(__DIR__ . '/Config/Permissions/contracts.php', self::NS);

        // SeAT runs registered seeders when the stack starts; this one adds the update job to SeAT's schedule.
        $this->registerDatabaseSeeders(ScheduleSeeder::class);
    }

    public function getName(): string
    {
        return 'Contracts';
    }

    public function getPackageRepositoryUrl(): string
    {
        return 'https://github.com/mr-glubschi/seat-contracts';
    }

    public function getPackagistPackageName(): string
    {
        return 'seat-contracts';
    }

    public function getPackagistVendorName(): string
    {
        return 'mr-glubschi';
    }

    public function getDescription(): ?string
    {
        return 'Prices of capital ships from contracts in all regions, with one click to the contract in the game.';
    }
}
