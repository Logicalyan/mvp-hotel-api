<?php

// ============================================
// 1. FILTER CLASS - app/Filters/ReservationFilter.php
// ============================================

namespace App\Filters;

use Illuminate\Database\Eloquent\Builder;

class ReservationFilter
{
    protected $builder;
    protected $request;

    public function __construct()
    {
        $this->request = request();
    }

    public function apply(Builder $builder): Builder
    {
        $this->builder = $builder;

        // Filter by reservation status
        if ($this->request->has('reservation_status') && $this->request->reservation_status !== null) {
            $this->filterByReservationStatus($this->request->reservation_status);
        }

        // Filter by payment status
        if ($this->request->has('payment_status') && $this->request->payment_status !== null) {
            $this->filterByPaymentStatus($this->request->payment_status);
        }

        // Filter by check-in date (from)
        if ($this->request->has('from_date') && $this->request->from_date !== null) {
            $this->filterFromDate($this->request->from_date);
        }

        // Filter by check-out date (to)
        if ($this->request->has('to_date') && $this->request->to_date !== null) {
            $this->filterToDate($this->request->to_date);
        }

        // Filter by room_id (optional)
        if ($this->request->has('room_id') && $this->request->room_id !== null) {
            $this->filterByRoom($this->request->room_id);
        }

        // Filter by room_type_id (optional)
        if ($this->request->has('room_type_id') && $this->request->room_type_id !== null) {
            $this->filterByRoomType($this->request->room_type_id);
        }

        // Filter by user_id (optional)
        if ($this->request->has('user_id') && $this->request->user_id !== null) {
            $this->filterByUser($this->request->user_id);
        }

        // Search by guest name, phone, email, or reservation code
        if ($this->request->has('search') && $this->request->search !== null) {
            $this->search($this->request->search);
        }

        // Sorting
        if ($this->request->has('sort') && $this->request->sort !== null) {
            $this->sort($this->request->sort);
        } else {
            // Default sorting: newest first
            $this->builder->latest('created_at');
        }

        return $this->builder;
    }

    protected function filterByReservationStatus($status)
    {
        $this->builder->where('reservation_status', $status);
    }

    protected function filterByPaymentStatus($status)
    {
        $this->builder->where('payment_status', $status);
    }

    protected function filterFromDate($fromDate)
    {
        $this->builder->whereDate('check_in_date', '>=', $fromDate);
    }

    protected function filterToDate($toDate)
    {
        $this->builder->whereDate('check_out_date', '<=', $toDate);
    }

    protected function filterByRoom($roomId)
    {
        $this->builder->where('room_id', $roomId);
    }

    protected function filterByRoomType($roomTypeId)
    {
        $this->builder->where('room_type_id', $roomTypeId);
    }

    protected function filterByUser($userId)
    {
        $this->builder->where('user_id', $userId);
    }

    protected function search($search)
    {
        $this->builder->where(function ($query) use ($search) {
            $query->where('guest_name', 'like', "%{$search}%")
                ->orWhere('guest_phone', 'like', "%{$search}%")
                ->orWhere('guest_email', 'like', "%{$search}%")
                ->orWhere('reservation_code', 'like', "%{$search}%");
        });
    }

    protected function sort($sort)
    {
        // Format: "field,direction" contoh: "check_in_date,asc"
        $sortParts = explode(',', $sort);

        if (count($sortParts) === 2) {
            $field = $sortParts[0];
            $direction = $sortParts[1];

            $allowedFields = [
                'check_in_date',
                'check_out_date',
                'created_at',
                'total_price',
                'reservation_status',
                'payment_status',
                'nights',
                'reservation_code'
            ];
            $allowedDirections = ['asc', 'desc'];

            if (in_array($field, $allowedFields) && in_array($direction, $allowedDirections)) {
                $this->builder->orderBy($field, $direction);
            }
        }
    }
}
