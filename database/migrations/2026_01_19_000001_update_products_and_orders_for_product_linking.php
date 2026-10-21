<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * التعديلات:
     * 1. حذف lowest_price, highest_price من products (مش محتاجينهم)
     * 2. إضافة product_id, quantity, unit_gram_weight, unit_karat لـ orders
     */
    public function up(): void
    {
        // 1) حذف أعمدة السعر من products
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'lowest_price')) {
                $table->dropColumn('lowest_price');
            }
            if (Schema::hasColumn('products', 'highest_price')) {
                $table->dropColumn('highest_price');
            }
        });

        // 2) إضافة أعمدة ربط المنتج في orders
        Schema::table('orders', function (Blueprint $table) {
            // ربط بالمنتج (nullable للأوردرات القديمة)
            if (!Schema::hasColumn('orders', 'product_id')) {
                $table->unsignedBigInteger('product_id')->nullable()->after('user_id');
                $table->foreign('product_id')->references('id')->on('products')->onDelete('set null');
            }

            // عدد الوحدات/القطع (مثلاً: 2 سبيكة)
            if (!Schema::hasColumn('orders', 'quantity')) {
                $table->integer('quantity')->default(1)->after('product_id');
            }

            // وزن الوحدة الواحدة وقت الشراء (للحفاظ على البيانات التاريخية)
            if (!Schema::hasColumn('orders', 'unit_gram_weight')) {
                $table->decimal('unit_gram_weight', 10, 3)->nullable()->after('quantity');
            }

            // العيار وقت الشراء
            if (!Schema::hasColumn('orders', 'unit_karat')) {
                $table->tinyInteger('unit_karat')->nullable()->after('unit_gram_weight');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // إرجاع أعمدة السعر للـ products
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'lowest_price')) {
                $table->decimal('lowest_price', 10, 2)->nullable();
            }
            if (!Schema::hasColumn('products', 'highest_price')) {
                $table->decimal('highest_price', 10, 2)->nullable();
            }
        });

        // حذف أعمدة ربط المنتج من orders
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'product_id')) {
                $table->dropForeign(['product_id']);
                $table->dropColumn('product_id');
            }
            if (Schema::hasColumn('orders', 'quantity')) {
                $table->dropColumn('quantity');
            }
            if (Schema::hasColumn('orders', 'unit_gram_weight')) {
                $table->dropColumn('unit_gram_weight');
            }
            if (Schema::hasColumn('orders', 'unit_karat')) {
                $table->dropColumn('unit_karat');
            }
        });
    }
};
