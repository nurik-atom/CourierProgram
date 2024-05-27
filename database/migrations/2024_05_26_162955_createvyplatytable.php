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
        Schema::create('vyplaty', function (Blueprint $table) {
            $table->id();
            $table->string('period')->nullable();
            $table->integer('summa')->nullable();
            $table->integer('bazavaya')->nullable();
            $table->integer('bonus')->nullable();
            $table->integer('kef')->nullable();
            $table->integer('nalogi')->nullable();
            $table->integer('kol_orders')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vyplaty');
    }
};
