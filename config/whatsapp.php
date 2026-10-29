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

    /*
     * Templates registered for every organization by `whatsapp:templates:sync --live`.
     * Create them in WhatsApp Manager with these names, in Arabic (ar) and English (en),
     * with {{1}}, {{2}}, ... in the order of `parameters` (docs/whatsapp-live-setup.md).
     */
    'templates' => [
        'appointment_reminder' => [
            'name' => env('WHATSAPP_TEMPLATE_REMINDER', 'opsai_appointment_reminder'),
            'parameters' => ['customer_first_name', 'service_name', 'appointment_date', 'appointment_time', 'company_name'],
        ],
        'appointment_confirmation' => [
            'name' => env('WHATSAPP_TEMPLATE_CONFIRMATION', 'opsai_appointment_confirmation'),
            'parameters' => ['customer_first_name', 'service_name', 'appointment_date', 'appointment_time', 'company_name'],
        ],
    ],
    'template_languages' => ['ar', 'en'],
    'reminder_hours_before' => (int) env('WHATSAPP_REMINDER_HOURS_BEFORE', 24),
];
