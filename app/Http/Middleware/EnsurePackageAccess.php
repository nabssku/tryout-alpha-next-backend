<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePackageAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle($request, \Closure $next)
    {
        $user = $request->user();
        $package = $request->route('package'); // route model binding

        // 1) package aktif
        if (!$package || !$package->is_active) {
            return response()->json(['message' => 'Package not available'], 404);
        }

        // 2) free package -> boleh
        if ((int) $package->price === 0) {
            return $next($request);
        }

        // 3) cek paid order
        $hasAccess = \App\Models\Order::query()
            ->where('user_id', $user->id)
            ->where('package_id', $package->id)
            ->where('status', 'paid')
            ->exists();

        if (!$hasAccess) {
            return response()->json(['message' => 'Package not purchased'], 403);
        }

        return $next($request);
    }
}
