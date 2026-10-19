<?php

return [
    'provider' => env('WHATSAPP_PROVIDER', 'fake'),
    'access_token' => env('WHATSAPP_ACCESS_TOKEN', env('WHATSAPP_TOKEN')),
    'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
    'business_account_id' => env('WHATSAPP_BUSINESS_ACCOUNT_ID'),
    'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),
    'app_secret' => env('WHATSAPP_APP_SECRET'),
    'api_version' => env('WHATSAPP_API_VERSION', 'v21.0'),
    'outbound_per_minute' => (int) env('WHATSAPP_OUTBOUND_PER_MINUTE', 30),
    'replay_window_seconds' => (int) env('WHATSAPP_REPLAY_WINDOW_SECONDS', 900),
];
