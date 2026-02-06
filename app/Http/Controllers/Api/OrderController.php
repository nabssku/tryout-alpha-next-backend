<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(private OrderService $orders) {}

    public function store(Request $request)
    {
        $data = $request->validate([
            'product_id' => ['required','integer','exists:products,id'],
            'promo_code' => ['nullable','string','max:50'],
        ]);

        $order = $this->orders->create(
            $request->user()->id,
            (int) $data['product_id'],
            $data['promo_code'] ?? null
        );

        return response()->json(['success' => true, 'data' => $order], 201);
    }

    public function show(Request $request, Order $order)
    {
        abort_unless($order->user_id === $request->user()->id, 403);

        return response()->json([
            'success' => true,
            'data' => $order->load(['product:id,name,type,price', 'items']),
        ]);
    }

    public function index(Request $request)
    {
        $rows = Order::where('user_id', $request->user()->id)
            ->latest('id')
            ->paginate(20);

        return response()->json(['success' => true, 'data' => $rows]);
    }
}
