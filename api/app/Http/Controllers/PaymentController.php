<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        DB::table('orders')->insert([
            'order_id' => $orderId,
            'user_id' => $user->id,
            'order_type' => $type,
            'order_package' => $packageId - 1,
            'order_status' => 'pending',
            'order_gateway' => $gateway,
            'order_amount' => $amount,
            'order_date' => time(),
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
     */
    private function initiatePayPal(string $orderId, float $amount, string $itemName, $user): array
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

            DB::table('orders')
                ->where('order_id', $orderId)
                ->update(['payment_intent_id' => $paymentIntent->id]);

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
     */
    public function paypalCallback(Request $request)
    {
        $orderId = $request->input('order_id');
        
        if (!$orderId) {
            return $this->paymentFailedResponse('Missing order ID');
        }
        
        return $this->paymentPendingResponse($orderId);
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

        DB::table('orders')
            ->where('order_id', $orderId)
            ->update([
                'order_status' => 'success',
                'transaction_id' => $txnId,
            ]);

        $userId = $order->user_id;
        $type = $order->order_type;
        $packageId = $order->order_package + 1;

        if ($type === 'credits') {
            $package = DB::table('config_credits')->where('id', $packageId)->first();
            if ($package) {
                DB::table('users')
                    ->where('id', $userId)
                    ->increment('credits', $package->credits);

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

        return response()->json([
            'order_id' => $order->order_id,
            'status' => $order->order_status,
            'type' => $order->order_type,
            'amount' => $order->order_amount,
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
