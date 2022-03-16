<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCommentPhoneToOrders extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->text("comments")->after("id_courier")->nullable();
            $table->string("cafe_phone",12)->after("cafe_name")->nullable();
//            $table->renameColumn("phone", "user_phone");
//            $table->renameColumn("name", "user_name");
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
            $table->dropColumn("comments");
            $table->dropColumn("cafe_phone");
//            $table->renameColumn("user_phone", "phone");
//            $table->renameColumn("user_name", "name");
        });
    }
}
