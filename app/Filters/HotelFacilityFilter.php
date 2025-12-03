<?php

namespace App\Filters;

use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;

class HotelFacilityFilter
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

    /** 🔍 Search by name */
    public function search($value): Builder
    {
        return $this->builder->where('name', 'LIKE', "%{$value}%");
    }

    /** 📌 Sorting: ?sort=name,asc  */
    public function sort($value): Builder
    {
        // format: ?sort=name,desc
        [$field, $direction] = explode(',', $value);

        $direction = strtolower($direction) === 'desc' ? 'desc' : 'asc';

        // whitelist kolom yang boleh di-sort
        $sortable = ['name', 'created_at', 'updated_at'];

        if (in_array($field, $sortable)) {
            return $this->builder->orderBy($field, $direction);
        }

        return $this->builder; // kalau field tidak valid → ignore
    }
}
