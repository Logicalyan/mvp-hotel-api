<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubDistrict extends Model
{
    protected $fillable = ['district_id', 'name'];

    public function district() {
        $this->belongsTo(District::class);
    }
}
