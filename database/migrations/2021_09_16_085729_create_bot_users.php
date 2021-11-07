<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateBotUsers extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('bot_users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('user_id');
            $table->string('name')->nullable();
            $table->string('lang')->nullable();
            $table->string('country')->nullable();
            $table->string('city')->nullable();
            $table->string('sex')->nullable();
            $table->integer('age')->nullable();
            $table->string('whom_find')->nullable();
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
        //
    }
}
