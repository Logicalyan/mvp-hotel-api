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
        Schema::table('room_reservations', function (Blueprint $table) {
            $table->dateTime('planned_check_in')->nullable();
            $table->dateTime('planned_check_out')->nullable();

            $table->dateTime('actual_check_in')->nullable();
            $table->dateTime('actual_check_out')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('room_reservations', function (Blueprint $table) {
            $table->dateTime('planned_check_in');
            $table->dateTime('planned_check_out');

            $table->dateTime('actual_check_in')->nullable();
            $table->dateTime('actual_check_out')->nullable();
        });
    }
};
