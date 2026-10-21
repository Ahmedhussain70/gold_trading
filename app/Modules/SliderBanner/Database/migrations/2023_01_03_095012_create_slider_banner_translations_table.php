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
        Schema::create('slider_banner_translations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('slider_banner_id');
            $table->unique(['slider_banner_id', 'locale']);
            $table->foreign('slider_banner_id')->references('id')->on('slider_banner')->onDelete('cascade');
            $table->string('locale')->index();
            $table->string('title');
            $table->text('sub_title');
            $table->string('link');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('slider_banner_translations');
    }
};
