<?php

namespace App\Filters;

use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder; // ⬅️ ini yang penting

class UserFilter
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

    // contoh filter
    public function role(string $value): Builder
    {
        return $this->builder->whereHas('roles', function ($q) use ($value) {
            $q->where("slug", $value);
        });
    }


    public function status(string $value): Builder
    {
        return $this->builder->where('status', $value);
    }

    public function sort(string $value): Builder
    {
        [$field, $direction] = explode(',', $value);
        $direction = strtolower($direction) === 'desc' ? 'desc' : 'asc';

        $sortable = ['name', 'email', 'created_at']; // whitelist
        if (in_array($field, $sortable)) {
            return $this->builder->orderBy($field, $direction);
        }

        return $this->builder;
    }

    public function search($value)
    {
        $columns = ['name', 'email']; // whitelist kolom
        return $this->builder->where(function ($q) use ($columns, $value) {
            foreach ($columns as $col) {
                $q->orWhere($col, 'LIKE', "%{$value}%");
            }
        });
    }
}
