<?php

namespace App\Http\Controllers;

use App\Models\Room;
use Illuminate\Http\Request;

class RoomController extends Controller
{
    public function index()
    {
        $rooms = Room::with('roomType')->get();

        return response()->json([
            "success" => true,
            "data" => $rooms
        ]);
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

        return response()->json([
            "success" => true,
            "message" => "Room created successfully",
            "data" => $room->load('roomType')
        ], 201);
    }

    public function show($id)
    {
        $room = Room::with('roomType')->findOrFail($id);

        return response()->json([
            "success" => true,
            "data" => $room
        ]);
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

        return response()->json([
            "success" => true,
            "message" => "Room updated successfully",
            "data" => $room->load('roomType')
        ]);
    }

    public function destroy($id)
    {
        $room = Room::findOrFail($id);
        $room->delete();

        return response()->json([
            "success" => true,
            "message" => "Room deleted successfully"
        ]);
    }
}
