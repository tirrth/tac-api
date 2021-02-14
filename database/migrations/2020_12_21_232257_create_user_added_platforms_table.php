<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUserAddedPlatformsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('user_added_platforms', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->unsignedBigInteger('user_id');
            $table->string('platform_name');
            $table->string('redirection_url');
            $table->unsignedBigInteger('media_type_id');
            $table->unsignedBigInteger('taps')->default(0);
            $table->string('logo_url')->nullable();
            $table->boolean('is_active')->default(0);

            $table->foreign('media_type_id')->references('id')->on('media_types')->onUpdate('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('user_added_platforms');
    }
}
