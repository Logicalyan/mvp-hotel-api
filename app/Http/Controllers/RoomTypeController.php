<?php

namespace App\Http\Controllers;

use App\ApiResponses;
use App\Filters\RoomTypeFilter;
use App\Models\Hotel;
use App\Models\RoomType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\RoomTypeFacility;
use Illuminate\Support\Facades\DB;

class RoomTypeController extends Controller
{
    use ApiResponses;

    public function index(RoomTypeFilter $filters)
    {
        $baseQuery = RoomType::query()->with(['hotel', 'facilities', 'images', 'beds.bedType', 'prices']);

        $query = $filters->apply($baseQuery);
        $perPage = request()->get('per_page', 10);
        $perPage = min(max((int) $perPage, 1), 100);

        $roomTypes = $query->paginate($perPage);

        return $this->success($roomTypes, "Room type list success", 200);
    }

    public function indexByHotelId(Request $request, RoomTypeFilter $filters)
    {
        $hotelId = $request->route('hotel_id');
        Hotel::findOrFail($hotelId);

        $checkIn  = $request->get('start_date');
        $checkOut = $request->get('end_date');

        $baseQuery = RoomType::query()
            ->where('hotel_id', $hotelId)
            ->with([
                'hotel',
                'facilities',
                'images',
                'beds.bedType',
                'prices',
            ])
            ->when($checkIn && $checkOut, function ($q) use ($checkIn, $checkOut) {
                $q->withCount([
                    'rooms as available_rooms_count' => function ($rq) use ($checkIn, $checkOut) {
                        $rq->where('is_active', 1)
                            ->where('status', 'available')
                            ->whereDoesntHave('reservations', function ($res) use ($checkIn, $checkOut) {
                                $res->active()
                                    ->where('check_in_date', '<', $checkOut)
                                    ->where('check_out_date', '>', $checkIn);
                            });
                    }
                ]);
            });

        $query = $filters->apply($baseQuery);

        $perPage = min(max((int) $request->get('per_page', 10), 1), 100);

        return $this->success(
            $query->paginate($perPage),
            "Room types for hotel retrieved successfully",
            200
        );
    }


    public function store(Request $request)
    {
        $validate = $request->validate([
            "hotel_id" => "required|exists:hotels,id",
            "name" => "required|string|max:100",
            "description" => "nullable|string",
            "capacity" => "required|integer|min:1",
            "facilities" => "required|array",
            "facilities.*" => "string|max:50",
            "images" => "required|array",
            "images.*" => "image|mimes:jpg,jpeg,png|max:2048",
            "beds" => "required|array",
            "beds.*.bed_type_id" => "required|exists:bed_types,id",
            "beds.*.quantity" => "required|integer|min:1",
            "prices" => "required|array",
            "prices.*.weekday_price" => "required|numeric",
            "prices.*.weekend_price" => "required|numeric",
            "prices.*.currency" => "required|string|max:10",
            "prices.*.start_date" => "required|date",
            "prices.*.end_date" => "required|date|after_or_equal:prices.*.start_date",
        ]);

        $roomTypeData = collect($validate)->except(['facilities', 'images', 'beds', 'prices'])->toArray();
        $roomType = RoomType::create($roomTypeData);

        // Handle facilities
        if ($request->has("facilities")) {
            $facilityIds = [];
            foreach ($request->facilities as $facility) {
                if (is_numeric($facility)) {
                    $exists = RoomTypeFacility::find($facility);
                    if ($exists) {
                        $facilityIds[] = $exists->id;
                    }
                } else {
                    $newFacility = RoomTypeFacility::firstOrCreate(['name' => $facility]);
                    $facilityIds[] = $newFacility->id;
                }
            }
            if (!empty($facilityIds)) {
                $roomType->facilities()->sync($facilityIds);
            }
        }

        // Handle images
        if ($request->hasFile("images")) {
            foreach ($request->file("images") as $image) {
                $fileName = time() . "_" . $image->getClientOriginalName();
                $path = $image->storeAs("room_types", $fileName, "public");
                $roomType->images()->create(["image_url" => $path]);
            }
        }

        // Handle beds
        if ($request->has("beds")) {
            foreach ($request->beds as $bed) {
                $roomType->beds()->create([
                    "bed_type_id" => $bed["bed_type_id"],
                    "quantity" => $bed["quantity"],
                ]);
            }
        }

        // Handle prices
        if ($request->has("prices")) {
            foreach ($request->prices as $price) {
                $roomType->prices()->create($price);
            }
        }

        $roomType->load(["facilities", "images", "beds.bedType", "prices"]);

        return $this->success($roomType, "Room type created successfully", 201);
    }

