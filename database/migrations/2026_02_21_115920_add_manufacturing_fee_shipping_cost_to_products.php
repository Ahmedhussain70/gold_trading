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
        Schema::table('products', function (Blueprint $table) {
            // قيمة المصنعية
            $table->decimal('manufacturing_fee', 10, 2)->default(0)->after('gram_weight');

            // الشحن
            $table->boolean('has_delivery')->default(false)->after('manufacturing_fee');
            $table->decimal('shipping_cost', 10, 2)->nullable()->after('has_delivery');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['manufacturing_fee', 'has_delivery', 'shipping_cost']);
        });
    }
};
