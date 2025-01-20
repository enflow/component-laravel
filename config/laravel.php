<?php

return [
    'robots_headers' => true,
    'security_txt' => true,
    'hosting_id' => env('HOSTING_ID'),
    'hsts' => env('HSTS'),
    'horizon_monitor_slack_notification_url' => env('HORIZON_MONITOR_SLACK_NOTIFICATION_URL'),
];
