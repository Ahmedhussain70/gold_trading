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
            $table->decimal('amount_egp', 20, 2)->default(0)->after('amount');
            $table->decimal('wait_withdraw_egp', 20, 2)->default(0)->after('wait_withdraw_demo');
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
            $table->dropColumn(['amount_egp', 'wait_withdraw_egp']);
        });
    }
};
