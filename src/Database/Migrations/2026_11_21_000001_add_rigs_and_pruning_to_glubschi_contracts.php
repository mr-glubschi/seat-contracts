<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * v0.3: rigs in a contract are counted (they count as 0 ISK), and contracts whose items were deleted by
 * contracts:prune are marked. An index for the price history, which reads contracts by hull and time.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('glubschi_contracts', function (Blueprint $table) {
            $table->integer('extras_rigs')->nullable()->after('extras_unpriced');
            $table->dateTime('items_pruned_at')->nullable()->after('items_status');
            $table->index(['main_type_id', 'date_expired']);
        });
    }

    public function down(): void
    {
        Schema::table('glubschi_contracts', function (Blueprint $table) {
            $table->dropIndex(['main_type_id', 'date_expired']);
            $table->dropColumn(['extras_rigs', 'items_pruned_at']);
        });
    }
};
