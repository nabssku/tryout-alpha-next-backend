<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\PromoCode;
use App\Models\UserPackage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderService
{
    public function create(int $userId, int $productId, ?string $promoCode): Order
    {
        return DB::transaction(function () use ($userId, $productId, $promoCode) {

            $product = Product::where('is_active', true)
                ->with(['package', 'packages'])
                ->findOrFail($productId);

            $gross = (int) $product->price; // sebelum promo
            [$final, $discount] = $this->applyPromoIfAny($promoCode, $gross);

            $order = Order::create([
                'user_id' => $userId,
                'product_id' => $product->id,
                'merchant_order_id' => $this->genMerchantOrderId(),
                'amount' => $final,          // sesuai migration kamu: final amount
                'discount' => $discount,
                'promo_code' => $promoCode ? strtoupper(trim($promoCode)) : null,
                'status' => $final === 0 ? 'paid' : 'pending',
                'paid_at' => $final === 0 ? now() : null,
            ]);

            // isi order_items (berisi package yang didapat)
            if ($product->type === 'single') {
                abort_if(!$product->package_id, 422, 'Product single belum punya package_id.');
                OrderItem::create([
                    'order_id' => $order->id,
                    'package_id' => (int) $product->package_id,
                    'qty' => 1,
                ]);
            } else {
                $pkgs = $product->packages;
                abort_if($pkgs->count() === 0, 422, 'Product bundle belum punya paket.');
                foreach ($pkgs as $p) {
                    OrderItem::create([
                        'order_id' => $order->id,
                        'package_id' => (int) $p->id,
                        'qty' => (int) ($p->pivot->qty ?? 1),
                    ]);
                }
            }

            // kalau free, langsung grant entitlement
            if ($order->status === 'paid') {
                $this->grantUserPackages($order);
                $this->consumePromoIfAny($promoCode);
            }

            return $order->load(['product:id,name,type,price', 'items']);
        });
    }

    public function markPaid(Order $order): Order
    {
        return DB::transaction(function () use ($order) {
            $order = Order::lockForUpdate()->findOrFail($order->id);
            if ($order->status === 'paid') return $order->load(['product', 'items']);

            $order->update([
                'status' => 'paid',
                'paid_at' => now(),
            ]);

            $this->grantUserPackages($order);

            if ($order->promo_code) {
                $this->consumePromoIfAny($order->promo_code);
            }

            return $order->fresh()->load(['product:id,name,type,price', 'items']);
        });
    }

    private function grantUserPackages(Order $order): void
    {
        $now = now();

        // pastikan product kebaca (biar akses_days ada)
        $order->loadMissing(['product', 'items']);

        $accessDays = (int) ($order->product?->access_days ?? 0);

        $endsAt = $accessDays > 0
            ? $now->copy()->addDays($accessDays)
            : null;

        foreach ($order->items as $it) {
            $packageId = (int) $it->package_id;

            $endsAt = $accessDays
                ? $now->copy()->addDays((int) $accessDays)
                : null;

            // idempotent (anti dobel)
            UserPackage::updateOrCreate(
                [
                    'user_id' => $order->user_id,
                    'package_id' => $packageId,
                    'order_id' => $order->id,
                ],
                [
                    'starts_at' => $now,
                    'ends_at' => $endsAt,
                ]
            );
        }
    }

    private function applyPromoIfAny(?string $code, int $gross): array
    {
        if (!$code) return [$gross, 0];

        $code = strtoupper(trim($code));
        $promo = PromoCode::where('code', $code)->first();
        if (!$promo || !$promo->is_active) return [$gross, 0];

        $now = now();
        if ($promo->starts_at && $now->lt($promo->starts_at)) return [$gross, 0];
        if ($promo->ends_at && $now->gt($promo->ends_at)) return [$gross, 0];
        if (!is_null($promo->max_uses) && $promo->used_count >= $promo->max_uses) return [$gross, 0];
        if (($promo->min_purchase ?? 0) > 0 && $gross < $promo->min_purchase) return [$gross, 0];

        $discount = $promo->type === 'percent'
            ? (int) floor($gross * ($promo->value / 100))
            : (int) $promo->value;

        $discount = min($discount, $gross);
        return [max(0, $gross - $discount), $discount];
    }

    private function consumePromoIfAny(?string $code): void
    {
        if (!$code) return;
        PromoCode::where('code', strtoupper(trim($code)))->increment('used_count');
    }

    private function genMerchantOrderId(): string
    {
        return 'ORD-' . now()->format('YmdHis') . '-' . strtoupper(Str::random(6));
    }
}
