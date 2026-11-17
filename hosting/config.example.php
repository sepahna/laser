<?php
declare(strict_types=1);

/*
 * Copy to config.php and fill in your DirectAdmin MySQL credentials.
 * Keep config.php outside public_html when possible. Never commit real credentials.
 */
return [
    'db' => [
        'host' => 'localhost',
        'name' => 'YOUR_MYSQL_DATABASE',
        'user' => 'YOUR_MYSQL_USER',
        'password' => 'YOUR_MYSQL_PASSWORD',
        'charset' => 'utf8mb4',
    ],
    // Use an absolute directory OUTSIDE public_html, e.g. /home/USERNAME/avid-private/receipts
    'receipt_dir' => '/home/YOUR_CPANEL_USER/avid-private/receipts',
    'admin_password_hash' => 'REPLACE_WITH_PASSWORD_HASH',
    'session_name' => 'avid_admin',
    'timezone' => 'Asia/Tehran',
];
