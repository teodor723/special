<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    /**
     * Get available packages (credits & premium)
     */
    public function getPackages()
    {
        $creditsPackages = DB::table('config_credits')
            ->orderBy('id')
            ->get()
            ->map(fn($p) => [
                'id' => $p->id,
                'type' => 'credits',
                'credits' => $p->credits,
                'price' => (float) $p->price,
                'currency' => config('payment.currency'),
            ]);

        $premiumPackages = DB::table('config_premium')
            ->orderBy('id')
            ->get()
            ->map(fn($p) => [
                'id' => $p->id,
                'type' => 'premium',
                'days' => $p->days,
                'price' => (float) $p->price,
                'currency' => config('payment.currency'),
                'currency_symbol' => config('payment.currency_symbol'),
            ]);

        return response()->json([
            'credits' => $creditsPackages,
            'premium' => $premiumPackages,
            'currency' => config('payment.currency'),
            'currency_symbol' => config('payment.currency_symbol'),
            'gateways' => $this->getEnabledGateways(),
        ]);
    }

    /**
     * Get enabled payment gateways from config
     */
    private function getEnabledGateways(): array
    {
        $gateways = [];

        if ($this->isEnabled('paypal')) {
            $gateways[] = [
                'id' => 'paypal',
                'name' => 'PayPal',
                'icon' => 'paypal',
            ];
        }

        if ($this->isEnabled('stripe')) {
            $gateways[] = [
                'id' => 'stripe',
                'name' => 'Credit Card',
                'icon' => 'card',
                'publishKey' => config('payment.stripe.publish_key'),
            ];
        }

        return $gateways;
    }

    /**
     * Check if a payment gateway is enabled
     */
    private function isEnabled(string $gateway): bool
    {
        $enabled = config("payment.{$gateway}.enabled");
        
        if (is_bool($enabled)) {
            return $enabled;
        }
        
        return in_array(strtolower((string) $enabled), ['yes', 'true', '1']);
    }

    /**
     * Initialize payment - create order and return payment URL
     */
    public function initiate(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|in:credits,premium',
            'package_id' => 'required|integer|min:1',
            'gateway' => 'required|string|in:paypal,stripe',
        ]);

        $user = $request->user();
        $type = $validated['type'];
        $packageId = $validated['package_id'];
        $gateway = $validated['gateway'];

        if (!$this->isEnabled($gateway)) {
            return response()->json(['error' => 'Payment gateway is not enabled'], 400);
        }

        $table = $type === 'credits' ? 'config_credits' : 'config_premium';
        $package = DB::table($table)->where('id', $packageId)->first();

        if (!$package) {
            return response()->json(['error' => 'Package not found'], 404);
        }

        $orderId = 'ORD-' . strtoupper(Str::random(12));
        $amount = (float) $package->price;
        $itemName = $type === 'credits' 
            ? "{$package->credits} Credits" 
            : "{$package->days} Days Premium";

        // Store order data - amount stored in raw_data for reference
        $rawData = [
            'amount' => $amount,
            'item_name' => $itemName,
        ];
        
        DB::table('orders')->insert([
            'order_id' => $orderId,
            'user_id' => $user->id,
            'order_type' => $type,
            'order_package' => $packageId - 1,
            'order_status' => 'pending',
            'order_gateway' => $gateway,
            'order_date' => (string) time(),
            'raw_data' => json_encode($rawData),
            'order_title' => $itemName,
        ]);

        $paymentData = match ($gateway) {
            'paypal' => $this->initiatePayPal($orderId, $amount, $itemName, $user),
            'stripe' => $this->initiateStripe($orderId, $amount, $itemName, $user),
        };

        return response()->json([
            'order_id' => $orderId,
            'gateway' => $gateway,
            ...$paymentData,
        ]);
    }

    /**
     * PayPal payment initialization
     * Uses REST API if credentials are provided, otherwise falls back to WPS
     */
    private function initiatePayPal(string $orderId, float $amount, string $itemName, $user): array
    {
        $clientId = config('payment.paypal.client_id');
        $clientSecret = config('payment.paypal.client_secret');
        $isSandbox = config('payment.paypal.sandbox', true);
        $mode = config('payment.paypal.mode', 'sandbox');
        
        // Use REST API if credentials are provided
        if (!empty($clientId) && !empty($clientSecret)) {
            return $this->initiatePayPalRestAPI($orderId, $amount, $itemName, $user, $clientId, $clientSecret, $mode === 'sandbox');
        }
        
        // Fallback to Website Payments Standard (WPS)
        return $this->initiatePayPalWPS($orderId, $amount, $itemName, $user);
    }
    
    /**
     * PayPal REST API initialization
     */
    private function initiatePayPalRestAPI(string $orderId, float $amount, string $itemName, $user, string $clientId, string $clientSecret, bool $sandbox): array
    {
        $currency = config('payment.currency', 'USD');
        // Use recommended PayPal endpoints
        $baseUrl = $sandbox 
            ? 'https://api-m.sandbox.paypal.com'
            : 'https://api-m.paypal.com';
        
        // Don't include order_id in return URL - PayPal will add token and PayerID
        // We'll find our order by matching the PayPal order ID (token) stored in raw_data
        $returnUrl = config('app.url') . '/api/payment/callback/paypal';
        $cancelUrl = config('app.url') . '/api/payment/cancel?order_id=' . $orderId;
        
        try {
            // Get access token
            $accessToken = $this->getPayPalAccessToken($baseUrl, $clientId, $clientSecret);
            
            if (!$accessToken) {
                Log::error('Failed to get PayPal access token');
                return [
                    'error' => 'Failed to initialize PayPal payment',
                    'method' => 'redirect',
                ];
            }
            
            // Create order
            $orderData = [
                'intent' => 'CAPTURE',
                'purchase_units' => [
                    [
                        'reference_id' => $orderId,
                        'description' => $itemName,
                        'amount' => [
                            'currency_code' => strtoupper($currency),
                            'value' => number_format($amount, 2, '.', ''),
                        ],
                        'invoice_id' => $orderId,
                    ],
                ],
                'application_context' => [
                    'brand_name' => config('app.name', 'Dating Site'),
                    'landing_page' => 'BILLING',
                    'user_action' => 'PAY_NOW',
                    'return_url' => $returnUrl,
                    'cancel_url' => $cancelUrl,
                ],
            ];
            
            // Use Http facade
            // WARNING: withoutVerifying() should never be used in production!
            // Use it only locally until your CA bundle is fixed
            $httpClient = config('app.env') === 'local' 
                ? Http::withoutVerifying()
                : Http::withOptions(['verify' => true]);
            
            $response = $httpClient
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Accept' => 'application/json',
                ])
                ->post($baseUrl . '/v2/checkout/orders', $orderData);
            
            if (!$response->successful() || $response->status() !== 201) {
                Log::error('PayPal order creation failed', [
                    'response' => $response->body(),
                    'code' => $response->status(),
                ]);
                return [
                    'error' => 'Failed to create PayPal order',
                    'method' => 'redirect',
                ];
            }
            
            $orderResponse = $response->json();
            
            if (!isset($orderResponse['id']) || !isset($orderResponse['links'])) {
                Log::error('Invalid PayPal order response', ['response' => $orderResponse]);
                return [
                    'error' => 'Invalid PayPal response',
                    'method' => 'redirect',
                ];
            }
            
            // Store PayPal order ID in raw_data
            $order = DB::table('orders')->where('order_id', $orderId)->first();
            $rawData = $order->raw_data ? json_decode($order->raw_data, true) : [];
            $rawData['paypal_order_id'] = $orderResponse['id'];
            
            DB::table('orders')
                ->where('order_id', $orderId)
                ->update(['raw_data' => json_encode($rawData)]);
            
            // Find approval URL
            $approvalUrl = null;
            foreach ($orderResponse['links'] as $link) {
                if ($link['rel'] === 'approve') {
                    $approvalUrl = $link['href'];
                    break;
                }
            }
            
            if (!$approvalUrl) {
                Log::error('No approval URL in PayPal response', ['response' => $orderResponse]);
                return [
                    'error' => 'No approval URL received',
                    'method' => 'redirect',
                ];
            }
            
            return [
                'redirect_url' => $approvalUrl,
                'method' => 'redirect',
            ];
            
        } catch (\Exception $e) {
            Log::error('PayPal REST API error: ' . $e->getMessage());
            return [
                'error' => 'Payment initialization failed',
                'method' => 'redirect',
            ];
        }
    }
    
    /**
     * Get PayPal access token
     * WARNING: withoutVerifying() should never be used in production!
     * Use it only locally until your CA bundle is fixed.
     */
    private function getPayPalAccessToken(string $baseUrl, string $clientId, string $clientSecret): ?string
    {
        try {
            // Use Http facade
            // WARNING: withoutVerifying() should never be used in production!
            // Use it only locally until your CA bundle is fixed
            $httpClient = config('app.env') === 'local' 
                ? Http::withoutVerifying()
                : Http::withOptions(['verify' => true]);
            
            $response = $httpClient
                ->withBasicAuth($clientId, $clientSecret)
                ->asForm()
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Accept-Language' => 'en_US',
                ])
                ->post($baseUrl . '/v1/oauth2/token', [
                    'grant_type' => 'client_credentials',
                ]);

            if (!$response->successful() || $response->status() !== 200) {
                Log::error('PayPal token request failed', [
                    'response' => $response->body(),
                    'code' => $response->status(),
                ]);
                return null;
            }

            $tokenData = $response->json();
            return $tokenData['access_token'] ?? null;
        } catch (\Exception $e) {
            Log::error('PayPal token request exception: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * PayPal Website Payments Standard (WPS) initialization (fallback)
     */
    private function initiatePayPalWPS(string $orderId, float $amount, string $itemName, $user): array
    {
        $currency = config('payment.currency', 'USD');
        $businessEmail = config('payment.paypal.business_email');
        $isSandbox = config('payment.paypal.sandbox', true);
        
        $returnUrl = config('app.url') . '/api/payment/callback/paypal';
        $cancelUrl = config('app.url') . '/api/payment/cancel';
        $notifyUrl = config('app.url') . '/api/payment/ipn/paypal';

        $paypalUrl = $isSandbox 
            ? 'https://www.sandbox.paypal.com/cgi-bin/webscr'
            : 'https://www.paypal.com/cgi-bin/webscr';
        
        $params = [
            'cmd' => '_xclick',
            'business' => $businessEmail,
            'item_name' => $itemName,
            'amount' => number_format($amount, 2, '.', ''),
            'currency_code' => $currency,
            'invoice' => $orderId,
            'return' => $returnUrl . '?order_id=' . $orderId,
            'cancel_return' => $cancelUrl . '?order_id=' . $orderId,
            'notify_url' => $notifyUrl,
            'custom' => json_encode([
                'user_id' => $user->id,
                'order_id' => $orderId,
            ]),
            'no_shipping' => 1,
            'no_note' => 1,
        ];

        return [
            'redirect_url' => $paypalUrl . '?' . http_build_query($params),
            'method' => 'redirect',
        ];
    }

    /**
     * Stripe payment initialization
     */
    private function initiateStripe(string $orderId, float $amount, string $itemName, $user): array
    {
        $secretKey = config('payment.stripe.secret_key');
        $currency = config('payment.currency', 'usd');

        \Stripe\Stripe::setApiKey($secretKey);

        try {
            $paymentIntent = \Stripe\PaymentIntent::create([
                'amount' => (int) ($amount * 100),
                'currency' => strtolower($currency),
                'description' => $itemName,
                'metadata' => [
                    'order_id' => $orderId,
                    'user_id' => $user->id,
                ],
            ]);

            // Store Stripe payment intent ID in raw_data
            $order = DB::table('orders')->where('order_id', $orderId)->first();
            $rawData = $order->raw_data ? json_decode($order->raw_data, true) : [];
            $rawData['stripe_payment_intent_id'] = $paymentIntent->id;
            
            DB::table('orders')
                ->where('order_id', $orderId)
                ->update(['raw_data' => json_encode($rawData)]);

            return [
                'client_secret' => $paymentIntent->client_secret,
                'method' => 'stripe',
            ];
        } catch (\Exception $e) {
            Log::error('Stripe error: ' . $e->getMessage());
            return [
                'error' => 'Payment initialization failed',
                'method' => 'stripe',
            ];
        }
    }

    /**
     * PayPal callback (return URL)
     * Handles both REST API (token) and WPS (order_id) callbacks
     */
    public function paypalCallback(Request $request)
    {
        $orderId = $request->input('order_id');
        $token = $request->input('token'); // PayPal order ID from REST API
        $payerId = $request->input('PayerID');
        
        Log::info('PayPal callback received', [
            'order_id' => $orderId,
            'token' => $token,
            'PayerID' => $payerId,
            'all_params' => $request->all(),
        ]);
        
        // REST API callback (has token and PayerID)
        if ($token && $payerId) {
            return $this->handlePayPalRestAPICallback($token, $payerId);
        }
        
        // Fallback: If we have order_id but no token, try to find order and check if it's REST API
        if ($orderId) {
            $order = DB::table('orders')->where('order_id', $orderId)->first();
            if ($order && $order->order_gateway === 'paypal' && $order->raw_data) {
                $rawData = json_decode($order->raw_data, true);
                // If we have a PayPal order ID stored, try to get token from request or use stored one
                if (isset($rawData['paypal_order_id'])) {
                    // Try to get token from request, or use stored PayPal order ID
                    $paypalOrderId = $token ?: $rawData['paypal_order_id'];
                    // If we have PayerID, proceed with capture
                    if ($payerId) {
                        return $this->handlePayPalRestAPICallback($paypalOrderId, $payerId);
                    }
                }
            }
            // WPS callback (has order_id but no token/PayerID)
            return $this->paymentPendingResponse($orderId);
        }
        
        return $this->paymentFailedResponse('Missing order ID or token');
    }
    
    /**
     * Handle PayPal REST API callback - capture the payment
     */
    private function handlePayPalRestAPICallback(string $token, string $payerId)
    {
        // Find order by PayPal order ID stored in raw_data
        // Search recent orders (last 24 hours) to limit the search
        $recentTime = time() - 86400;
        $orders = DB::table('orders')
            ->where('order_gateway', 'paypal')
            ->where('order_status', 'pending')
            ->where('order_date', '>=', (string) $recentTime)
            ->get();
        
        $order = null;
        foreach ($orders as $o) {
            if ($o->raw_data) {
                $rawData = json_decode($o->raw_data, true);
                if (isset($rawData['paypal_order_id']) && $rawData['paypal_order_id'] === $token) {
                    $order = $o;
                    break;
                }
            }
        }
        
        if (!$order) {
            Log::error('PayPal order not found', ['token' => $token]);
            return $this->paymentFailedResponse('Order not found');
        }
        
        // If order is already completed, redirect to success
        if ($order->order_status === 'success') {
            $redirectUrl = config('payment.redirect_url', '/') . '?payment=success&order_id=' . $order->order_id;
            return redirect($redirectUrl);
        }
        
        $clientId = config('payment.paypal.client_id');
        $clientSecret = config('payment.paypal.client_secret');
        $mode = config('payment.paypal.mode', 'sandbox');
        // Use recommended PayPal endpoints
        $baseUrl = $mode === 'sandbox' 
            ? 'https://api-m.sandbox.paypal.com'
            : 'https://api-m.paypal.com';
        
        try {
            // Get access token
            $accessToken = $this->getPayPalAccessToken($baseUrl, $clientId, $clientSecret);
            
            if (!$accessToken) {
                Log::error('Failed to get PayPal access token for capture');
                return $this->paymentPendingResponse($order->order_id);
            }
            
            // Capture the payment using Http facade
            // WARNING: withoutVerifying() should never be used in production!
            // Use it only locally until your CA bundle is fixed
            $httpClient = config('app.env') === 'local' 
                ? Http::withoutVerifying()
                : Http::withOptions(['verify' => true]);
            
            // PayPal capture endpoint - send empty JSON object {}
            // PayPal requires {} not [] for the capture body
            // Using withBody() to send empty JSON object string explicitly
            $response = $httpClient
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Accept' => 'application/json',
                ])
                ->withBody('{}', 'application/json')
                ->send('POST', $baseUrl . '/v2/checkout/orders/' . $token . '/capture');
            
            $statusCode = $response->status();
            $responseBody = $response->body();
            $captureResponse = $response->json();
            
            Log::info('PayPal capture response', [
                'status_code' => $statusCode,
                'response' => $captureResponse,
                'token' => $token,
                'order_id' => $order->order_id,
            ]);
            
            if ($response->successful() && ($statusCode === 201 || $statusCode === 200)) {
                // Check status - could be in different places depending on PayPal response structure
                $status = $captureResponse['status'] ?? 
                         ($captureResponse['purchase_units'][0]['payments']['captures'][0]['status'] ?? null);
                
                if ($status === 'COMPLETED' || $status === 'completed') {
                    // Payment successful
                    $transactionId = $captureResponse['purchase_units'][0]['payments']['captures'][0]['id'] ?? $token;
                    $rawData = $order->raw_data ? json_decode($order->raw_data, true) : [];
                    $amount = $captureResponse['purchase_units'][0]['payments']['captures'][0]['amount']['value'] ?? ($rawData['amount'] ?? 0);
                    
                    $this->processSuccessfulPayment($order->order_id, $transactionId, (float) $amount, 'paypal');
                    
                    $redirectUrl = config('payment.redirect_url', '/') . '?payment=success&order_id=' . $order->order_id;
                    return redirect($redirectUrl);
                } else {
                    Log::warning('PayPal capture not completed', [
                        'status' => $status,
                        'full_response' => $captureResponse,
                    ]);
                }
            } else {
                Log::error('PayPal capture request failed', [
                    'status_code' => $statusCode,
                    'response_body' => $responseBody,
                    'response_json' => $captureResponse,
                ]);
            }
            
            return $this->paymentPendingResponse($order->order_id);
            
        } catch (\Exception $e) {
            Log::error('PayPal capture error: ' . $e->getMessage());
            return $this->paymentPendingResponse($order->order_id);
        }
    }

    /**
     * PayPal IPN (Instant Payment Notification)
     */
    public function paypalIPN(Request $request)
    {
        Log::channel('payment')->info('PayPal IPN received', $request->all());

        $raw = $request->getContent();
        $isSandbox = config('payment.paypal.sandbox', true);
        $verified = $this->verifyPayPalIPN($raw, $isSandbox);

        if (!$verified) {
            Log::channel('payment')->warning('PayPal IPN verification failed');
            return response('INVALID', 400);
        }

        $paymentStatus = $request->input('payment_status');
        $orderId = $request->input('invoice');
        $txnId = $request->input('txn_id');
        $amount = $request->input('mc_gross');

        if ($paymentStatus === 'Completed') {
            $this->processSuccessfulPayment($orderId, $txnId, $amount, 'paypal');
        }

        return response('OK');
    }

    /**
     * Stripe webhook
     */
    public function stripeWebhook(Request $request)
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $webhookSecret = config('payment.stripe.webhook_secret');

        try {
            $event = \Stripe\Webhook::constructEvent($payload, $sigHeader, $webhookSecret);
        } catch (\Exception $e) {
            Log::channel('payment')->error('Stripe webhook error: ' . $e->getMessage());
            return response('Invalid signature', 400);
        }

        Log::channel('payment')->info('Stripe webhook received', ['type' => $event->type]);

        if ($event->type === 'payment_intent.succeeded') {
            $paymentIntent = $event->data->object;
            $orderId = $paymentIntent->metadata->order_id ?? null;
            
            if ($orderId) {
                $this->processSuccessfulPayment(
                    $orderId,
                    $paymentIntent->id,
                    $paymentIntent->amount / 100,
                    'stripe'
                );
            }
        }

        return response('OK');
    }

    /**
     * Process successful payment
     */
    private function processSuccessfulPayment(string $orderId, string $txnId, float $amount, string $gateway): void
    {
        $order = DB::table('orders')->where('order_id', $orderId)->first();

        if (!$order || $order->order_status === 'success') {
            return;
        }

        // Store transaction ID in raw_data
        $order = DB::table('orders')->where('order_id', $orderId)->first();
        $rawData = $order->raw_data ? json_decode($order->raw_data, true) : [];
        $rawData['transaction_id'] = $txnId;
        
        DB::table('orders')
            ->where('order_id', $orderId)
            ->update([
                'order_status' => 'success',
                'raw_data' => json_encode($rawData),
            ]);

        $userId = $order->user_id;
        $type = $order->order_type;
        $packageId = $order->order_package + 1;

        if ($type === 'credits') {
            $package = DB::table('config_credits')->where('id', $packageId)->first();
            if ($package) {
                $currentTime = time();
                
                // Increment user credits
                DB::table('users')
                    ->where('id', $userId)
                    ->increment('credits', $package->credits);

                // Log to users_credits table
                DB::table('users_credits')->insert([
                    'uid' => $userId,
                    'credits' => $package->credits,
                    'reason' => "Credits purchase ({$package->credits} credits)",
                    'time' => $currentTime,
                    'type' => 'added',
                ]);

                $this->recordSale($userId, $package->price, $gateway, "{$package->credits} Credits", 'credits', $package->credits);
            }
        } else {
            $package = DB::table('config_premium')->where('id', $packageId)->first();
            if ($package) {
                $premiumExpiry = time() + ($package->days * 86400);
                DB::table('users_premium')
                    ->where('uid', $userId)
                    ->update(['premium' => $premiumExpiry]);

                $this->recordSale($userId, $package->price, $gateway, "{$package->days} Days Premium", 'premium', $package->days);
            }
        }

        Log::channel('payment')->info('Payment processed successfully', [
            'order_id' => $orderId,
            'user_id' => $userId,
            'type' => $type,
        ]);
    }

    /**
     * Record sale in database
     */
    private function recordSale(int $userId, float $amount, string $gateway, string $action, string $type, int $quantity): void
    {
        DB::table('sales')->insert([
            'u_id' => $userId,
            'amount' => $amount,
            'gateway' => $gateway,
            'action' => $action,
            'time' => time(),
            'type' => $type,
            'quantity' => $quantity,
            'saledate' => date('m/d/Y H:i'),
        ]);
    }

    /**
     * Verify PayPal IPN
     */
    private function verifyPayPalIPN(string $raw, bool $sandbox = false): bool
    {
        $url = $sandbox 
            ? 'https://ipnpb.sandbox.paypal.com/cgi-bin/webscr'
            : 'https://ipnpb.paypal.com/cgi-bin/webscr';

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, 'cmd=_notify-validate&' . $raw);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        
        $response = curl_exec($ch);
        curl_close($ch);

        return $response === 'VERIFIED';
    }

    /**
     * Check order status
     */
    public function checkStatus(Request $request, string $orderId)
    {
        $order = DB::table('orders')
            ->where('order_id', $orderId)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$order) {
            return response()->json(['error' => 'Order not found'], 404);
        }

        $rawData = $order->raw_data ? json_decode($order->raw_data, true) : [];
        $amount = $rawData['amount'] ?? 0;
        
        return response()->json([
            'order_id' => $order->order_id,
            'status' => $order->order_status,
            'type' => $order->order_type,
            'amount' => $amount,
            'gateway' => $order->order_gateway,
        ]);
    }

    /**
     * Payment cancelled
     */
    public function cancelled(Request $request)
    {
        $orderId = $request->input('order_id');
        
        if ($orderId) {
            DB::table('orders')
                ->where('order_id', $orderId)
                ->update(['order_status' => 'cancelled']);
        }

        $redirectUrl = config('payment.redirect_url', '/') . '?payment=cancelled';
        return redirect($redirectUrl);
    }

    private function paymentPendingResponse(string $orderId)
    {
        $redirectUrl = config('payment.redirect_url', '/') . '?payment=pending&order_id=' . $orderId;
        return redirect($redirectUrl);
    }

    private function paymentFailedResponse(string $message)
    {
        $redirectUrl = config('payment.redirect_url', '/') . '?payment=failed&message=' . urlencode($message);
        return redirect($redirectUrl);
    }
}
