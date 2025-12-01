<?php

namespace App\Services;

use App\Models\LateCheckoutRule;
use App\Models\LateCheckoutFee;
use App\Models\RoomReservation;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class LateCheckoutService
{
    /**
     * Proses checkout termasuk perhitungan late fee jika terlambat.
     */
    public function processCheckout(RoomReservation $reservation, string $actualCheckout)
    {
        return DB::transaction(function () use ($reservation, $actualCheckout) {

            $actual = Carbon::parse($actualCheckout);
            $planned = Carbon::parse($reservation->planned_check_out);

            // Update actual checkout pada reservation
            $reservation->update([
                'actual_check_out' => $actual
            ]);

            // Tidak terlambat → tidak ada fee
            if ($actual->lte($planned)) {
                return [
                    'late' => false,
                    'hours_late' => 0,
                    'fee' => 0
                ];
            }

            // Hitung selisih jam keterlambatan
            $hoursLate = ceil($planned->diffInMinutes($actual) / 60);

            // Cari rule yang cocok
            $rule = $this->findApplicableRule($hoursLate);

            if (!$rule) {
                return [
                    'late' => true,
                    'hours_late' => $hoursLate,
                    'fee' => 0,
                    'message' => 'No late checkout rule matched.'
                ];
            }

            // Hitung fee
            $fee = $this->calculateFee($rule, $reservation->total_price);

            // Simpan fee
            LateCheckoutFee::create([
                'reservation_id' => $reservation->id,
                'amount' => $fee,
                'hours_late' => $hoursLate,
                'description' => "Late checkout {$hoursLate}h using rule ID {$rule->id}"
            ]);

            return [
                'late' => true,
                'hours_late' => $hoursLate,
                'fee' => $fee,
                'rule' => $rule
            ];
        });
    }

    /**
     * Mencari rule berdasarkan jumlah jam terlambat.
     */
    private function findApplicableRule(int $hoursLate)
    {
        return LateCheckoutRule::where('is_active', true)
            ->where('start_hour', '<=', $hoursLate)
            ->where('end_hour', '>=', $hoursLate)
            ->first();
    }

    /**
     * Hitung fee berdasarkan rule.
     */
    private function calculateFee(LateCheckoutRule $rule, int $totalPrice)
    {
        return match ($rule->fee_type) {
            'flat' => $rule->fee_value,
            'percent' => ceil(($totalPrice * $rule->fee_value) / 100),
            'hourly' => $rule->fee_value, // fee_value berarti per jam
            default => 0,
        };
    }
}
