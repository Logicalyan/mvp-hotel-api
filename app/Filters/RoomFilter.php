<?php

namespace App\Filters;

use Illuminate\Database\Eloquent\Builder;

class RoomFilter
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

        // Filter by room_type_id
        if ($this->request->has('room_type_id') && $this->request->room_type_id !== null) {
            $this->filterByRoomType($this->request->room_type_id);
        }

        // Filter by status
        if ($this->request->has('status') && $this->request->status !== null) {
            $this->filterByStatus($this->request->status);
        }

        // Filter by floor
        if ($this->request->has('floor') && $this->request->floor !== null) {
            $this->filterByFloor($this->request->floor);
        }

        // Filter by is_active
        if ($this->request->has('is_active') && $this->request->is_active !== null) {
            $this->filterByActiveStatus($this->request->is_active);
        }

        // Search by room_number
        if ($this->request->has('search') && $this->request->search !== null) {
            $this->search($this->request->search);
        }

        // Sorting
        if ($this->request->has('sort') && $this->request->sort !== null) {
            $this->sort($this->request->sort);
        }

        return $this->builder;
    }

    protected function filterByRoomType($roomTypeId)
    {
        $this->builder->where('room_type_id', $roomTypeId);
    }

    protected function filterByStatus($status)
    {
        $this->builder->where('status', $status);
    }

    protected function filterByFloor($floor)
    {
        $this->builder->where('floor', $floor);
    }

    protected function filterByActiveStatus($isActive)
    {
        $this->builder->where('is_active', filter_var($isActive, FILTER_VALIDATE_BOOLEAN));
    }

    protected function search($search)
    {
        $this->builder->where('room_number', 'like', "%{$search}%");
    }

    protected function sort($sort)
    {
        // Format: "field,direction" contoh: "room_number,asc"
        $sortParts = explode(',', $sort);

        if (count($sortParts) === 2) {
            $field = $sortParts[0];
            $direction = $sortParts[1];

            $allowedFields = ['room_number', 'floor', 'status', 'created_at'];
            $allowedDirections = ['asc', 'desc'];

            if (in_array($field, $allowedFields) && in_array($direction, $allowedDirections)) {
                $this->builder->orderBy($field, $direction);
            }
        }
    }
}
