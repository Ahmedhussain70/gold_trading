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
        Schema::table('wallets', function (Blueprint $table) {
            $table->decimal('wait_withdraw_live', 10, 2)->default(0)->after('amount');
            $table->decimal('wait_withdraw_demo', 10, 2)->default(0)->after('amount_demo');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('wallets', function (Blueprint $table) {
            $table->dropColumn('wait_withdraw_live');
            $table->dropColumn('wait_withdraw_demo');
        });
    }
};
