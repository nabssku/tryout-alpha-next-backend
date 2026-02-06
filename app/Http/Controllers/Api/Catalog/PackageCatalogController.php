<?php

namespace App\Http\Controllers\Api\Catalog;

use App\Http\Controllers\Controller;
use App\Models\Package;
use Illuminate\Http\Request;

class PackageCatalogController extends Controller
{
    public function index(Request $request)
    {
        $rows = Package::query()
            ->where('is_active', true)
            ->with('category:id,name')
            ->withCount('questions')
            ->when(
                $request->category_id,
                fn($q) =>
                $q->where('category_id', $request->category_id)
            )
            ->when(
                $request->type,
                fn($q) =>
                $q->where('type', $request->type)
            )
            ->orderBy('id', 'desc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $rows,
        ]);
    }

    public function show(Package $package)
    {
        abort_unless($package->is_active, 404);

        $package->load('category:id,name');
        $package->loadCount('questions');

        return response()->json([
            'success' => true,
            'data' => $package,
        ]);
    }
}
