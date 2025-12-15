<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // payment_status
        DB::statement("
            ALTER TABLE room_reservations
            MODIFY payment_status
            ENUM('pending', 'awaiting_payment', 'paid', 'failed', 'expired', 'refunded')
            NOT NULL
        ");

        // reservation_status
        DB::statement("
            ALTER TABLE room_reservations
            MODIFY reservation_status
            ENUM('booked', 'checked_in', 'checked_out', 'cancelled', 'expired')
            NOT NULL
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE room_reservations
            MODIFY payment_status
            ENUM('pending', 'awaiting_payment', 'paid', 'failed', 'refunded')
        ");

        DB::statement("
            ALTER TABLE room_reservations
            MODIFY reservation_status
            ENUM('booked', 'checked_in', 'checked_out', 'cancelled')
        ");
    }
};

