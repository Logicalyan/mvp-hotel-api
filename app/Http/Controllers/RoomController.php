<?php

namespace App\Http\Controllers;

use App\ApiResponses;
use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RoomController extends Controller
{
    use ApiResponses;

    public function index()
    {
        $query = Room::with('roomType', 'roomType.hotel');
        $perPage = request()->get('per_page', 10);
        $perPage = min(max((int) $perPage, 1), 100);
        $rooms = $query->paginate($perPage);

        return $this->success($rooms, "Rooms Retrieved Successfully", 200);
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            // Validasi dasar - HAPUS validasi unique di sini
            $validated = $request->validate([
                "room_type_id" => "required|exists:room_types,id",
                "creation_type" => "required|in:single,bulk,auto",
                
                // Untuk single room
                "room_number" => "required_if:creation_type,single|string|max:50", // HAPUS unique
                "floor" => "nullable|string|max:50",
                "status" => "sometimes|in:available,occupied,maintenance",
                "is_active" => "boolean",
                
                // Untuk bulk rooms
                "rooms" => "required_if:creation_type,bulk|array|min:1|max:100",
                "rooms.*.room_number" => "required|string|max:50", // HAPUS distinct
                "rooms.*.floor" => "nullable|string|max:50",
                "rooms.*.status" => "sometimes|in:available,occupied,maintenance",
                "rooms.*.is_active" => "sometimes|boolean",
                
                // Untuk auto generate
                "auto_generate" => "required_if:creation_type,auto|array",
                "auto_generate.start_floor" => "required_if:creation_type,auto|integer|min:1",
                "auto_generate.end_floor" => "required_if:creation_type,auto|integer|min:1|gte:auto_generate.start_floor",
                "auto_generate.rooms_per_floor" => "required_if:creation_type,auto|integer|min:1|max:50",
                "auto_generate.room_number_prefix" => "nullable|string|max:10",
                "auto_generate.starting_room_number" => "required_if:creation_type,auto|integer|min:1|max:99",
            ]);

            $roomType = RoomType::findOrFail($validated['room_type_id']);
            $createdRooms = [];

            switch ($validated['creation_type']) {
                case 'single':
                    // Check duplicate untuk single room
                    $this->checkDuplicateRoomNumbers([$validated['room_number']]);
                    
                    $room = Room::create([
                        'room_type_id' => $validated['room_type_id'],
                        'room_number' => $validated['room_number'],
                        'floor' => $validated['floor'] ?? null,
                        'status' => $validated['status'] ?? 'available',
                        'is_active' => $validated['is_active'] ?? true,
                    ]);
                    $createdRooms[] = $room;
                    break;

                case 'bulk':
                    // Check duplicates untuk bulk rooms
                    $roomNumbers = collect($validated['rooms'])->pluck('room_number');
                    $this->checkDuplicateRoomNumbers($roomNumbers);

                    foreach ($validated['rooms'] as $roomData) {
                        $createdRooms[] = Room::create([
                            'room_type_id' => $validated['room_type_id'],
                            'room_number' => $roomData['room_number'],
                            'floor' => $roomData['floor'] ?? null,
                            'status' => $roomData['status'] ?? 'available',
                            'is_active' => $roomData['is_active'] ?? true,
                        ]);
                    }
                    break;

                case 'auto':
                    // Auto generate rooms
                    $autoConfig = $validated['auto_generate'];
                    $rooms = $this->generateRoomNumbers($autoConfig);
                    $roomNumbers = collect($rooms)->pluck('room_number');
                    
                    $this->checkDuplicateRoomNumbers($roomNumbers);

                    foreach ($rooms as $roomData) {
                        $createdRooms[] = Room::create([
                            'room_type_id' => $validated['room_type_id'],
                            'room_number' => $roomData['room_number'],
                            'floor' => $roomData['floor'],
                            'status' => $validated['status'] ?? 'available',
                            'is_active' => $validated['is_active'] ?? true,
                        ]);
                    }
                    break;
            }

            DB::commit();

            return $this->success(
                [
                    'created_count' => count($createdRooms),
                    'rooms' => Room::with('roomType')->whereIn('id', collect($createdRooms)->pluck('id'))->get()
                ],
                count($createdRooms) . " room(s) created successfully",
                201
            );

        } catch (ValidationException $e) {
            DB::rollBack();
            return $this->error("Validation failed: " . implode(', ', Arr::flatten($e->errors())), 422);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Room creation error: ' . $e->getMessage());
            return $this->error("Failed to create rooms: " . $e->getMessage(), 500);
        }
    }

    /**
     * Check for duplicate room numbers
     */
    private function checkDuplicateRoomNumbers($roomNumbers)
    {
        $existingRooms = Room::whereIn('room_number', $roomNumbers)->pluck('room_number');
        
        if ($existingRooms->isNotEmpty()) {
            throw ValidationException::withMessages([
                'room_number' => ["Room numbers already exist: " . $existingRooms->implode(', ')]
            ]);
        }
    }

    /**
     * Generate room numbers based on configuration
     */
    private function generateRoomNumbers($config)
    {
        $rooms = [];
        $prefix = $config['room_number_prefix'] ?? '';

        for ($floor = $config['start_floor']; $floor <= $config['end_floor']; $floor++) {
            for ($roomNum = $config['starting_room_number']; $roomNum < $config['starting_room_number'] + $config['rooms_per_floor']; $roomNum++) {
                $roomNumber = $prefix . $floor . str_pad($roomNum, 2, '0', STR_PAD_LEFT);
                
                $rooms[] = [
                    'room_number' => $roomNumber,
                    'floor' => (string) $floor,
                ];
            }
        }

        return $rooms;
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