<?php

namespace App\Filters;

use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;

class RoomFilter
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

    public function hotel_id($value)
    {
        return $this->builder->where('hotel_id', $value);
    }

    public function status($value)
    {
        return $this->builder->where('status', $value);
    }

    public function floor($value)
    {
        return $this->builder->where('floor', $value);
    }

    public function capacity($value)
    {
        return $this->builder->where('capacity', '>=', $value);
    }

    public function price($value)
    {
        // format request: price=min,max
        [$min, $max] = explode(',', $value);
        return $this->builder->whereBetween('price', [(float) $min, (float) $max]);
    }

    public function search($value)
    {
        return $this->builder->where(function ($q) use ($value) {
            $q->where('name', 'LIKE', "%{$value}%")
                ->orWhere('room_number', 'LIKE', "%{$value}%");
        });
    }
}
