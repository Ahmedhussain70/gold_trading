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
            $table->decimal('manufacturing_fee_snapshot', 10, 2)->nullable()->after('unit_karat');
            $table->decimal('shipping_cost_snapshot', 10, 2)->nullable()->after('manufacturing_fee_snapshot');
            $table->decimal('metal_cost_snapshot', 15, 8)->nullable()->after('shipping_cost_snapshot');
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
            $table->dropColumn(['manufacturing_fee_snapshot', 'shipping_cost_snapshot', 'metal_cost_snapshot']);
        });
    }
};
