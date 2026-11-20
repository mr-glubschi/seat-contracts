<?php

namespace Glubschi\Seat\Contracts\Tests;

use Glubschi\Seat\Contracts\ContractsServiceProvider;
use Glubschi\Seat\Contracts\Database\Seeders\ScheduleSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

class ServiceProviderTest extends TestCase
{
    public function test_registers_the_page_with_seat_middleware(): void
    {
        $route = Route::getRoutes()->getByName('seatcontracts::home');

        $this->assertNotNull($route);
        $this->assertSame('contracts', $route->uri());
        $this->assertContains('GET', $route->methods());
        $this->assertSame(['web', 'auth', 'locale', 'can:contracts.view'], $route->gatherMiddleware());
    }

    public function test_registers_the_view_permission(): void
    {
        $permission = config('seat.permissions.contracts.view');

        $this->assertIsArray($permission);
        $this->assertSame('contracts::permissions.view_label', $permission['label']);
        $this->assertSame('financial', $permission['division']);
    }

    public function test_adds_a_sidebar_entry_guarded_by_the_permission(): void
    {
        $entry = config('package.sidebar.contracts');

        $this->assertSame('seatcontracts::home', $entry['route']);
        $this->assertSame('contracts.view', $entry['permission']);
        $this->assertSame('contracts', $entry['route_segment']);
    }

    public function test_reports_name_and_packagist_coordinates(): void
    {
        $provider = new ContractsServiceProvider($this->app);

        $this->assertSame('Contracts', $provider->getName());
        $this->assertSame('mr-glubschi', $provider->getPackagistVendorName());
        $this->assertSame('seat-contracts', $provider->getPackagistPackageName());
    }

    public function test_creates_its_tables(): void
    {
        $this->assertTrue(Schema::hasTable('glubschi_contracts'));
        $this->assertTrue(Schema::hasTable('glubschi_contract_items'));
    }

    public function test_registers_the_command_and_its_schedule(): void
    {
        $this->assertArrayHasKey('contracts:update', Artisan::all());
        $this->assertContains(ScheduleSeeder::class, config('seat.seeders'));

        $schedule = (new ScheduleSeeder())->getSchedules()[0];
        $this->assertSame('contracts:update', $schedule['command']);
        $this->assertSame('7,37 * * * *', $schedule['expression']);
    }

    public function test_reads_its_config(): void
    {
        $this->assertContains('Dreadnought', config('contracts.groups'));
        $this->assertSame(60003760, config('contracts.jita_station_id'));
    }

    public function test_translation_keys_resolve(): void
    {
        $this->assertSame('Contracts', trans('contracts::menu.main'));
        $this->assertSame('View Contracts', trans('contracts::permissions.view_label'));
        $this->assertSame('Ship class', trans('contracts::global.col_group'));
    }
}
