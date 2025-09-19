<?php

namespace App\Http\Controllers\Data;

use App\Http\Controllers\Controller;
use App\Models\BedType;
use App\Filters\BedTypeFilter;
use Illuminate\Http\Request;

class BedTypeController extends Controller
{
    // GET /api/bed-types
    public function index(Request $request)
    {
        $filter = new BedTypeFilter($request);
        $bedTypes = $filter->apply(BedType::query())->paginate(10);

        return response()->json($bedTypes);
    }

    // POST /api/bed-types
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $bedType = BedType::create($request->only(['name', 'description']));

        return response()->json($bedType, 201);
    }

    // GET /api/bed-types/{id}
    public function show(BedType $bedType)
    {
        return response()->json($bedType);
    }

    // PUT /api/bed-types/{id}
    public function update(Request $request, BedType $bedType)
    {
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
        ]);

        $bedType->update($request->only(['name', 'description']));

        return response()->json($bedType);
    }

    // DELETE /api/bed-types/{id}
    public function destroy(BedType $bedType)
    {
        $bedType->delete();

        return response()->json(null, 204);
    }
}
