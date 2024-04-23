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
        Schema::create('slots_users', function (Blueprint $table) {
            $table->id();
            $table->integer('id_slot');
            $table->integer('id_user');
            $table->integer('status')->default(1);
            $table->text('prichina_otmena')->nullable();
            $table->integer('who')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('slots_users');
    }
};
