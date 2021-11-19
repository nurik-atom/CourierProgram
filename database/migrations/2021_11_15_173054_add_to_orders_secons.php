<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddToOrdersSecons extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->integer("duration_sec")->nullable();
            $table->integer("needed_sec")->nullable();
            $table->integer("distance_matrix")->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn("duration_sec");
            $table->dropColumn("needed_sec");
            $table->dropColumn("distance_matrix");

        });
    }
}
