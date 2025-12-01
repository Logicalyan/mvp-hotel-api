<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('room_reservations', function (Blueprint $table) {
            $table->id();

            // user (nullable karena tamu bisa walk-in)
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // room type (snapshot tipe)
            $table->foreignId('room_type_id')->constrained()->cascadeOnDelete();

            // room (wajib, karena user memilih kamar langsung)
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();

            // kode unik reservasi (contoh: RSV-20250105-XXXX)
            $table->string('reservation_code')->unique();

            // tanggal reservasi
            $table->date('check_in_date');
            $table->date('check_out_date');
            $table->unsignedInteger('nights');

            // data tamu
            $table->string('guest_name');
            $table->string('guest_phone');
            $table->string('guest_email')->nullable();

            // harga final (yang sudah dikunci)
            $table->unsignedBigInteger('total_price');

            // status pembayaran
            $table->enum('payment_status', [
                'pending', 'awaiting_payment', 'paid', 'failed', 'refunded'
            ])->default('pending');

            // status reservasi
            $table->enum('reservation_status', [
                'booked', 'checked_in', 'checked_out', 'cancelled'
            ])->default('booked');

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('room_reservations');
    }
};
