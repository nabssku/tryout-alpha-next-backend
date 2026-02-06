<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\PromoCode;
use App\Services\DuitkuService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    public function __construct(private DuitkuService $duitku) {}

    // POST /api/products/{product}/payment-methods
    public function paymentMethods(Request $request, Product $product)
    {
        $data = $request->validate([
            'promo_code' => ['nullable','string','max:50'],
        ]);

        [$finalAmount] = $this->applyPromoIfAny($data['promo_code'] ?? null, (int)$product->price);

        $duitku = $this->duitku->getPaymentMethods($finalAmount);

        $methods = collect($duitku['paymentFee'] ?? [])->map(fn ($m) => [
            'payment_method' => $m['paymentMethod'] ?? null,
            'payment_name'   => $m['paymentName'] ?? null,
            'payment_image'  => $m['paymentImage'] ?? null,
            'total_fee'      => (int)($m['totalFee'] ?? 0),
        ])->values();

        return response()->json([
            'success' => true,
            'data' => [
                'amount' => $finalAmount,
                'methods' => $methods,
            ],
        ]);
    }

    // POST /api/products/{product}/pay
    public function payProduct(Request $request, Product $product)
    {
        $data = $request->validate([
            'payment_method' => ['required','string','max:30'],
            'promo_code'     => ['nullable','string','max:50'],
        ]);

        $user = $request->user();

        [$finalAmount, $discount, $promoCodeStr] = $this->applyPromoIfAny($data['promo_code'] ?? null, (int)$product->price);

        // buat order + items
        $order = DB::transaction(function () use ($user, $product, $finalAmount, $discount, $promoCodeStr, $data) {
            $merchantOrderId = 'ORD-' . now()->format('YmdHis') . '-' . str_pad((string)random_int(1, 999999), 6, '0', STR_PAD_LEFT);

            $orderId = DB::table('orders')->insertGetId([
                'user_id' => $user->id,
                'product_id' => $product->id,
                'merchant_order_id' => $merchantOrderId,
                'amount' => $finalAmount,
                'status' => 'pending',
                'payment_method' => $data['payment_method'],
                'promo_code' => $promoCodeStr,
                'discount' => $discount,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // order_items: single vs bundle
            if ($product->type === 'single') {
                DB::table('order_items')->insert([
                    'order_id' => $orderId,
                    'package_id' => $product->package_id,
                    'qty' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $rows = DB::table('product_packages')
                    ->where('product_id', $product->id)
                    ->get(['package_id','qty']);

                foreach ($rows as $r) {
                    DB::table('order_items')->insert([
                        'order_id' => $orderId,
                        'package_id' => $r->package_id,
                        'qty' => (int)$r->qty,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            return (object)[
                'id' => $orderId,
                'merchant_order_id' => $merchantOrderId,
            ];
        });

        // Call Duitku inquiry v2 (paymentMethod dipilih user)
        $merchantCode = config('services.duitku.merchant_code');
        $apiKey       = config('services.duitku.api_key');

        // signature MD5(merchantCode + merchantOrderId + paymentAmount + apiKey) :contentReference[oaicite:3]{index=3}
        $signature = md5($merchantCode . $order->merchant_order_id . $finalAmount . $apiKey);

        $payload = [
            'merchantCode'    => $merchantCode,
            'paymentAmount'   => $finalAmount,
            'merchantOrderId' => $order->merchant_order_id,
            'productDetails'  => $product->name,
            'email'           => $user->email,
            'callbackUrl'     => config('services.duitku.callback_url'),
            'returnUrl'       => config('services.duitku.return_url'),
            'signature'       => $signature,
            'paymentMethod'   => $data['payment_method'],
            'expiryPeriod'    => 60, // menit (opsional)
        ];

        $res = $this->duitku->createInvoice($payload);

        // res biasanya punya reference + paymentUrl :contentReference[oaicite:4]{index=4}
        if (($res['statusCode'] ?? null) !== '00') {
            // gagal buat invoice
            DB::table('orders')->where('id', $order->id)->update([
                'status' => 'failed',
                'updated_at' => now(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $res['statusMessage'] ?? 'Gagal membuat pembayaran.',
                'data' => $res,
            ], 422);
        }

        DB::table('orders')->where('id', $order->id)->update([
            'duitku_reference' => $res['reference'] ?? null,
            'payment_url'      => $res['paymentUrl'] ?? null,
            'updated_at'       => now(),
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'order_id' => $order->id,
                'merchant_order_id' => $order->merchant_order_id,
                'amount' => $finalAmount,
                'payment_method' => $data['payment_method'],
                'payment_url' => $res['paymentUrl'] ?? null,
                'reference' => $res['reference'] ?? null,
            ],
        ], 201);
    }

    private function applyPromoIfAny(?string $promoCode, int $amount): array
    {
        if (!$promoCode) return [$amount, 0, null];

        $code = strtoupper(trim($promoCode));
        $promo = PromoCode::where('code', $code)->first();

        if (!$promo || !$promo->is_active) return [$amount, 0, null];

        $now = now();
        if ($promo->starts_at && $now->lt($promo->starts_at)) return [$amount, 0, null];
        if ($promo->ends_at && $now->gt($promo->ends_at)) return [$amount, 0, null];
        if (!is_null($promo->max_uses) && $promo->used_count >= $promo->max_uses) return [$amount, 0, null];
        if (($promo->min_purchase ?? 0) > 0 && $amount < $promo->min_purchase) return [$amount, 0, null];

        $discount = $promo->type === 'percent'
            ? (int) floor($amount * ($promo->value / 100))
            : (int) $promo->value;

        $discount = min($discount, $amount);
        $final = max(0, $amount - $discount);

        return [$final, $discount, $promo->code];
    }
}
