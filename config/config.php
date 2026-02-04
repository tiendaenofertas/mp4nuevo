<?php
declare(strict_types=1);

/**
 * MP4 Security System - Archivo de Configuración Corregido (v2.2 Stable)
 * Mantiene compatibilidad con enlaces antiguos.
 */

// Configuración de errores y sesión para producción
error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_secure', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? '1' : '0');
    ini_set('session.use_strict_mode', '1');
    if (PHP_VERSION_ID >= 70300) {
        ini_set('session.cookie_samesite', 'Strict');
    }
    session_start();
}

// Constantes del sistema - NO MODIFICADAS PARA MANTENER COMPATIBILIDAD
define('MP4_VERSION', '2.2_STABLE');
define('MP4_ENCRYPTION_KEY', 'mp4_secure_key_2025_' . hash('sha256', 'xzorra_protection'));
define('MP4_ENCRYPTION_IV', substr(hash('sha256', 'xzorra_iv_2025'), 0, 16));
define('MP4_HMAC_KEY', 'hmac_xzorra_2025_' . hash('sha256', 'protection_key'));
define('MP4_TOKEN_LIFETIME', 1800); // 30 minutos
define('MP4_MAX_REQUESTS_PER_IP', 60); // Ajustado para uso real

// Dominios permitidos
define('MP4_ALLOWED_DOMAINS', [
    'xcuca.net',
    'www.xcuca.net',
    'earnvids.xzorra.net',
    'xpro.xcuca.net',
    'xcuca.com',
    'www.xcuca.com',
    'xpro.xcuca.com',
    'localhost',
    '127.0.0.1'
]);

/**
 * Compatibilidad para PHP < 8.0
 */
if (!function_exists('str_ends_with')) {
    function str_ends_with(string $haystack, string $needle): bool {
        return $needle === '' || substr($haystack, -strlen($needle)) === $needle;
    }
}

/**
 * Headers de seguridad avanzados
 */
function setSecurityHeaders(): void {
    if (headers_sent()) return;
    
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Cache-Control: no-cache, no-store, must-revalidate, private');
    
    // Content Security Policy (CSP) optimizado
    $csp = "default-src 'self'; script-src 'self' 'unsafe-inline' *.jwpcdn.com code.jquery.com cdn.jsdelivr.net; " .
           "style-src 'self' 'unsafe-inline' cdn.jsdelivr.net cdnjs.cloudflare.com fonts.googleapis.com; " .
           "img-src 'self' data: https:; media-src 'self' data: blob: https:; connect-src 'self' https:; " .
           "frame-ancestors 'self' " . implode(' ', MP4_ALLOWED_DOMAINS);
    
    header("Content-Security-Policy: $csp");
}

/**
 * Validación de referer REFORZADA
 */
function validateReferer(): bool {
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    $serverName = $_SERVER['SERVER_NAME'] ?? '';
    
    // Permitir en local
    if (in_array($serverName, ['localhost', '127.0.0.1'])) return true;
    
    if (empty($referer)) return false; // Bloqueo de acceso directo por seguridad
    
    $refererHost = parse_url($referer, PHP_URL_HOST);
    if (!$refererHost) return false;
    
    foreach (MP4_ALLOWED_DOMAINS as $domain) {
        if ($refererHost === $domain || str_ends_with($refererHost, ".$domain")) {
            return true;
        }
    }
    
    return $refererHost === $serverName;
}

/**
 * Encriptación (Compatible con IV estático)
 */
function encodeSecure(string $data): string|false {
    if (empty($data)) return false;
    try {
        $payload = json_encode([
            'data' => $data,
            'timestamp' => time(),
            'nonce' => bin2hex(random_bytes(8)),
            'ip' => getClientIP()
        ], JSON_THROW_ON_ERROR);
        
        $hmac = hash_hmac('sha256', $payload, MP4_HMAC_KEY);
        $combined = $payload . '|' . $hmac;
        
        $encryption = openssl_encrypt($combined, 'AES-256-CBC', MP4_ENCRYPTION_KEY, OPENSSL_RAW_DATA, MP4_ENCRYPTION_IV);
        return $encryption ? base64_encode($encryption) : false;
    } catch (Exception $e) { return false; }
}

/**
 * Desencriptación (Compatible con IV estático y validación reactivada)
 */
function decodeSecure(string $encodedData, int $maxAge = MP4_TOKEN_LIFETIME): array|false {
    if (empty($encodedData)) return false;
    try {
        $decoded = base64_decode($encodedData, true);
        if (!$decoded) return false;

        $decryption = openssl_decrypt($decoded, 'AES-256-CBC', MP4_ENCRYPTION_KEY, OPENSSL_RAW_DATA, MP4_ENCRYPTION_IV);
        if (!$decryption) return false;

        $parts = explode('|', $decryption);
        if (count($parts) !== 2) return false;
        
        list($payload, $receivedHmac) = $parts;
        if (!hash_equals(hash_hmac('sha256', $payload, MP4_HMAC_KEY), $receivedHmac)) return false;

        $data = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        
        // Validación de tiempo REACTIVADA para seguridad
        if ((time() - $data['timestamp']) > $maxAge) {
            logSecurityEvent("Token expirado detectado");
            return false; 
        }
        
        return $data;
    } catch (Exception $e) { return false; }
}

/**
 * Rate limiting FUNCIONAL basado en sesión
 */
function checkRateLimit(string $identifier = null, int $limit = MP4_MAX_REQUESTS_PER_IP): bool {
    $identifier = $identifier ?: getClientIP();
    if (session_status() === PHP_SESSION_NONE) session_start();
    
    $now = time();
    $key = 'rate_' . hash('md5', $identifier);
    
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = ['count' => 1, 'start' => $now];
        return true;
    }
    
    if (($now - $_SESSION[$key]['start']) > 3600) {
        $_SESSION[$key] = ['count' => 1, 'start' => $now];
        return true;
    }
    
    $_SESSION[$key]['count']++;
    return $_SESSION[$key]['count'] <= $limit;
}

function getClientIP(): string {
    $headers = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
    foreach ($headers as $header) {
        if (!empty($_SERVER[$header])) {
            $ips = explode(',', $_SERVER[$header]);
            $ip = trim($ips[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
        }
    }
    return '0.0.0.0';
}

function sanitizeInput(mixed $input): string {
    if (is_array($input)) return '';
    return htmlspecialchars(strip_tags(trim((string)$input)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function generateCSRFToken(): string {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken(string $token): bool {
    if (session_status() === PHP_SESSION_NONE) session_start();
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function logSecurityEvent(string $event, array $context = []): void {
    $msg = "SECURITY: $event | IP: " . getClientIP() . " | Context: " . json_encode($context);
    error_log($msg);
}

setSecurityHeaders();
