<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Package;

class AdminPackageController extends Controller
{
    public function index(Request $request)
    {
        $q = Package::query()
            ->with('category:id,name')
            ->withCount('questions')
            ->when($request->type, fn($qq) => $qq->where('type', $request->type))
            ->when($request->category_id, fn($qq) => $qq->where('category_id', $request->category_id))
            ->orderBy('id','desc')
            ->paginate(20);

        return response()->json(['success' => true, 'data' => $q]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required','string','max:200'],
            'type' => ['required','in:latihan,tryout,akbar'],
            'category_id' => ['required','integer','exists:categories,id'],
            'duration_seconds' => ['required','integer','min:60'],
            'is_active' => ['required','boolean'],
            'is_free' => ['required','boolean'],
        ]);

        $pkg = Package::create($data);

        return response()->json(['success' => true, 'data' => $pkg], 201);
    }

    public function show(Package $package)
    {
        $package->load('category:id,name');
        $package->loadCount('questions');

        return response()->json(['success' => true, 'data' => $package]);
    }

    public function update(Request $request, Package $package)
    {
        $data = $request->validate([
            'name' => ['sometimes','required','string','max:200'],
            'type' => ['sometimes','required','in:latihan,tryout,akbar'],
            'category_id' => ['sometimes','required','integer','exists:categories,id'],
            'duration_seconds' => ['sometimes','required','integer','min:60'],
            'is_active' => ['sometimes','required','boolean'],
            'is_free' => ['sometimes','required','boolean'],
        ]);

        $package->update($data);

        return response()->json(['success' => true, 'data' => $package->fresh()]);
    }

    public function destroy(Package $package)
    {
        $package->delete();

        return response()->json(['success' => true, 'message' => 'Deleted']);
    }
}
