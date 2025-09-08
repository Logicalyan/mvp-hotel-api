<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class District extends Model
{
    protected $fillable = ['city_id', 'name'];

    public function city() {
        $this->belongsTo(City::class);
    }
}
