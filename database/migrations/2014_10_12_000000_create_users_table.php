<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');
            $table->string('username')->unique();
            $table->string('email')->unique()->nullable();
            $table->string('phone')->unique()->nullable();
            $table->string('full_name');
            $table->string('password');
            $table->unsignedBigInteger('gender_id');
            $table->string('avatar')->nullable();
            $table->string('bio')->nullable();
            $table->string('web_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('user_verified_at')->nullable();
            // $table->boolean('term_conditions_agreed')->default(true);
            $table->timestamps();

            $table->foreign('gender_id')->references('id')->on('genders')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('users');
    }
}
