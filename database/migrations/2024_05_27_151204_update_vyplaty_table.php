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
        Schema::table('vyplaty', function (Blueprint $table) {
            $table->date('date_from')->nullable()->after('id_user');
            $table->date('date_to')->nullable()->after('date_from');
            $table->dropColumn('period');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vyplaty', function (Blueprint $table) {
            $table->dropColumn('date_from');
            $table->dropColumn('date_to');
            $table->string('period')->nullable();
        });
    }
};
