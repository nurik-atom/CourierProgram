<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateNotificationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->integer("id_user")->nullable();
            $table->integer("id_city")->nullable();
            $table->integer("howmany_open")->default(0);
            $table->string("name")->nullable();
            $table->string("short_text")->nullable();
            $table->text("full_text")->nullable();
            $table->dateTime("actual_time")->nullable();
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
        Schema::dropIfExists('notifications');
    }
}
