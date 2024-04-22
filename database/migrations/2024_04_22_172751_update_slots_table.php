<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('slots', function (Blueprint $table) {
            $table->integer('id_city')->nullable()->after('id');
            $table->integer('kef')->default(1)->after('kol');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('balance_history', function (Blueprint $table) {
            $table->dropColumn('id_city');
            $table->dropColumn('kef');
        });
    }
};
