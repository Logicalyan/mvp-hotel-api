<?php

namespace App\Http\Controllers\Data\Hotel;

use App\ApiResponses;
use App\Filters\HotelFilter;
use App\Http\Controllers\Controller;
use App\Models\Facility;
use App\Models\Hotel;
use Illuminate\Http\Request;

class HotelController extends Controller
{
    use ApiResponses;

    public function store(Request $request)
    {
        $validate = $request->validate([
            "name" => "required|string|max:50",
            "description" => "required|string|max:255",
            "address" => "required|string|max:255",
            "sub_district_id" => "required|exists:sub_districts,id",
            "district_id" => "required|exists:districts,id",
            "city_id" => "required|exists:cities,id",
            "province_id" => "required|exists:provinces,id",
            "phone_number" => "required|numeric|digits_between:10,13",
            "email" => "required|string|max:255",
            "images" => "required|array",
            "images.*" => "image|mimes:jpg,jpeg,png|max:5120",
            "facilities" => "required|array",
            "facilities.*" => "string|max:20"
        ]);

        $hotel = Hotel::create($validate);

        if ($request->has("images")) {
            foreach ($request->file("images") as $image) {
                $originName = $image->getClientOriginalName();
                $fileName = time() . "_" . $originName;

                $path = $image->storeAs("hotels", $fileName, "public");

                $hotel->images()->create(["image_url" => $path]);
            }
        }

        if ($request->has("facilities")) {
            $facilityIds = [];

            foreach ($request->facilities as $facility) {
                if (is_numeric($facility)) {
                    $facilityIds[] = $facility;
                } else {
                    $newFacility = Facility::firstOrCreate(['name' => $facility]);
                    $facilityIds[] = $newFacility->id;
                }
            }

            $hotel->facilities()->sync($facilityIds);
        }

        $hotel->load(["images", "facilities"]);

        return $this->success($hotel, "Hotel created successfully", 201);
    }

    public function index(HotelFilter $filters)
    {
        $query = $filters->apply(Hotel::query()->with(['images', 'facilities', 'province', 'city', 'district', 'subDistrict']));

        $hotels = $query->paginate(10);

        return $this->success($hotels, "Hotel list success", 200);
    }

    public function show(HotelFilter $filters, $id)
    {
        $query = $filters->apply(Hotel::where("id", $id)->with(['images', 'facilities']));
        $hotel = $query->first();

        if (!$hotel) {
            return $this->error("Hotel not found", 404);
        }

        return $this->success($hotel, "Hotel found successfully", 200);
    }

    public function update(Request $request, $id)
    {
        $hotel = Hotel::find($id);

        if (!$hotel) {
            return $this->error("Hotel not found", 404);
        }

        $validate = $request->validate([
            "name" => "sometimes|string|max:50",
            "description" => "sometimes|string|max:255",
            "address" => "sometimes|string|max:255",
            "sub_district_id" => "sometimes|exists:sub_districts,id",
            "district_id" => "sometimes|exists:districts,id",
            "city_id" => "sometimes|exists:cities,id",
            "province_id" => "sometimes|exists:provinces,id",
            "phone_number" => "sometimes|numeric|digits_between:10,13",
            "email" => "sometimes|string|max:255",
            "images" => "sometimes|array",
            "images.*" => "image|mimes:jpg,jpeg,png|max:5120",
            "facilities" => "sometimes|array",
            "facilities.*" => "string|max:20"
        ]);

        $hotel->update($validate);

        if ($request->hasFile("images")) {
            $hotel->images()->delete();

            foreach ($request->file("images") as $image) {
                $fileName = time() . "_" . $image->getClientOriginalName();
                $path = $image->storeAs("hotels", $fileName, "public");
                $hotel->images()->create(["image_url" => $path]);
            }
        }

        if ($request->has("facilities")) {
            $hotel->facilities()->delete();

            foreach ($request->facilities as $facility) {
                $hotel->facilities()->create(["name" => $facility]);
            }
        }

        $hotel->load(["images", "facilities"]);

        return $this->success($hotel, "Hotel updated successfully", 200);
    }

    public function destroy($id)
    {
        $hotel = Hotel::find($id);

        if (!$hotel) {
            return $this->error("Hotel not found", 404);
        }

        $hotel->images()->delete();
        $hotel->facilities()->delete();
        $hotel->delete();

        return $this->success(null, "Hotel deleted successfully", 200);
    }
}
