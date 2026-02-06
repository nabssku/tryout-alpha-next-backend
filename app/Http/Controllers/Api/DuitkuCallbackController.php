<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DuitkuCallbackController extends Controller
{
    public function handle(Request $request)
    {
        $apiKey = config('services.duitku.api_key');

        $merchantCode    = $request->input('merchantCode');
        $amount          = $request->input('amount');
        $merchantOrderId = $request->input('merchantOrderId');
        $resultCode      = $request->input('resultCode'); // '00' success
        $reference       = $request->input('reference');
        $signature       = $request->input('signature');

        if (!$merchantCode || !$amount || !$merchantOrderId || !$signature) {
            return response('Bad Request', 400);
        }

        $expected = md5($merchantCode . $amount . $merchantOrderId . $apiKey);
        if (!hash_equals($expected, $signature)) {
            return response('Invalid Signature', 401);
        }

        $order = DB::table('orders')->where('merchant_order_id', $merchantOrderId)->first();
        if (!$order) return response('Order Not Found', 404);

        // idempotent
        if ($order->status === 'paid') {
            return response('OK', 200);
        }

        return DB::transaction(function () use ($order, $resultCode, $reference, $request) {

            DB::table('orders')->where('id', $order->id)->update([
                'duitku_reference' => $reference ?? $order->duitku_reference,
                'raw_callback' => json_encode($request->all()),
                'updated_at' => now(),
            ]);

            if ($resultCode === '00') {
                DB::table('orders')->where('id', $order->id)->update([
                    'status' => 'paid',
                    'paid_at' => now(),
                    'updated_at' => now(),
                ]);

                // grant entitlement
                $items = DB::table('order_items')->where('order_id', $order->id)->get(['package_id','qty']);
                foreach ($items as $it) {
                    DB::table('user_packages')->insert([
                        'user_id' => $order->user_id,
                        'package_id' => $it->package_id,
                        'order_id' => $order->id,
                        'starts_at' => now(),
                        'ends_at' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            } else {
                DB::table('orders')->where('id', $order->id)->update([
                    'status' => 'failed',
                    'updated_at' => now(),
                ]);
            }

            return response('OK', 200);
        });
    }
}
