<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    */

    'pusher' => [
        'app_id' => env('PUSHER_APP_ID'),
        'key' => env('PUSHER_APP_KEY'),
        'secret' => env('PUSHER_APP_SECRET'),
        'cluster' => env('PUSHER_APP_CLUSTER', 'mt1'),
        'options' => [
            'cluster' => env('PUSHER_APP_CLUSTER', 'mt1'),
            'useTLS' => true,
        ],
    ],

    'firebase' => [
        'api_key' => env('FIREBASE_API_KEY'),
    ],

    'geolocation' => [
        'api_key' => env('GEOLOCATION_API_KEY'),
    ],

    'aws' => [
        'access_key_id' => env('AWS_ACCESS_KEY_ID'),
        'secret_access_key' => env('AWS_SECRET_ACCESS_KEY'),
        'default_region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
        'bucket' => env('AWS_BUCKET'),
        'rekognition_min_confidence' => env('AWS_REKOGNITION_MIN_CONFIDENCE', 75),
    ],

];

