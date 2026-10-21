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
        Schema::create('blog_translations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('blog_id');
            $table->unique(['blog_id', 'locale']);
            $table->foreign('blog_id')->references('id')->on('blogs')->onDelete('cascade');
            $table->string('locale')->index();
            $table->string('title');
            $table->text('content');
            $table->string('tags');
            $table->text('slug')->nullable();
            $table->text('meta_title')->nullable();
            $table->text('meta_content')->nullable();
            $table->text('meta_keywords')->nullable();
            $table->text('opengraph_type')->nullable();
            $table->text('opengraph_title')->nullable();
            $table->text('opengraph_url')->nullable();
            $table->text('opengraph_content')->nullable();
            $table->text('twitter_card')->nullable();
            $table->text('twitter_title')->nullable();
            $table->text('twitter_site')->nullable();
            $table->text('twitter_content')->nullable();
            $table->text('twitter_image_alt')->nullable();
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
        Schema::dropIfExists('blog_translations');
    }
};
