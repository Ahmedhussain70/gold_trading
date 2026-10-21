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
            $table->enum('amount_type', ['live', 'demo'])->default('demo')->after('type');
            $table->enum('created_by', ['client', 'admin'])->default('client')->after('amount_type');
            $table->string('note')->nullable()->after('created_by');
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
            $table->dropColumn('amount_type');
            $table->dropColumn('created_by');
            $table->dropColumn('note');
        });
    }
};
