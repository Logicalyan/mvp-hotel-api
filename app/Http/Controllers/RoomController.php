<?php

namespace App\Http\Controllers;

use App\ApiResponses;
use App\Models\Room;
use Illuminate\Http\Request;

class RoomController extends Controller
{

    use ApiResponses;

    public function index()
    {
        $query = Room::with('roomType');
        $perPage = request()->get('per_page', 10);
        $perPage = min(max((int) $perPage, 1), 100);
        $rooms = $query->paginate($perPage);

        return $this->success($rooms, "Rooms Retrivied Successfully", 200);
    }

    public function store(Request $request)
    {
        $validate = $request->validate([
            "room_type_id" => "required|exists:room_types,id",
            "room_number" => "required|string|max:50|unique:rooms,room_number",
            "floor" => "nullable|string|max:50",
            "status" => "required|in:available,occupied,maintenance",
            "is_active" => "boolean"
        ]);

        $room = Room::create($validate);

        return $this->success($room->load('room_type'), "Room Created Successfully", 201);
    }

    public function show($id)
    {
        $room = Room::with('roomType')->findOrFail($id);

        return $this->success($room, "Room get by id successfully", 200);
    }

    public function update(Request $request, $id)
    {
        $room = Room::findOrFail($id);

        $validate = $request->validate([
            "room_type_id" => "sometimes|exists:room_types,id",
            "room_number" => "sometimes|string|max:50|unique:rooms,room_number,".$room->id,
            "floor" => "nullable|string|max:50",
            "status" => "sometimes|in:available,occupied,maintenance",
            "is_active" => "boolean"
        ]);

        $room->update($validate);

        return $this->success($room->load('roomType'), "Room updated successfully", 200);
    }

    public function destroy($id)
    {
        $room = Room::findOrFail($id);
        $room->delete();

        return $this->success($room,'Room Deleted Successfully', 200);
    }
}
