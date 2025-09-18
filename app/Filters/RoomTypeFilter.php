<?php

namespace App\Filters;

use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;

class RoomTypeFilter
{
    protected Request $request;
    protected Builder $builder;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function apply(Builder $builder): Builder
    {
        $this->builder = $builder;

        foreach ($this->filters() as $filter => $value) {
            if (method_exists($this, $filter) && $value !== null) {
                $this->$filter($value);
            }
        }

        return $this->builder;
    }

    protected function filters(): array
    {
        return $this->request->all();
    }

    /**
     * 🔍 Search by name / description
     */
    public function search($value): Builder
    {
        $columns = ['name', 'description']; // whitelist kolom untuk search
        return $this->builder->where(function ($q) use ($columns, $value) {
            foreach ($columns as $col) {
                $q->orWhere($col, 'LIKE', "%{$value}%");
            }
        });
    }

    /**
     * Filter berdasarkan hotel_id
     */
    public function hotel_id($value): Builder
    {
        return $this->builder->where('hotel_id', $value);
    }

    /**
     * Filter berdasarkan kapasitas minimal
     */
    public function capacity($value): Builder
    {
        return $this->builder->where('capacity', '>=', $value);
    }

    /**
     * Sorting: ?sort=name,asc
     */
    public function sort(string $value): Builder
    {
        [$field, $direction] = explode(',', $value);
        $direction = strtolower($direction) === 'desc' ? 'desc' : 'asc';

        $sortable = ['name', 'capacity', 'created_at'];
        if (in_array($field, $sortable)) {
            return $this->builder->orderBy($field, $direction);
        }

        return $this->builder;
    }

    public function facilities($facilityIds): Builder
    {
        if (is_string($facilityIds)) {
            $facilityIds = explode(',', $facilityIds);
        }

        return $this->builder->whereHas('facilities', function ($q) use ($facilityIds) {
            $q->whereIn('room_type_facilities.id', $facilityIds);
        }, '=', count($facilityIds));
    }
}
