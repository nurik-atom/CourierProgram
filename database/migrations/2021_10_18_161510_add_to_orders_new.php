<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddToOrdersNew extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->integer("id_cafe")->after("id_allfood");
            $table->string("cafe_name")->after("id_cafe");
            $table->integer("id_city")->after("id");
            $table->text("from_geo");
            $table->text("from_address");
            $table->text("to_geo");
            $table->text("to_address");
            $table->integer("summ_order");
            $table->integer("price_delivery");
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
            $table->dropColumn("id_cafe");
            $table->dropColumn("cafe_name");
            $table->dropColumn("id_city");
            $table->dropColumn("from_geo");
            $table->dropColumn("from_address");
            $table->dropColumn("to_geo");
            $table->dropColumn("to_address");
            $table->dropColumn("summ_order");
            $table->dropColumn("price_delivery");
        });
    }
}
