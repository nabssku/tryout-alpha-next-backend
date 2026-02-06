<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\Request;

class AdminOrderController extends Controller
{
    public function __construct(private OrderService $orders) {}

    public function index(Request $request)
    {
        $q = Order::query()
            ->with(['product:id,name,type,price', 'items'])
            ->when($request->status, fn($qq) => $qq->where('status', $request->status))
            ->orderByDesc('id')
            ->paginate(20);

        return response()->json(['success' => true, 'data' => $q]);
    }

    public function markPaid(Order $order)
    {
        $order = $this->orders->markPaid($order);

        return response()->json(['success' => true, 'data' => $order]);
    }
}