    public function storeByHotelId(Request $request)
    {
        // Ambil hotel_id dari route parameter
        $hotelId = $request->route('hotel_id');

        // Validasi (HAPUS hotel_id dari validasi karena sudah dari route)
        $validate = $request->validate([
            "name" => "required|string|max:100",
            "description" => "nullable|string",
            "capacity" => "required|integer|min:1",
            "facilities" => "required|array",
            "facilities.*" => "string|max:50",
            "images" => "required|array",
            "images.*" => "image|mimes:jpg,jpeg,png|max:2048",
            "beds" => "required|array",
            "beds.*.bed_type_id" => "required|exists:bed_types,id",
            "beds.*.quantity" => "required|integer|min:1",
            "prices" => "required|array",
            "prices.*.weekday_price" => "required|numeric",
            "prices.*.weekend_price" => "required|numeric",
            "prices.*.currency" => "required|string|max:10",
            "prices.*.start_date" => "required|date",
            "prices.*.end_date" => "required|date|after_or_equal:prices.*.start_date",
        ]);

        // Tambahkan hotel_id ke data yang akan disimpan
        $roomTypeData = collect($validate)
            ->except(['facilities', 'images', 'beds', 'prices'])
            ->toArray();
        $roomTypeData['hotel_id'] = $hotelId; // ← PERBAIKAN DI SINI

        $roomType = RoomType::create($roomTypeData);

        // Handle facilities (PERBAIKAN - HAPUS PENGECEKAN EXISTS)
        if ($request->has("facilities")) {
            $facilityIds = [];
            foreach ($request->facilities as $facility) {
                if (is_numeric($facility)) {
                    // Langsung masukkan ID tanpa cek exists lagi
                    $facilityIds[] = $facility;
                } else {
                    $newFacility = RoomTypeFacility::firstOrCreate(['name' => $facility]);
                    $facilityIds[] = $newFacility->id;
                }
            }
            if (!empty($facilityIds)) {
                $roomType->facilities()->sync($facilityIds);
            }
        }

        // Handle images
        if ($request->hasFile("images")) {
            foreach ($request->file("images") as $image) {
                $fileName = time() . "_" . $image->getClientOriginalName();
                $path = $image->storeAs("room_types", $fileName, "public");
                $roomType->images()->create(["image_url" => $path]);
            }
        }

        // Handle beds
        if ($request->has("beds")) {
            foreach ($request->beds as $bed) {
                $roomType->beds()->create([
                    "bed_type_id" => $bed["bed_type_id"],
                    "quantity" => $bed["quantity"],
                ]);
            }
        }

        // Handle prices
        if ($request->has("prices")) {
            foreach ($request->prices as $price) {
                $roomType->prices()->create($price);
            }
        }

        $roomType->load(["facilities", "images", "beds.bedType", "prices"]);

        return $this->success($roomType, "Room type created successfully", 201);
    }

    public function show($id)
    {
        $roomType = RoomType::with(['hotel', 'facilities', 'images', 'beds.bedType', 'prices'])->find($id);

        if (!$roomType) {
            return $this->error("Room type not found", 404);
        }

        return $this->success($roomType, "Room type found successfully", 200);
    }

    public function showByHotelId(Request $request)
    {
        $hotelId = $request->route('hotel_id');
        $roomTypeId = $request->route('room_type_id');

        // Cari room type yang sesuai hotel_id DAN room_type_id
        $roomType = RoomType::with(['hotel', 'facilities', 'images', 'beds.bedType', 'prices'])
            ->where('hotel_id', $hotelId)
            ->where('id', $roomTypeId)
            ->first();

        if (!$roomType) {
            return $this->error("Room type not found in this hotel", 404);
        }

        return $this->success($roomType, "Room type found successfully", 200);
    }

