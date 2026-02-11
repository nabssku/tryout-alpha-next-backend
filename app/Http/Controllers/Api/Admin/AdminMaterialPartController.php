<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Material;
use App\Models\MaterialPart;
use Illuminate\Http\Request;

class AdminMaterialPartController extends Controller
{
    // GET /api/admin/materials/{material}/parts
    public function index(Material $material)
    {
        return response()->json([
            'success' => true,
            'data' => $material->parts()->orderBy('sort_order')->paginate(50),
        ]);
    }

    // POST /api/admin/materials/{material}/parts
    public function store(Request $request, Material $material)
    {
        if ($material->type !== 'video') {
            return response()->json([
                'success' => false,
                'message' => 'Parts hanya untuk material type=video',
            ], 422);
        }

        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'video_url' => ['required', 'string', 'max:500'],
            'duration_seconds' => ['nullable', 'integer', 'min:1'],
            'sort_order' => ['sometimes', 'integer', 'min:1'],
            'is_active' => ['required', 'boolean'],
        ]);

        $part = MaterialPart::create([
            'material_id' => $material->id,
            'title' => $data['title'],
            'video_url' => $data['video_url'],
            'duration_seconds' => $data['duration_seconds'] ?? null,
            'sort_order' => $data['sort_order'] ?? 1,
            'is_active' => $data['is_active'],
        ]);

        return response()->json(['success' => true, 'data' => $part], 201);
    }

    // PATCH /api/admin/materials/{material}/parts/{part}
    public function update(Request $request, Material $material, MaterialPart $part)
    {
        abort_unless($part->material_id === $material->id, 404);

        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:200'],
            'video_url' => ['sometimes', 'required', 'string', 'max:500'],
            'duration_seconds' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'sort_order' => ['sometimes', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'required', 'boolean'],
        ]);

        $part->update($data);

        return response()->json(['success' => true, 'data' => $part->fresh()]);
    }

    // DELETE /api/admin/materials/{material}/parts/{part}
    public function destroy(Material $material, MaterialPart $part)
    {
        abort_unless($part->material_id === $material->id, 404);

        $part->delete();
        return response()->json(['success' => true, 'message' => 'Deleted']);
    }
}
