<?php

namespace App\Http\Controllers;

use App\ApiResponses;
use App\Filters\RoomFilter;
use App\Models\Hotel;
use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Http\Request;

class RoomController extends Controller
{

    use ApiResponses;

    public function index()
    {
        $query = Room::with('roomType', 'roomType.hotel');
        $perPage = request()->get('per_page', 10);
        $perPage = min(max((int) $perPage, 1), 100);
        $rooms = $query->paginate($perPage);

        return $this->success($rooms, "Rooms Retrivied Successfully", 200);
    }

    public function indexByHotelId(Request $request, RoomFilter $filters)
    {
        // Ambil hotel_id dari route parameter
        $hotelId = $request->route('hotel_id');

        // Validasi hotel exists (auto 404 kalau gak ada)
        Hotel::findOrFail($hotelId);

        // Base query: rooms yang room_type-nya punya hotel_id ini
        $baseQuery = Room::query()
            ->whereHas('roomType', function ($query) use ($hotelId) {
                $query->where('hotel_id', $hotelId);
            })
            ->with(['roomType']);

        // Apply filters (status, floor, room_type, dll)
        $query = $filters->apply($baseQuery);

        // Pagination
        $perPage = request()->get('per_page', 10);
        $perPage = min(max((int) $perPage, 1), 100);

        $rooms = $query->paginate($perPage);

        return $this->success($rooms, "Rooms for hotel retrieved successfully", 200);
    }

    public function showByRoomTypeId(Request $request)
    {
        $hotelId = $request->route('hotel_id');
        $roomTypeId = $request->route('room_type_id');
        $roomId = $request->route('room_id');

        // ✅ Validasi hotel exists
        // Hotel::findOrFail($hotelId);

        $room = Room::with('roomType.hotel')
        ->whereHas('roomType', function($query) use ($hotelId) {
            $query->where('hotel_id', $hotelId);
        })
        ->where('room_type_id', $roomTypeId)
        ->where('id', $roomId)
        ->firstOrFail();

    return $this->success($room, "Room retrieved successfully", 200);
    }

    public function indexByRoomTypeId(Request $request, RoomFilter $filters)
    {
        // Ambil hotel_id dan room_type_id dari route parameter
        $hotelId = $request->route('hotel_id');
        $roomTypeId = $request->route('room_type_id');

        // Validasi hotel exists
        Hotel::findOrFail($hotelId);

        // Validasi room type exists dan belongs to hotel
        $roomType = RoomType::where('id', $roomTypeId)
            ->where('hotel_id', $hotelId)
            ->firstOrFail();

        // Base query dengan filter room_type_id
        $baseQuery = Room::query()
            ->where('room_type_id', $roomTypeId)
            ->with(['roomType.hotel']);

        // Apply filters
        $query = $filters->apply($baseQuery);

        // Pagination
        $perPage = request()->get('per_page', 10);
        $perPage = min(max((int) $perPage, 1), 100);

        $rooms = $query->paginate($perPage);

        return $this->success($rooms, "Rooms for room type retrieved successfully", 200);
    }

    public function store(Request $request)
    {
        $hotelId = $request->route('hotel_id');

        // Validasi hotel exists
        Hotel::findOrFail($hotelId);

        // Validasi room_type_id belongs to this hotel
        $roomType = RoomType::where('id', $request->room_type_id)
            ->where('hotel_id', $hotelId)
            ->firstOrFail();

        $validate = $request->validate([
            "room_type_id" => "required|exists:room_types,id",
            "room_number" => "required|string|max:50|unique:rooms,room_number",
            "floor" => "nullable|max:50",
            "status" => "required|in:available,occupied,maintenance",
            "is_active" => "boolean"
        ]);

        $room = Room::create($validate);

        $room->load(['roomType']);

        return $this->success($room, "Room created successfully", 201);
    }

    public function show($id)
    {
        $room = Room::with(['roomType.hotel'])->findOrFail($id);

        return $this->success($room, "Room retrieved successfully", 200);
    }

    public function update(Request $request, $id)
    {
        $room = Room::findOrFail($id);

        $validate = $request->validate([
            "room_type_id" => "sometimes|exists:room_types,id",
            "room_number" => "sometimes|string|max:50|unique:rooms,room_number," . $room->id,
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

        // Optional: Check if room sedang ada booking
        // if ($room->hasActiveBookings()) {
        //     return $this->error("Cannot delete room with active bookings", 422);
        // }

        $room->delete();

        return $this->success(null, "Room deleted successfully", 200);
    }

    public function toggleStatus($id)
    {
        $room = Room::findOrFail($id);

        $room->update([
            'is_active' => !$room->is_active
        ]);

        $room->load(['roomType']);

        return $this->success($room, "Room status toggled successfully", 200);
    }
}
