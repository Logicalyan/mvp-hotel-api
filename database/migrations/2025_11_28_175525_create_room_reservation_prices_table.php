<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('room_reservation_prices', function (Blueprint $table) {
            $table->id();

            $table->foreignId('reservation_id')
                ->constrained('room_reservations')
                ->cascadeOnDelete();

            // tanggal malam yang dihitung
            $table->date('date');

            // harga final per malam (snapshot)
            $table->unsignedBigInteger('price');

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('room_reservation_prices');
    }
};
