<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Initial User Values
    |--------------------------------------------------------------------------
    */
    'initial_credits' => env('INITIAL_CREDITS', 100),
    'initial_super_likes' => env('INITIAL_SUPER_LIKES', 5),

    /*
    |--------------------------------------------------------------------------
    | Chat Settings
    |--------------------------------------------------------------------------
    */
    'chat_basic_limit' => env('CHAT_BASIC_LIMIT', 10),
    'chat_premium_limit' => env('CHAT_PREMIUM_LIMIT', 'unlimited'),
    'chat_transfer_credits' => env('CHAT_TRANSFER_CREDITS', false),
    'chat_credits_per_message' => env('CHAT_CREDITS_PER_MESSAGE', 1),
    'chat_credits_gender' => env('CHAT_CREDITS_GENDER', 0),

    /*
    |--------------------------------------------------------------------------
    | Pricing
    |--------------------------------------------------------------------------
    */
    'price_spotlight' => env('PRICE_SPOTLIGHT', 50),
    'price_rise_up' => env('PRICE_RISE_UP', 100),
    'price_discover_100' => env('PRICE_DISCOVER_100', 75),
    'price_super_like' => env('PRICE_SUPER_LIKE', 10),

    /*
    |--------------------------------------------------------------------------
    | Genders
    |--------------------------------------------------------------------------
    */
    'genders' => [1, 2], // 1 = Male, 2 = Female

    /*
    |--------------------------------------------------------------------------
    | Plugin Settings
    |--------------------------------------------------------------------------
    */
    'plugin_meet_search_result' => 20,
    'plugin_story_days' => env('STORY_EXPIRY_DAYS', 1),
    'plugin_story_review' => false,
    'plugin_spotlight_limit' => 50,
    'plugin_spotlight_area' => 'Worldwide',
    'plugin_spotlight_worldwide' => 'Yes',
    'plugin_fake_users_notification_timeout' => 30,

    /*
    |--------------------------------------------------------------------------
    | Photo/Video Review Settings
    |--------------------------------------------------------------------------
    */
    'photo_review_enabled' => env('PHOTO_REVIEW_ENABLED', false),
    'video_review_enabled' => env('VIDEO_REVIEW_ENABLED', false),
    'sightengine_enabled' => env('SIGHTENGINE_ENABLED', false),
    'sightengine_api_user' => env('SIGHTENGINE_API_USER'),
    'sightengine_api_secret' => env('SIGHTENGINE_API_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Activity Logging
    |--------------------------------------------------------------------------
    */
    'log_activity' => env('LOG_ACTIVITY', true),

];

