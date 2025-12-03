<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;


class HotelFacility extends Model
{
    protected $fillable = ['name'];

    public function hotels()
    {
        return $this->belongsToMany(Hotel::class, 'facility_hotel');
    }

    public function scopeFilter($query, $filters)
    {
        return $filters->apply($query);
    }
}
