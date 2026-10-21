<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
            // ✅ للـ tradess() و orderss() و allTrades()
            $table->index(
                ['user_id', 'mode', 'type', 'status'],
                'orders_user_mode_type_status_idx'
            );

            // ✅ للـ closedTrades()
            $table->index(
                ['user_id', 'mode', 'status', 'closed_at'],
                'orders_user_mode_status_closed_idx'
            );
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_user_mode_type_status_idx');
            $table->dropIndex('orders_user_mode_status_closed_idx');
        });
    }
};