    public function update(Request $request, $id)
    {
        $roomType = RoomType::find($id);

        if (!$roomType) {
            return $this->error("Room type not found", 404);
        }

        $validate = $request->validate([
            "hotel_id" => "sometimes|exists:hotels,id",
            "name" => "sometimes|string|max:100",
            "description" => "nullable|string",
            "capacity" => "sometimes|integer|min:1",

            "facilities" => "sometimes|array",
            "facilities.*" => "string|max:50",

            "images" => "nullable|array",
            "images.*" => "image|mimes:jpg,jpeg,png|max:2048",
            "remove_images" => "sometimes|array",
            "remove_images.*" => "integer|exists:room_type_images,id",

            "beds" => "sometimes|array",
            "beds.*.bed_type_id" => "sometimes|exists:bed_types,id",
            "beds.*.quantity" => "sometimes|integer|min:1",

            "prices" => "sometimes|array",
            "prices.*.weekday_price" => "sometimes|numeric",
            "prices.*.weekend_price" => "sometimes|numeric",
            "prices.*.currency" => "sometimes|string|max:10",
            "prices.*.start_date" => "sometimes|date",
            "prices.*.end_date" => "sometimes|date|after_or_equal:prices.*.start_date",

        ]);

        $roomTypeData = collect($validate)->except(['facilities', 'images', 'beds', 'prices', 'remove_images'])->toArray();
        $roomType->update($roomTypeData);

        // Remove selected images
        if ($request->filled("remove_images")) {
            $images = $roomType->images()->whereIn("id", $request->remove_images)->get();
            foreach ($images as $image) {
                Storage::disk("public")->delete($image->image_url);
                $image->delete();
            }
        }

        // Add new images
        if ($request->hasFile("images")) {
            foreach ($request->file("images") as $image) {
                $fileName = time() . "_" . $image->getClientOriginalName();
                $path = $image->storeAs("room_types", $fileName, "public");
                $roomType->images()->create(["image_url" => $path]);
            }
        }

        // Update facilities
        if ($request->has("facilities")) {
            $facilityIds = [];
            foreach ($request->facilities as $facility) {
                if (is_numeric($facility)) {
                    $exists = RoomTypeFacility::find($facility);
                    if ($exists) {
                        $facilityIds[] = $exists->id;
                    }
                } else {
                    $newFacility = RoomTypeFacility::firstOrCreate(['name' => $facility]);
                    $facilityIds[] = $newFacility->id;
                }
            }
            $roomType->facilities()->sync($facilityIds);
        }

        // Update beds
        if ($request->has("beds")) {
            $roomType->beds()->delete();
            foreach ($request->beds as $bed) {
                $roomType->beds()->create([
                    "bed_type_id" => $bed["bed_type_id"],
                    "quantity" => $bed["quantity"],
                ]);
            }
        }

        // Update prices
        if ($request->has("prices")) {
            $roomType->prices()->delete();
            foreach ($request->prices as $price) {
                $roomType->prices()->create($price);
            }
        }

        $roomType->load(["facilities", "images", "beds.bedType", "prices"]);

        return $this->success($roomType, "Room type updated successfully", 200);
    }

