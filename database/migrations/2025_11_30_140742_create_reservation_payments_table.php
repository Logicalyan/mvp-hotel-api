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
        Schema::create('reservation_payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('reservation_id')
                ->constrained('room_reservations')
                ->cascadeOnDelete();

            // informasi pembayaran
            $table->string('payment_type')->nullable();
            $table->string('transaction_id')->nullable();
            $table->string('fraud_status')->nullable();

            $table->integer('gross_amount')->default(0);

            // optional
            $table->string('bank')->nullable();
            $table->string('va_number')->nullable();
            $table->string('qr_reference')->nullable();

            $table->enum('status', ['pending', 'paid', 'failed'])
                ->default('pending');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservation_payments');
    }
};
