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
        Schema::table('transactions', function (Blueprint $table) {
            $table->decimal('amount', 10, 2)->change();
            $table->renameColumn('amount', 'credit');
            $table->decimal('debit', 10, 2)->nullable();
            $table->decimal('balance', 10, 2)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('credit', 255)->change();
            $table->renameColumn('credit', 'amount');
            $table->dropColumn(['debit', 'balance']);
        });
    }
};
