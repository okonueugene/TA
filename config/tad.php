<?php

return [
    'ip' => env('TAD_IP', '192.168.0.1'),
    'internal_id' => env('TAD_INTERNAL_ID', 1),
    'com_key' => env('TAD_COM_KEY', 0),
    'description' => env('TAD_DESCRIPTION', 'Time Attendance Device'),
    'soap_port' => env('TAD_SOAP_PORT', 80),
    'udp_port' => env('TAD_UDP_PORT', 4370),
    'encoding' => env('TAD_ENCODING', 'utf-8'),
];