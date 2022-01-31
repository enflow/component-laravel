<?php

return [
    'robots_headers' => true,

    'security_headers' => [
        'permissions_policy' => true,
    ],

    'hosting_id' => env('HOSTING_ID'),
    'hsts' => env('HSTS'),
];
