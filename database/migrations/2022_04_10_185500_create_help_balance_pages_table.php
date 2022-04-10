<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateHelpBalancePagesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('help_balance_pages', function (Blueprint $table) {
            $table->id();
            $table->string("name",255)->nullable();
            $table->string("icon",255)->nullable();
            $table->string("type",255)->nullable();
            $table->text("big_text")->nullable();
            $table->integer("sort")->default(999);
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
        Schema::dropIfExists('help_balance_pages');
    }
}
