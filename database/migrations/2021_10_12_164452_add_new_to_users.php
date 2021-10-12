<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddNewToUsers extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string("birthday")->after("id_city")->nullable();
            $table->string("photo")->after("birthday")->nullable();
            $table->renameColumn("type", "type_transport");
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn("birthday");
            $table->dropColumn("photo");
            $table->renameColumn("type_transport", "type");
        });
    }
}
