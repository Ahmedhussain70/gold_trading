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
        Schema::table('deposits', function (Blueprint $table) {
            $table->string('payer_name')->nullable()->after('user_id');          // اسم صاحب التحويل
            $table->string('payer_phone')->nullable()->after('payer_name');      // رقم الموبايل
            $table->string('receipt_path')->nullable()->after('note');           // مسار إيصال التحويل (صورة/ PDF)
        });

        Schema::table('withdraws', function (Blueprint $table) {
            $table->string('account_name')->nullable()->after('user_id');        // اسم صاحب الحساب
            $table->string('account_phone')->nullable()->after('account_name');  // رقم الموبايل
            $table->string('bank_name')->nullable()->after('account_phone');     // اسم البنك
            $table->string('bank_account')->nullable()->after('bank_name');      // رقم الحساب/IBAN
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('deposits', function (Blueprint $table) {
            $table->dropColumn(['payer_name','payer_phone','receipt_path']);
        });

        Schema::table('withdraws', function (Blueprint $table) {
            $table->dropColumn(['account_name','account_phone','bank_name','bank_account']);
        });
    }
};
