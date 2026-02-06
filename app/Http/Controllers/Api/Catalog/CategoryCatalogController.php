<?php

namespace App\Http\Controllers\Api\Catalog;

use App\Http\Controllers\Controller;
use App\Models\Category;

class CategoryCatalogController extends Controller
{
    public function index()
    {
        $rows = Category::query()
            ->withCount(['packages' => fn($q) => $q->where('is_active', true)])
            ->orderBy('name')
            ->get(['id','name']);

        return response()->json([
            'success' => true,
            'data' => $rows,
        ]);
    }
}