    public function updateByHotelId(Request $request)
    {
        $hotelId = $request->route('hotel_id');
        $roomTypeId = $request->route('room_type_id');

        // Cari room type yang sesuai hotel_id DAN room_type_id
        $roomType = RoomType::where('hotel_id', $hotelId)
            ->where('id', $roomTypeId)
            ->first();

        if (!$roomType) {
            return $this->error("Room type not found in this hotel", 404);
        }

        $validate = $request->validate([
            "name" => "sometimes|string|max:100",
            "description" => "nullable|string",
            "capacity" => "sometimes|integer|min:1",

            "facilities" => "sometimes|array",
            "facilities.*" => "string|max:50",

            "images" => "nullable|array",
            "images.*" => "image|mimes:jpg,jpeg,png|max:2048",
            "remove_images" => "sometimes|array",
            "remove_images.*" => "integer|exists:room_type_images,id",

            "beds" => "sometimes|array",
            "beds.*.bed_type_id" => "required_with:beds|exists:bed_types,id",
            "beds.*.quantity" => "required_with:beds|integer|min:1",

            "prices" => "sometimes|array",
            "prices.*.weekday_price" => "required_with:prices|numeric|min:0",
            "prices.*.weekend_price" => "required_with:prices|numeric|min:0",
            "prices.*.currency" => "required_with:prices|string|max:10",
            "prices.*.start_date" => "required_with:prices|date",
            "prices.*.end_date" => "required_with:prices|date|after_or_equal:prices.*.start_date",
        ]);

        DB::beginTransaction();

        try {
            // Update basic info (HAPUS hotel_id dari data yang bisa diupdate)
            $roomTypeData = collect($validate)
                ->except(['facilities', 'images', 'beds', 'prices', 'remove_images'])
                ->toArray();
            $roomType->update($roomTypeData);

            // Remove selected images
            if ($request->filled("remove_images")) {
                // Validasi bahwa images yang mau dihapus milik room type ini
                $images = $roomType->images()
                    ->whereIn("id", $request->remove_images)
                    ->get();

                foreach ($images as $image) {
                    Storage::disk("public")->delete($image->image_url);
                    $image->delete();
                }
            }

            // Add new images
            if ($request->hasFile("images")) {
                foreach ($request->file("images") as $image) {
                    $fileName = uniqid() . '_' . time() . '.' . $image->getClientOriginalExtension();
                    $path = $image->storeAs("room_types/{$roomType->id}", $fileName, "public");
                    $roomType->images()->create(["image_url" => $path]);
                }
            }

            // Update facilities
            if ($request->has("facilities")) {
                $facilityIds = [];
                foreach ($request->facilities as $facility) {
                    if (is_numeric($facility)) {
                        $facilityIds[] = $facility;
                    } else {
                        $newFacility = RoomTypeFacility::firstOrCreate(['name' => $facility]);
                        $facilityIds[] = $newFacility->id;
                    }
                }
                $roomType->facilities()->sync($facilityIds);
            }

            // Update beds
            if ($request->has("beds")) {
                $roomType->beds()->delete();
                foreach ($request->beds as $bed) {
                    $roomType->beds()->create([
                        "bed_type_id" => $bed["bed_type_id"],
                        "quantity" => $bed["quantity"],
                    ]);
                }
            }

            // Update prices
            if ($request->has("prices")) {
                $roomType->prices()->delete();
                foreach ($request->prices as $price) {
                    $roomType->prices()->create($price);
                }
            }

            DB::commit();

            $roomType->load(["hotel", "facilities", "images", "beds.bedType", "prices"]);

            return $this->success($roomType, "Room type updated successfully", 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error("Failed to update room type: " . $e->getMessage(), 500);
        }
    }

    public function destroy($id)
    {
        $roomType = RoomType::find($id);

        if (!$roomType) {
            return $this->error("Room type not found", 404);
        }

        $roomType->images()->delete();
        $roomType->facilities()->detach();
        $roomType->beds()->delete();
        $roomType->prices()->delete();
        $roomType->delete();

        return $this->success(null, "Room type deleted successfully", 200);
    }

    public function destroyByHotelId(Request $request)
    {
        $hotelId = $request->route('hotel_id');
        $roomTypeId = $request->route('room_type_id');

        $roomType = RoomType::where('hotel_id', $hotelId)
            ->where('id', $roomTypeId)
            ->first();

        if (!$roomType) {
            return $this->error("Room type not found in this hotel", 404);
        }

        DB::beginTransaction();

        try {
            // Hapus file gambar dari storage
            foreach ($roomType->images as $image) {
                Storage::disk('public')->delete($image->image_url);
            }
            Storage::disk('public')->deleteDirectory("room_types/{$roomType->id}");

            // Hapus relasi dan data
            $roomType->images()->delete();
            $roomType->facilities()->detach();
            $roomType->beds()->delete();
            $roomType->prices()->delete();
            $roomType->delete();

            DB::commit();

            return $this->success(null, "Room type deleted successfully", 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error("Failed to delete room type: " . $e->getMessage(), 500);
        }
    }
}
