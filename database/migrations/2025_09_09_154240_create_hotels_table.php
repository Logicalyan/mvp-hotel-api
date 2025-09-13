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
        Schema::create('hotels', function (Blueprint $table) {
            $table->id();
            $table->string("name");
            $table->string("description");
            $table->string("address");
            $table->foreignId("sub_district_id")->constrained("sub_districts")->onDelete("cascade");
            $table->foreignId("district_id")->constrained("districts")->onDelete("cascade");
            $table->foreignId("city_id")->constrained("cities")->onDelete("cascade");
            $table->foreignId("province_id")->constrained("provinces")->onDelete("cascade");
            $table->string("phone_number");
            $table->string("email");
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hotels');
    }
};
