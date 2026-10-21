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
        Schema::table('users', function (Blueprint $table) {
            $table->string('national_id_front')->nullable();
            $table->string('national_id_back')->nullable();
            $table->boolean('national_id_front_approved')->default(0);
            $table->boolean('national_id_back_approved')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'national_id_front',
                'national_id_back',
                'national_id_front_approved',
                'national_id_back_approved'
            ]);
        });
    }
};
