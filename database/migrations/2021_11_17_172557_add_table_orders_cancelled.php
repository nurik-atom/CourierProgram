<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTableOrdersCancelled extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('orders_cancelled', function (Blueprint $table) {
            $table->id();
            $table->integer("id_order");
            $table->integer("id_who")->nullable();
            $table->smallInteger("who");
            $table->string("cause")->nullable();
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
        Schema::dropIfExists('orders_cancelled');
    }
}
