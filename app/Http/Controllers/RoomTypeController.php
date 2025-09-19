<?php

namespace App\Http\Controllers;

use App\ApiResponses;
use App\Filters\RoomTypeFilter;
use App\Models\RoomType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\RoomTypeFacility;

class RoomTypeController extends Controller
{
    use ApiResponses;

    public function index(RoomTypeFilter $filters)
    {
        $baseQuery = RoomType::query()->with(['facilities', 'images', 'beds.bedType', 'prices']);

        $query = $filters->apply($baseQuery);
        $perPage = request()->get('per_page', 10);
        $perPage = min(max((int) $perPage, 1), 100);

        $roomTypes = $query->paginate($perPage);

        return $this->success($roomTypes, "Room type list success", 200);
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

    public function show($id)
    {
        $roomType = RoomType::with(['facilities', 'images', 'beds.bedType', 'prices'])->find($id);

        if (!$roomType) {
            return $this->error("Room type not found", 404);
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
}
