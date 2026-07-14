<?php

return [
    'enabled_channels' => ['database'],
    
    'channels' => [
        'database' => [
            'enabled' => true,
            'queue' => true,
            'priority' => 1,
        ],
        
        'mail' => [
            'enabled' => false,
            'queue' => true,
            'priority' => 2,
            'from_address' => env('MAIL_FROM_ADDRESS', 'noreply@flashsale.com'),
            'from_name' => env('MAIL_FROM_NAME', 'Flash Sale'),
        ],
        
        'sms' => [
            'enabled' => false,
            'queue' => true,
            'priority' => 3,
            'provider' => env('SMS_PROVIDER', 'twilio'),
        ],
        
        'push' => [
            'enabled' => false,
            'queue' => true,
            'priority' => 4,
            'provider' => env('PUSH_PROVIDER', 'fcm'),
        ],
    ],
    
    'retry' => [
        'database' => [
            'tries' => 3,
            'backoff' => [10, 30, 60],
        ],
        'mail' => [
            'tries' => 3,
            'backoff' => [10, 30, 60],
        ],
        'sms' => [
            'tries' => 3,
            'backoff' => [10, 30, 60],
        ],
        'push' => [
            'tries' => 3,
            'backoff' => [10, 30, 60],
        ],
    ],
    
    'fallback' => [
        'enabled' => true,
        'channels' => ['database'],
    ],
];