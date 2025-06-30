<?php
declare(strict_types=1);

error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure', '1');
ini_set('session.use_strict_mode', '1');

require_once __DIR__ . '/library.php';

define('MP4_VERSION', '2.0');
define('MP4_ENCRYPTION_KEY', 'apicodesdotcom_v2_2025');
define('MP4_ENCRYPTION_IV', '1234567890123456');
define('MP4_ALLOWED_DOMAINS', [
    'xzorra.net',
    'xzorra.net'
]);

function setSecurityHeaders(): void {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

function validateReferer(): bool {
    if (!isset($_SERVER['HTTP_REFERER'])) {
        return false;
    }
    
    foreach (MP4_ALLOWED_DOMAINS as $domain) {
        if (str_contains($_SERVER['HTTP_REFERER'], $domain)) {
            return true;
        }
    }
    
    return false;
}
?>