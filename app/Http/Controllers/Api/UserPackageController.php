<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserPackageController extends Controller
{
    // GET /api/user/packages
    public function index(Request $request)
    {
        $user = $request->user();
        $now = now();

        // paket berbayar (hasil purchase)
        $paidPackages = DB::table('user_packages')
            ->join('packages', 'packages.id', '=', 'user_packages.package_id')
            ->where('user_packages.user_id', $user->id)
            ->select([
                'packages.id as package_id',
                'packages.name',
                'packages.type',
                'packages.category_id',
                'user_packages.starts_at',
                'user_packages.ends_at',
            ])
            ->orderByDesc('user_packages.id')
            ->get()
            ->map(function ($p) use ($now) {
                $isExpired = $p->ends_at && $now->gt($p->ends_at);

                return [
                    'package_id' => (int) $p->package_id,
                    'name' => $p->name,
                    'type' => $p->type,
                    'category_id' => (int) $p->category_id,
                    'starts_at' => $p->starts_at,
                    'ends_at' => $p->ends_at,
                    'is_free' => false,
                    'status' => $isExpired ? 'expired' : 'active',
                ];
            });

        // paket gratis (always active)
        $freePackages = DB::table('packages')
            ->where('is_active', true)
            ->where('is_free', true)
            ->select([
                'id as package_id',
                'name',
                'type',
                'category_id',
            ])
            ->get()
            ->map(fn ($p) => [
                'package_id' => (int) $p->package_id,
                'name' => $p->name,
                'type' => $p->type,
                'category_id' => (int) $p->category_id,
                'starts_at' => null,
                'ends_at' => null,
                'is_free' => true,
                'status' => 'active',
            ]);

        // merge & unique (kalau user beli paket yang free)
        $items = $paidPackages
            ->merge($freePackages)
            ->unique('package_id')
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'items' => $items,
                'summary' => [
                    'total' => $items->count(),
                    'active' => $items->where('status', 'active')->count(),
                    'expired' => $items->where('status', 'expired')->count(),
                ],
            ],
        ]);
    }
}
