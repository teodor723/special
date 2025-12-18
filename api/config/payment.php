<?php

return [

    /*
    |--------------------------------------------------------------------------
    | General Payment Configuration
    |--------------------------------------------------------------------------
    */

    'currency' => env('PAYMENT_CURRENCY', 'USD'),
    'currency_symbol' => env('PAYMENT_CURRENCY_SYMBOL', '$'),
    'redirect_url' => env('PAYMENT_REDIRECT_URL', '/payment-result'),

    /*
    |--------------------------------------------------------------------------
    | PayPal Configuration
    |--------------------------------------------------------------------------
    */
    'paypal' => [
        'enabled' => env('PAYPAL_ENABLED', false),
        'sandbox' => env('PAYPAL_SANDBOX', true),
        'business_email' => env('PAYPAL_BUSINESS_EMAIL', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Stripe Configuration
    |--------------------------------------------------------------------------
    */
    'stripe' => [
        'enabled' => env('STRIPE_ENABLED', false),
        'publish_key' => env('STRIPE_PUBLISH_KEY', ''),
        'secret_key' => env('STRIPE_SECRET_KEY', ''),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET', ''),
    ],

];
