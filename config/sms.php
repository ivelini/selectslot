<?php

return [
    'stub' => [
        'is_active' => env('SMS_STUB', true),
        'code' => env('SMS_STUB_CODE', 1234),
    ],
    'provider' => env('SMS_PROVIDER'),
    'resend_cooldown_seconds' => (int) env('SMS_RESEND_COOLDOWN_SECONDS', 60),
];
