<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Hotel extends Model
{
    protected $fillable = [
        "name",
        "description",
        "address",
        "sub_district_id",
        "district_id",
        "city_id",
        "province_id",
        "phone_number",
        "email",
    ];

    public function subDistrict()
    {
        return $this->belongsTo(SubDistrict::class);
    }
    public function district()
    {
        return $this->belongsTo(District::class);
    }
    public function city()
    {
        return $this->belongsTo(City::class);
    }
    public function province()
    {
        return $this->belongsTo(Province::class);
    }

    public function images()
    {
        return $this->hasMany(HotelImage::class);
    }
    public function facilities()    
    {
        return $this->hasMany(HotelFacility::class);
    }
}
