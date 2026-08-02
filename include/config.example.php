<?php

declare(strict_types=1);

define('WEB_TITLE', getenv('WEB_TITLE') ?: 'BankPro');
define('WEB_URL', rtrim(getenv('WEB_URL') ?: 'http://localhost:3000', '/'));
define('WEB_EMAIL', getenv('WEB_EMAIL') ?: 'support@example.com');

define('SMTP_HOST', getenv('SMTP_HOST') ?: '#');
define('SMTP_USERNAME', getenv('SMTP_USERNAME') ?: '#');
define('SMTP_PASSWORD', getenv('SMTP_PASSWORD') ?: '#');
define('SMTP_PORT', (int)(getenv('SMTP_PORT') ?: 465));
define('SMTP_SECURE', getenv('SMTP_SECURE') ?: 'ssl');
define('SMTP_FROM_EMAIL', getenv('SMTP_FROM_EMAIL') ?: WEB_EMAIL);
define('SMTP_FROM_NAME', getenv('SMTP_FROM_NAME') ?: WEB_TITLE);

$web_url = WEB_URL;

function dbConnect(): PDO
{
    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $port = getenv('DB_PORT') ?: '3306';
    $database = getenv('DB_NAME') ?: 'bankpro';
    $username = getenv('DB_USERNAME') ?: 'root';
    $password = getenv('DB_PASSWORD') ?: '';
    $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";

    return new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

function inputValidation($value): string
{
    return trim(htmlspecialchars(htmlentities((string)$value)));
}
