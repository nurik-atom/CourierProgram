<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateOrderUser extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('order_user', function (Blueprint $table) {
            $table->integer("seconds")->default(0)->after("status");
            $table->string("refuse_text")->nullable()->after("seconds");
            $table->dropColumn("accept_time");
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('order_user', function (Blueprint $table) {
            $table->dropColumn("refuse_text");
            $table->dropColumn("seconds");
        });
    }
}
