<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Http;
use Exception;

class TorobPay
{
    protected string $baseUrl;
    protected string $clientId;
    protected string $clientSecret;
    protected string $username;
    protected string $password;
    protected ?string $accessToken = null;

    public function __construct()
    {
        $this->baseUrl = 'https://cpg.torobpay.com';
        $this->clientId = 'torobpay.client_id';
        $this->clientSecret = 'torobpay.client_secret';
        $this->username = 'torobpay.username';
        $this->password = 'torobpay.password';

        if (empty($this->clientId) || empty($this->clientSecret) || empty($this->username) || empty($this->password)) {
            throw new Exception('TorobPay credentials are not configured properly in your .env file.');
        }
    }

    public function obtainAccessToken(): string
    {
        $credentials = base64_encode($this->clientId . ':' . $this->clientSecret);

        $response = Http::withHeaders([
            'Authorization' => 'Basic ' . $credentials,
            'Content-Type' => 'application/json'
        ])->post($this->baseUrl . '/api/online/v1/oauth/token', [
            'username' => $this->username,
            'password' => $this->password,
        ]);

        if ($response->failed()) {
            throw new Exception("TorobPay Authentication Failed: " . $response->body());
        }

        $this->accessToken = $response->json('access_token');
        return $this->accessToken;
    }

    protected function ensureAccessToken(): void
    {
        if (is_null($this->accessToken)) {
            $this->obtainAccessToken();
        }
    }

    public function checkEligibility(int $amount): array
    {
        $this->ensureAccessToken();

        $response = Http::withToken($this->accessToken)
            ->get($this->baseUrl . '/api/online/offer/v1/eligible', [
                'amount' => $amount,
            ]);

        if ($response->failed()) {
            throw new Exception("TorobPay Eligibility Check Failed: " . $response->body());
        }

        return $response->json();
    }

    public function requestPaymentToken(array $paymentData): array
    {
        $this->ensureAccessToken();

        $response = Http::withToken($this->accessToken)
            ->post($this->baseUrl . '/api/online/payment/v1/token', $paymentData);

        if ($response->failed()) {
            throw new Exception("TorobPay Request Payment Token Failed: " . $response->body());
        }

        return $response->json();
    }

    public function verifyPayment(string $paymentToken): array
    {
        $this->ensureAccessToken();

        $response = Http::withToken($this->accessToken)
            ->post($this->baseUrl . '/api/online/payment/v1/verify', [
                'paymentToken' => $paymentToken,
            ]);

        if ($response->failed()) {
            throw new Exception("TorobPay Verification Failed: " . $response->body());
        }

        return $response->json();
    }

    public function settlePayment(string $paymentToken): array
    {
        $this->ensureAccessToken();

        $response = Http::withToken($this->accessToken)
            ->post($this->baseUrl . '/api/online/payment/v1/settle', [
                'paymentToken' => $paymentToken,
            ]);

        if ($response->failed()) {
            throw new Exception("TorobPay Settle Failed: " . $response->body());
        }

        return $response->json();
    }

    public function revertPayment(string $paymentToken): array
    {
        $this->ensureAccessToken();

        $response = Http::withToken($this->accessToken)
            ->post($this->baseUrl . '/api/online/payment/v1/revert', [
                'paymentToken' => $paymentToken,
            ]);
            
        if ($response->failed()) {
            throw new Exception("TorobPay Revert Failed: " . $response->body());
        }

        return $response->json();
    }

    public function getPaymentStatus(string $paymentToken): array
    {
        $this->ensureAccessToken();

        $response = Http::withToken($this->accessToken)
            ->get($this->baseUrl . '/api/online/payment/v1/status', [
                'paymentToken' => $paymentToken,
            ]);
            
        if ($response->failed()) {
            throw new Exception("TorobPay Get Status Failed: " . $response->body());
        }

        return $response->json();
    }

    public function cancelPayment(string $paymentToken): array
    {
        $this->ensureAccessToken();
        
        $response = Http::withToken($this->accessToken)
            ->post($this->baseUrl . '/api/online/payment/v1/cancel', [
                'paymentToken' => $paymentToken
            ]);

        if ($response->failed()) {
            throw new Exception("TorobPay Cancel Payment Failed: " . $response->body());
        }
        
        return $response->json();
    }

    public function updatePayment(array $updateData): array
    {
        $this->ensureAccessToken();
        
        $response = Http::withToken($this->accessToken)
            ->post($this->baseUrl . '/api/online/payment/v1/update', $updateData);

        if ($response->failed()) {
            throw new Exception("TorobPay Update Payment Failed: " . $response->body());
        }
        
        return $response->json();
    }
}
