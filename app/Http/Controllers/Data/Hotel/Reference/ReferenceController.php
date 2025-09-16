<?php

namespace App\Http\Controllers\Data\Hotel\Reference;

use App\ApiResponses;
use App\Http\Controllers\Controller;
use App\Models\Province;
use App\Models\City;
use App\Models\District;
use App\Models\SubDistrict;
use App\Models\HotelFacility;
use Illuminate\Http\Request;

class ReferenceController extends Controller
{
    use ApiResponses;
    public function provinces()
    {
        $provinces = Province::select('id', 'name')
            ->orderBy('name')
            ->get();

        return $this->success($provinces, "Provinces retrieved successfully");
    }

    public function cities(Request $request)
    {
        $query = City::select('id', 'name', 'province_id')
            ->orderBy('name');

        if ($request->filled('province_id')) {
            $query->where('province_id', $request->province_id);
        }

        $cities = $query->get();

        return $this->success($cities, "Cities retrieved successfully");
    }

    public function districts(Request $request)
    {
        $query = District::select('id', 'name', 'city_id')
            ->orderBy('name');

        if ($request->filled('city_id')) {
            $query->where('city_id', $request->city_id);
        }

        $districts = $query->get();

        return $this->success($districts, "Districts retrieved successfully");
    }

    public function subDistricts(Request $request)
    {
        $query = SubDistrict::select('id', 'name', 'district_id')
            ->orderBy('name');

        if ($request->filled('district_id')) {
            $query->where('district_id', $request->district_id);
        }

        $subDistricts = $query->get();

        return $this->success($subDistricts, "Sub districts retrieved successfully");
    }

    public function facilities()
    {
        $facilities = HotelFacility::select('id', 'name')
            ->orderBy('name')
            ->get();

        return $this->success($facilities, "Facilities retrieved successfully");
    }
}
