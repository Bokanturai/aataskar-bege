<?php

namespace App\Services;

use App\Http\Helpers\noncestrHelper;
use App\Http\Helpers\signatureHelper;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PalmPayService
{
    protected $baseUrl;
    protected $bearerToken;
    protected $merchantId;
    protected $version;
    protected $privateKey;

    public function __construct()
    {
        $this->baseUrl = env('BASE_URL_PALMPAY');
        $this->bearerToken = env('BEARER_TOKEN');
        $this->merchantId = env('MERCHANTID');
        $this->version = env('VERSION', 'V2.0');
        $this->privateKey = config('keys.private');
    }

    public function getMerchantBalance()
    {
        try {
            $requestTime = (int) (microtime(true) * 1000);
            $nonceStr = noncestrHelper::generateNonceStr();

            $data = [
                'requestTime' => $requestTime,
                'nonceStr' => $nonceStr,
                'merchantId' => $this->merchantId,
                'version' => $this->version,
            ];

            $signature = signatureHelper::generate_signature($data, $this->privateKey);

            $response = Http::withoutVerifying()->withHeaders([
                'Accept' => 'application/json',
                'CountryCode' => 'NG',
                'Authorization' => "Bearer {$this->bearerToken}",
                'Signature' => $signature,
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . 'api/v2/merchant/manage/account/queryBalance', $data);

            if ($response->successful()) {
                $result = $response->json();
                if (isset($result['respCode']) && $result['respCode'] === '00000000') {
                    $data = $result['data'] ?? [];
                    $balance = $data['availableBalance'] ?? ($data['currentBalance'] ?? ($data['balance'] ?? 0));
                    
                    // Convert from minor unit (kobo) to major unit (Naira)
                    $balance = $balance / 100;

                    return [
                        'success' => true,
                        'balance' => $balance,
                        'currency' => $result['data']['currency'] ?? 'NGN'
                    ];
                }
                
                Log::error('PalmPay Balance Inquiry API Error', ['response' => $result]);
                return ['success' => false, 'message' => $result['respMsg'] ?? 'Unknown API error'];
            }

            Log::error('PalmPay Balance Inquiry HTTP Error', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            return ['success' => false, 'message' => 'HTTP error: ' . $response->status()];

        } catch (\Exception $e) {
            Log::error('PalmPay Balance Inquiry Exception', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => 'Exception: ' . $e->getMessage()];
        }
    }
}
