<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('close_price', 18, 6)->nullable()->after('executed_price');
            $table->decimal('realized_pnl', 18, 6)->nullable()->after('close_price');
            $table->decimal('unrealized_pnl', 18, 6)->nullable()->after('realized_pnl');
            $table->timestamp('closed_at')->nullable()->after('unrealized_pnl');

            // كيف تم إغلاق الأوردر: بيع للتطبيق أم شحن
            $table->enum('close_kind', ['sell','delivery'])->nullable()->after('closed_at');

            // حقول الشحن (العنوان موجود مسبقًا في address)
            $table->string('delivery_status')->nullable()->after('close_kind');           // pending_delivery, in_progress, delivered, cancelled
            $table->timestamp('delivery_requested_at')->nullable()->after('delivery_status');
            $table->string('delivery_method')->nullable()->after('delivery_requested_at'); // courier, pickup, ..
            $table->string('delivery_city')->nullable()->after('delivery_method');
            $table->string('delivery_phone')->nullable()->after('delivery_city');
            $table->decimal('delivery_fee', 10, 2)->nullable()->after('delivery_phone');
            $table->string('delivery_tracking_no')->nullable()->after('delivery_fee');
        });

        // توسيع الـ ENUM لحقل status (كان: pending/executed/cancelled)
        DB::statement("
            ALTER TABLE `orders`
            MODIFY `status`
            ENUM('open','pending','pending_delivery','executed','delivered','cancelled')
            NOT NULL DEFAULT 'open'
        ");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // رجوع الـ ENUM القديم (لو عايز ترجع)
        DB::statement("
            ALTER TABLE `orders`
            MODIFY `status`
            ENUM('pending','executed','cancelled')
            NOT NULL DEFAULT 'pending'
        ");

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'close_price','realized_pnl','unrealized_pnl','closed_at',
                'close_kind',
                'delivery_status','delivery_requested_at','delivery_method',
                'delivery_city','delivery_phone','delivery_fee','delivery_tracking_no',
            ]);
        });
    }
};
