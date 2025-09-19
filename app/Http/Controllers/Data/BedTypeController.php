<?php

namespace App\Http\Controllers\Data;

use App\ApiResponses;
use App\Http\Controllers\Controller;
use App\Models\BedType;
use App\Filters\BedTypeFilter;
use Illuminate\Http\Request;

class BedTypeController extends Controller
{
    use ApiResponses;
    // GET /api/bed-types
    public function index()
    {
        $bedTypes = BedType::select('id', 'name');

        return $this->success($bedTypes, "Bed Type list Success", 200);
    }

    // POST /api/bed-types
    public function store(Request $request)
    {
        $validate = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $bedType = BedType::create($validate);

        return $this->success($bedType, "Bed type created successfully");
    }

    // GET /api/bed-types/{id}
    public function show(BedType $bedType)
    {
        if (!$bedType) {
            return $this->error("bedType not found", 404);
        }

        return $this->success($bedType, "Hotel found successfully", 200);
    }

    // PUT /api/bed-types/{id}
    public function update(Request $request, BedType $bedType)
    {
        $validate = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
        ]);

        $bedType->update($validate);

        return $this->success($bedType, "Updated BedType Successfully", 200);
    }

    // DELETE /api/bed-types/{id}
    public function destroy(BedType $bedType)
    {
        $bedType->delete();

        return $this->success($bedType, "Deleted BedType Successfully", 200);
    }
}
