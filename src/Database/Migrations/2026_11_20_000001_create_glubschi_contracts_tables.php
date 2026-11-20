<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The plugin's own tables. Table names carry the author prefix so they cannot clash with SeAT's contract_*
 * tables (which hold the characters' and corporations' own contracts) or with other plugins.
 */
return new class extends Migration
{
    public function up(): void
    {
        // One row per public item exchange or auction contract ever seen. The price of a contract cannot
        // change, so a row is written once and only its "seen" times move.
        Schema::create('glubschi_contracts', function (Blueprint $table) {
            $table->bigInteger('contract_id')->primary();
            $table->integer('region_id')->index();
            $table->enum('type', ['item_exchange', 'auction']);
            $table->string('title')->nullable();
            $table->bigInteger('issuer_id');
            $table->bigInteger('issuer_corporation_id');
            $table->boolean('for_corporation');
            $table->bigInteger('start_location_id')->nullable();
            $table->double('price')->nullable();
            $table->double('buyout')->nullable();
            $table->double('volume')->nullable();
            $table->dateTime('date_issued');
            $table->dateTime('date_expired')->index();

            // When the plugin saw the contract first and last, and when it was gone from the region list
            // (accepted, deleted or expired: ESI does not say which).
            $table->dateTime('first_seen_at');
            $table->dateTime('last_seen_at');
            $table->dateTime('gone_at')->nullable()->index();

            // Contents: queued for reading, read, or no longer readable (ESI 204/403/404).
            $table->dateTime('items_queued_at')->nullable();
            $table->dateTime('items_read_at')->nullable();
            $table->enum('items_status', ['pending', 'read', 'unavailable'])->default('pending')->index();

            // Valuation, set when the contents are read (Pricing\Valuation).
            $table->string('valuation', 16)->nullable();
            $table->integer('main_type_id')->nullable()->index();
            $table->double('extras_value')->nullable();
            $table->integer('extras_unpriced')->nullable();
            $table->double('net_price')->nullable();
        });

        // The contents, as ESI lists them. Never changes for a contract.
        Schema::create('glubschi_contract_items', function (Blueprint $table) {
            $table->bigInteger('contract_id');
            $table->bigInteger('record_id');
            $table->integer('type_id')->index();
            $table->integer('quantity');
            $table->boolean('is_included');
            $table->boolean('is_blueprint_copy')->nullable();
            $table->integer('runs')->nullable();
            $table->integer('material_efficiency')->nullable();
            $table->integer('time_efficiency')->nullable();
            $table->bigInteger('item_id')->nullable();
            $table->primary(['contract_id', 'record_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('glubschi_contract_items');
        Schema::dropIfExists('glubschi_contracts');
    }
};
