<?php

namespace App\Http\Controllers\Data\Hotel\Facility;

use App\ApiResponses;
use App\Http\Controllers\Controller;
use App\Models\Hotel;
use App\Models\HotelFacility;
use Illuminate\Http\Request;
use App\Filters\HotelFacilityFilter;

class HotelFacilityController extends Controller
{
    use ApiResponses;

    public function indexByHotelId(Request $request, HotelFacilityFilter $filters)
    {
        $hotelId = $request->route('hotel_id');
        Hotel::findOrFail($hotelId);

        $baseQuery = HotelFacility::query()
            ->whereHas('hotels', function ($q) use ($hotelId) {
                $q->where('hotel_id', $hotelId);
            });

        $query = $filters->apply($baseQuery); // <-- apply filter

        $perPage = request()->get('per_page', 10);
        $perPage = min(max((int) $perPage, 1), 100);

        $facilities = $query->paginate($perPage);

        return $this->success($facilities, "Facilities for hotel retrieved successfully", 200);
    }

    public function storeForHotel(Request $request, $hotel_id)
    {
        Hotel::findOrFail($hotel_id);

        $facility = HotelFacility::create(
            $request->validate(['name' => 'required|string'])
        );

        // Attach facility ke hotel
        $facility->hotels()->attach($hotel_id);

        return $this->success($facility, "Facility created & attached to hotel", 201);
    }

    public function detachFromHotel($hotel_id, $facility_id)
    {
        $hotel = Hotel::findOrFail($hotel_id);
        $facility = HotelFacility::findOrFail($facility_id);

        // putuskan relasi Pivot
        $hotel->facilities()->detach($facility_id);

        return $this->success(null, "Facility detached from hotel", 200);
    }


    public function index()
    {
        $query = HotelFacility::query()->with('hotels');
        $perPage = request()->get('per_page', 10);
        $perPage = min(max((int) $perPage, 1), 100);
        $facilities = $query->paginate($perPage);

        return $this->success($facilities, "Facilities Retrivied Successfully", 200);
    }

    // public function store(Request $request)
    // {
    //     $validate = $request->validate([
    //         'name' => 'required|string'
    //     ]);

    //     $facilities = HotelFacility::create($validate);
    //     return $this->success($facilities, "Facility Create Successfully", 201);
    // }

    public function update(HotelFacility $hotelFacility, Request $request)
    {
        $validate = $request->validate([
            'name' => 'sometimes|string|max:255',
        ]);

        $hotelFacility->update($validate);

        return $this->success($hotelFacility, "Updated BedType Successfully", 200);
    }

    public function destroy(HotelFacility $hotelFacility)
    {
        $hotelFacility->delete();
        return $this->success(null, "Facility Deleted Successfully", 200);
    }
}
