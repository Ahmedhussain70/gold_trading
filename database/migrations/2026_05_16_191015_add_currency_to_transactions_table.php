<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('currency', 10)->default('USD')->after('amount_type');
        });

        // ✅ تحديث السجلات القديمة بناءً على amount_type
        DB::statement("
            UPDATE transactions 
            SET currency = CASE 
                WHEN amount_type = 'demo' THEN 'DEMO'
                ELSE 'USD'
            END
        ");
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('currency');
        });
    }
};