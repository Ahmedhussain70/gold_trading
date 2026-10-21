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
            $table->string('metal', 8)->nullable()->after('product_category_id');  // XAU, XAG
            $table->tinyInteger('karat')->nullable()->after('metal');             // 18/20/21/22/24
            $table->decimal('gram_weight', 10, 3)->nullable()->after('karat');    // وزن القطعة بالجرام
            $table->index(['metal', 'karat']);
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
            $table->dropIndex(['metal', 'karat']);
            $table->dropColumn(['metal','karat','gram_weight']);
        });
    }
};
