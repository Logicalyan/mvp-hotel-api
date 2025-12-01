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
        Schema::create('late_check_out_rules', function (Blueprint $table) {
            $table->id();
            $table->integer('start_hour'); // jam setelah checkout standar (misal 0 = langsung setelah batas)
            $table->integer('end_hour')->nullable(); // null = sampai seterusnya
            $table->integer('fee_type'); // 1=fixed, 2=percentage
            $table->decimal('fee_value', 10, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('late_check_out_rules');
    }
};
