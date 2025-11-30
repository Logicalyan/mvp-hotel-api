<?php

namespace App\Http\Controllers;

use App\ApiResponses;
use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

        return $this->success($room->load('roomType'), "Room Created Successfully", 201);
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

     public function bulkStore(Request $request, $roomTypeId)
    {
        // Validasi room type exists
        $roomType = RoomType::findOrFail($roomTypeId);

        $validated = $request->validate([
            'rooms' => 'required|array|min:1|max:100', // max 100 rooms per request
            'rooms.*.room_number' => 'required|string|max:50|distinct',
            'rooms.*.floor' => 'nullable|string|max:50',
            'rooms.*.status' => 'sometimes|in:available,occupied,maintenance',
            'rooms.*.is_active' => 'sometimes|boolean',
        ]);

        // Check for duplicate room numbers in database
        $roomNumbers = collect($validated['rooms'])->pluck('room_number');
        $existingRooms = Room::whereIn('room_number', $roomNumbers)->pluck('room_number');
        
        if ($existingRooms->isNotEmpty()) {
            return $this->error("Room numbers already exist: " . $existingRooms->implode(', '), 422);
        }

        DB::beginTransaction();
        try {
            $createdRooms = [];
            
            foreach ($validated['rooms'] as $roomData) {
                $createdRooms[] = Room::create([
                    'room_type_id' => $roomTypeId,
                    'room_number' => $roomData['room_number'],
                    'floor' => $roomData['floor'] ?? null,
                    'status' => $roomData['status'] ?? 'available',
                    'is_active' => $roomData['is_active'] ?? true,
                ]);
            }

            DB::commit();

            return $this->success(
                Room::with('roomType')->whereIn('id', collect($createdRooms)->pluck('id'))->get(),
                count($createdRooms) . " rooms created successfully",
                201
            );

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error("Failed to create rooms: " . $e->getMessage(), 500);
        }
    }

    /**
     * Auto-generate room numbers with pattern
     * POST /api/room-types/{room_type_id}/rooms/auto-generate
     */
    public function autoGenerate(Request $request, $roomTypeId)
    {
        $roomType = RoomType::findOrFail($roomTypeId);

        $validated = $request->validate([
            'start_floor' => 'required|integer|min:1',
            'end_floor' => 'required|integer|min:1|gte:start_floor',
            'rooms_per_floor' => 'required|integer|min:1|max:50',
            'room_number_prefix' => 'nullable|string|max:10', // misal: "A", "B", "VIP"
            'starting_room_number' => 'required|integer|min:1|max:99', // misal: 01, 10
            'status' => 'sometimes|in:available,occupied,maintenance',
        ]);

        $rooms = [];
        $prefix = $validated['room_number_prefix'] ?? '';

        for ($floor = $validated['start_floor']; $floor <= $validated['end_floor']; $floor++) {
            for ($roomNum = $validated['starting_room_number']; $roomNum < $validated['starting_room_number'] + $validated['rooms_per_floor']; $roomNum++) {
                $roomNumber = $prefix . $floor . str_pad($roomNum, 2, '0', STR_PAD_LEFT);
                
                $rooms[] = [
                    'room_number' => $roomNumber,
                    'floor' => (string) $floor,
                ];
            }
        }

        // Check duplicates
        $roomNumbers = collect($rooms)->pluck('room_number');
        $existingRooms = Room::whereIn('room_number', $roomNumbers)->pluck('room_number');
        
        if ($existingRooms->isNotEmpty()) {
            return $this->error("Some room numbers already exist: " . $existingRooms->implode(', '), 422);
        }

        DB::beginTransaction();
        try {
            $createdRooms = [];
            
            foreach ($rooms as $roomData) {
                $createdRooms[] = Room::create([
                    'room_type_id' => $roomTypeId,
                    'room_number' => $roomData['room_number'],
                    'floor' => $roomData['floor'],
                    'status' => $validated['status'] ?? 'available',
                    'is_active' => true,
                ]);
            }

            DB::commit();

            return $this->success(
                [
                    'generated_count' => count($createdRooms),
                    'rooms' => Room::with('roomType')->whereIn('id', collect($createdRooms)->pluck('id'))->get()
                ],
                count($createdRooms) . " rooms auto-generated successfully",
                201
            );

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error("Failed to generate rooms: " . $e->getMessage(), 500);
        }
    }

    /**
     * Get available rooms by room type for booking
     */
    public function availableByRoomType(Request $request, $roomTypeId)
    {
        $roomType = RoomType::with(['prices', 'facilities', 'images'])->findOrFail($roomTypeId);

        $validated = $request->validate([
            'check_in_date' => 'required|date',
            'check_out_date' => 'required|date|after:check_in_date',
        ]);

        // Get available rooms (not occupied and not reserved during these dates)
        $availableRooms = Room::where('room_type_id', $roomTypeId)
            ->where('status', 'available')
            ->where('is_active', true)
            ->whereDoesntHave('reservations', function ($query) use ($validated) {
                $query->where('reservation_status', '!=', 'cancelled')
                    ->where(function ($q) use ($validated) {
                        $q->whereBetween('check_in_date', [$validated['check_in_date'], $validated['check_out_date']])
                          ->orWhereBetween('check_out_date', [$validated['check_in_date'], $validated['check_out_date']])
                          ->orWhere(function ($subQ) use ($validated) {
                              $subQ->where('check_in_date', '<=', $validated['check_in_date'])
                                   ->where('check_out_date', '>=', $validated['check_out_date']);
                          });
                    });
            })
            ->get();

        return $this->success([
            'room_type' => $roomType,
            'available_rooms' => $availableRooms,
            'available_count' => $availableRooms->count(),
        ], "Available rooms retrieved successfully", 200);
    }

    public function destroy($id)
    {
        $room = Room::findOrFail($id);
        $room->delete();

        return $this->success($room,'Room Deleted Successfully', 200);
    }
}
