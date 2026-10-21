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
        Schema::create('otps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                  ->nullable() // لو بتسمح بإرسال OTP قبل إنشاء يوزر
                  ->constrained('users')
                  ->cascadeOnDelete();

            // احتياطيًا خليه يخزن الإيميل/الموبايل اللي اتبعت له الكود
            $table->string('email')->nullable()->index();
            $table->string('mobile')->nullable()->index();

            $table->string('code', 10);                    // 6 أرقام عادة
            $table->string('type')->default('password_reset')->index(); // password_reset / login / verify_email...

            $table->timestamp('expire_at')->index();       // صلاحية الكود
            $table->unsignedInteger('attempts')->default(0); // عدد محاولات الإدخال

            $table->json('meta')->nullable();              // أي بيانات إضافية
            $table->timestamp('used_at')->nullable()->index(); // وقت الاستخدام

            $table->timestamps();

            // شوية مؤشرات مفيدة
            $table->index(['user_id', 'type', 'created_at']);
            $table->index(['email', 'type', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('otps');
    }
};
