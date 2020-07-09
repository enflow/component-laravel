<?php

return [
    'robots_headers' => true,
    'endpoints' => [
        'flare' => [
            'base_url' => 'https://flareapp.io/projects/',
            'project' => '',
        ],
        'mailspons' => [
            'base_url' => 'https://mailspons.com/app/inboxes/',
            'inbox' => '',
        ],
        's3' => [
            'base_url' => 'https://console.aws.amazon.com/s3/buckets/',
        ],
        'git' => [
            'base_url' => 'https://github.com/',
            'project' => '',
        ],
        'chipper' => [
            'base_url' => 'https://app.chipperci.com/projects/',
            'project' => '',
        ],
        'phpmyadmin' => [
            'base_url' => env('PHPMYADMIN_URL', 'https://web0.clu0.enflow.nl/dbmyadmin/index.php'),
            'database' => env('DB_DATABASE_LIVE'),
            'username' => env('DB_USERNAME_LIVE'),
            'password' => env('DB_PASSWORD_LIVE'),
            'table' => 'users',
        ],
    ],
];
