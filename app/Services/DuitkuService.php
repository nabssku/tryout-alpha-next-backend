<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class DuitkuService
{
    public function baseUrl(): string
    {
        return config('services.duitku.mode') === 'production'
            ? 'https://passport.duitku.com/webapi/api'
            : 'https://sandbox.duitku.com/webapi/api';
    }

    public function getPaymentMethods(int $amount): array
    {
        $merchantCode = config('services.duitku.merchant_code');
        $apiKey       = config('services.duitku.api_key');
        $datetime     = now()->format('Y-m-d H:i:s');

        // Sha256(merchantcode + paymentAmount + datetime + apiKey)
        $signature = hash('sha256', $merchantCode . $amount . $datetime . $apiKey);

        $payload = [
            'merchantcode' => $merchantCode,
            'amount'       => $amount,
            'datetime'     => $datetime,
            'signature'    => $signature,
        ];

        $url = $this->baseUrl() . '/merchant/paymentmethod/getpaymentmethod';

        $res = Http::acceptJson()->asJson()->post($url, $payload);

        if (!$res->ok()) {
            throw new \RuntimeException("Duitku getPaymentMethod failed: {$res->status()} {$res->body()}");
        }

        return $res->json();
    }

    public function createInvoice(array $payload): array
    {
        // Inquiry v2 (merchant/v2/inquiry) biasanya pakai signature MD5.
        // Contoh response ada reference & paymentUrl. :contentReference[oaicite:2]{index=2}
        $url = $this->baseUrl() . '/merchant/v2/inquiry';

        $res = Http::acceptJson()->asJson()->post($url, $payload);

        if (!$res->ok()) {
            throw new \RuntimeException("Duitku inquiry failed: {$res->status()} {$res->body()}");
        }

        return $res->json();
    }
}
