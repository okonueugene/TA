<?php

return [

    'ip' => env('TAD_IP', '192.168.0.1'),
    'internal_id' => env('TAD_INTERNAL_ID', 1),
    'com_key' => env('TAD_COM_KEY', 0),
    'description' => env('TAD_DESCRIPTION', 'Time Attendance Device'),
    'soap_port' => env('TAD_SOAP_PORT', 80),
    'udp_port' => env('TAD_UDP_PORT', 4370),
    'encoding' => env('TAD_ENCODING', 'utf-8'),

    // Custom: Notification emails for sync events
      'notify_emails' => [
        'versionaskari19@gmail.com',
        'christine.mwende@mcdave.co.ke',
        'joseph.uimbia@mcdave.co.ke',
        'judith.kendi@mcdave.co.ke'
        
    ],

    // 'notify_emails' => [
    //     'versionaskari19@gmail.com'
    // ],

    // Custom: Cache TTLs (in seconds)
    'cache_ttl' => [
        'error' => 3600,       // 1 hour
        'last_error' => 86400, // 1 day
    ],
];
