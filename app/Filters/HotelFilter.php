<?php

namespace App\Filters;

use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;

class HotelFilter
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

    public function search(string $value): Builder
    {
        $columns = ['name', 'description', 'address'];
        return $this->builder->where(function ($q) use ($columns, $value) {
            foreach ($columns as $col) {
                $q->orWhere($col, 'LIKE', "%{$value}%");
            }
        });
    }

    public function city_id($value): Builder
    {
        return $this->builder->where('city_id', $value);
    }

    public function district_id($value): Builder
    {
        return $this->builder->where('district_id', $value);
    }

    public function sub_district_id($value): Builder
    {
        return $this->builder->where('sub_district_id', $value);
    }

    public function province_id($value): Builder
    {
        return $this->builder->where('province_id', $value);
    }

    public function facilities($facilityIds): Builder
    {
        if (is_string($facilityIds)) {
            $facilityIds = explode(',', $facilityIds);
        }

        return $this->builder->whereHas('facilities', function ($q) use ($facilityIds) {
            $q->whereIn('facilities.id', $facilityIds);
        }, '=', count($facilityIds));
    }

    public function sort(string $value): Builder
    {
        [$field, $direction] = explode(',', $value);
        $direction = strtolower($direction) === 'desc' ? 'desc' : 'asc';

        $sortable = ['name', 'created_at'];
        if (in_array($field, $sortable)) {
            return $this->builder->orderBy($field, $direction);
        }

        return $this->builder;
    }
}
