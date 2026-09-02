<?php
declare(strict_types=1);

return [
    'db' => [
        'host' => getenv('RESELLER_DB_HOST') ?: 'localhost',
        'name' => getenv('RESELLER_DB_NAME') ?: 'isplzepc_reseller',
        'user' => getenv('RESELLER_DB_USER') ?: 'isplzepc_reseller',
        'pass' => getenv('RESELLER_DB_PASS') ?: '',
        'charset' => 'utf8mb4',
    ],
    'app_url' => getenv('RESELLER_APP_URL') ?: '',
    'session_name' => 'RESELLER_SID',
];
