<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Material;
use Illuminate\Http\Request;

class AdminMaterialController extends Controller
{
    public function index(Request $request)
    {
        $q = Material::query()
            ->when($request->type, fn($qq) => $qq->where('type', $request->type))
            ->when($request->search, function ($qq) use ($request) {
                $s = $request->search;
                $qq->where('title', 'like', "%{$s}%");
            })
            ->when(
                !is_null($request->is_active),
                fn($qq) =>
                $qq->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN))
            )
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->paginate(20);

        return response()->json(['success' => true, 'data' => $q]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'type' => ['required', 'in:ebook,video'],
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'cover_url' => ['nullable', 'string', 'max:500'],
            'ebook_url' => ['nullable', 'string', 'max:500'], // wajib kalau ebook (di bawah)
            'sort_order' => ['sometimes', 'integer', 'min:1'],
            'is_active' => ['required', 'boolean'],
            'is_free' => ['sometimes', 'boolean'],
        ]);

        if ($data['type'] === 'ebook' && empty($data['ebook_url'])) {
            return response()->json([
                'success' => false,
                'message' => 'ebook_url wajib untuk type=ebook',
            ], 422);
        }

        if ($data['type'] === 'video') {
            $data['ebook_url'] = null;
        }

        $m = Material::create([
            'type' => $data['type'],
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'cover_url' => $data['cover_url'] ?? null,
            'ebook_url' => $data['ebook_url'] ?? null,
            'sort_order' => $data['sort_order'] ?? 1,
            'is_active' => $data['is_active'],
            'is_free' => array_key_exists('is_free', $data) ? (bool)$data['is_free'] : false,
        ]);

        return response()->json(['success' => true, 'data' => $m], 201);
    }

    public function show(Material $material)
    {
        return response()->json([
            'success' => true,
            'data' => $material->load('parts'),
        ]);
    }

    public function update(Request $request, Material $material)
    {
        $data = $request->validate([
            'type' => ['sometimes', 'in:ebook,video'],
            'title' => ['sometimes', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'cover_url' => ['nullable', 'string', 'max:500'],
            'ebook_url' => ['nullable', 'string', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
            'is_free' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:1'],
        ]);

        $type = $data['type'] ?? $material->type;

        // enforce ebook_url kalau jadi ebook
        if ($type === 'ebook') {
            $ebookUrl = array_key_exists('ebook_url', $data) ? $data['ebook_url'] : $material->ebook_url;
            if (empty($ebookUrl)) {
                return response()->json([
                    'success' => false,
                    'message' => 'ebook_url wajib untuk type=ebook',
                ], 422);
            }
        }

        // kalau jadi video, ebook_url null
        if ($type === 'video') {
            $data['ebook_url'] = null;
        }

        $material->update([
            'type' => $data['type'] ?? $material->type,
            'title' => $data['title'] ?? $material->title,
            'description' => $data['description'] ?? $material->description,
            'cover_url' => $data['cover_url'] ?? $material->cover_url,
            'ebook_url' => $data['ebook_url'] ?? $material->ebook_url,
            'is_active' => array_key_exists('is_active', $data) ? (bool)$data['is_active'] : $material->is_active,
            'is_free' => array_key_exists('is_free', $data) ? (bool)$data['is_free'] : $material->is_free,
            'sort_order' => $data['sort_order'] ?? $material->sort_order,
        ]);

        return response()->json(['success' => true, 'data' => $material->fresh()]);
    }

    public function destroy(Material $material)
    {
        $material->delete();
        return response()->json(['success' => true, 'message' => 'Deleted']);
    }
}
