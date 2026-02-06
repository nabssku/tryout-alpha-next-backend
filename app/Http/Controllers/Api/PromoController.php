<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PromoCode;
use Illuminate\Http\Request;

class PromoController extends Controller
{
    public function validateCode(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50'],
            'amount' => ['required', 'integer', 'min:1'], // harga paket / total order
        ]);

        $code = strtoupper(trim($data['code']));
        $amount = (int) $data['amount'];

        $promo = PromoCode::where('code', $code)->first();

        if (!$promo || !$promo->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Kode promo tidak valid.',
            ], 422);
        }

        $now = now();
        if ($promo->starts_at && $now->lt($promo->starts_at)) {
            return response()->json([
                'success' => false,
                'message' => 'Kode promo belum berlaku.',
            ], 422);
        }
        if ($promo->ends_at && $now->gt($promo->ends_at)) {
            return response()->json([
                'success' => false,
                'message' => 'Kode promo sudah kadaluarsa.',
            ], 422);
        }

        if (!is_null($promo->max_uses) && $promo->used_count >= $promo->max_uses) {
            return response()->json([
                'success' => false,
                'message' => 'Kuota kode promo sudah habis.',
            ], 422);
        }

        if (($promo->min_purchase ?? 0) > 0 && $amount < $promo->min_purchase) {
            return response()->json([
                'success' => false,
                'message' => 'Minimal pembelian untuk promo ini adalah ' . $promo->min_purchase . '.',
            ], 422);
        }


        // hitung diskon
        if ($promo->type === 'percent') {
            $discount = (int) floor($amount * ($promo->value / 100));
        } else { // fixed
            $discount = (int) $promo->value;
        }

        // diskon tidak boleh lebih besar dari amount
        $discount = min($discount, $amount);
        $finalAmount = max(0, $amount - $discount);

        return response()->json([
            'success' => true,
            'data' => [
                'code' => $promo->code,
                'type' => $promo->type,
                'value' => $promo->value,
                'min_purchase' => (int) ($promo->min_purchase ?? 0),
                'amount' => $amount,
                'discount' => $discount,
                'final_amount' => $finalAmount,
            ],
        ]);
    }
}
