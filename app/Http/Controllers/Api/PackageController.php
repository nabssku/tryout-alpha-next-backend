<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Package;

class PackageController extends Controller
{
    public function index()
    {
        return Package::where('is_active', true)->get();
    }

    public function show(Package $package)
    {
        abort_unless($package->is_active, 404);
        return $package;

    }

    public function pay(Request $request, Package $package)
    {
        // user login di sini
        $user = $request->user();

        // create invoice / order / payment intent
        // ...

        return response()->json([
            'message' => 'Payment initiated',
        ]);
    }
}
