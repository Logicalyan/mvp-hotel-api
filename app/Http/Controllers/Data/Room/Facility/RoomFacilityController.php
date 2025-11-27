<?php

namespace App\Http\Controllers\Data\Room\Facility;

use App\ApiResponses;
use App\Http\Controllers\Controller;
use App\Models\RoomTypeFacility;
use Illuminate\Http\Request;

class RoomFacilityController extends Controller
{
    use ApiResponses;

    public function index(){
        $query = RoomTypeFacility::query();
        $perPage = request()->get('per_page', 10);
        $perPage = min(max((int) $perPage, 1), 100);
        $facilities = $query->paginate($perPage);

        return $this->success($facilities, "Facilities Retrivied Successfully", 200);
    }

    public function store(Request $request) {
        $validate = $request->validate([
            'name' => 'required|string'
        ]);

        $facilities = RoomTypeFacility::create($validate);
        return $this->success($facilities, "Facility Create Successfully", 201);
    }

    public function update(RoomTypeFacility $roomTypeFacility, Request $request){
        $validate = $request->validate([
            'name' => 'sometimes|string|max:255',
        ]);

        $roomTypeFacility->update($validate);

        return $this->success($roomTypeFacility, "Updated BedType Successfully", 200);
    }

    public function destroy(RoomTypeFacility $roomTypeFacility){
        $roomTypeFacility->delete();
        return $this->success(null, "Facility Deleted Successfully", 200);
    }
}
