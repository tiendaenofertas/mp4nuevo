<?php
declare(strict_types=1);

// Configuración de errores y sesión
error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Solo configurar sesión si no está activa
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_secure', '1');
    ini_set('session.use_strict_mode', '1');
    if (PHP_VERSION_ID >= 70300) {
        ini_set('session.cookie_samesite', 'Strict');
    }
}

// Constantes del sistema
define('MP4_VERSION', '2.1');
define('MP4_ENCRYPTION_KEY', 'mp4_secure_key_2025_' . hash('sha256', 'xzorra_protection'));
define('MP4_ENCRYPTION_IV', substr(hash('sha256', 'xzorra_iv_2025'), 0, 16));
define('MP4_HMAC_KEY', 'hmac_xzorra_2025_' . hash('sha256', 'protection_key'));
define('MP4_TOKEN_LIFETIME', 1800); // 30 minutos
define('MP4_MAX_REQUESTS_PER_IP', 50); // Por hora

// Dominios permitidos - EXPANDIDA PARA COMPATIBILIDAD
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
 * Función de compatibilidad para str_ends_with (PHP < 8.0)
 */
if (!function_exists('str_ends_with')) {
    function str_ends_with(string $haystack, string $needle): bool {
        $length = strlen($needle);
        if ($length === 0) {
            return true;
        }
        return substr($haystack, -$length) === $needle;
    }
}

/**
 * Establece headers de seguridad avanzados
 */
function setSecurityHeaders(): void {
    try {
        if (headers_sent()) {
            return; // No enviar headers si ya se enviaron
        }
        
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Cache-Control: no-cache, no-store, must-revalidate, private');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Content Security Policy más permisivo temporalmente
        $csp = "default-src 'self' 'unsafe-inline' 'unsafe-eval'; " .
               "script-src 'self' 'unsafe-inline' 'unsafe-eval' *.jwpcdn.com code.jquery.com cdn.jsdelivr.net; " .
               "style-src 'self' 'unsafe-inline' cdn.jsdelivr.net cdnjs.cloudflare.com fonts.googleapis.com; " .
               "font-src 'self' fonts.gstatic.com cdnjs.cloudflare.com; " .
               "img-src 'self' data: https:; " .
               "media-src 'self' data: blob: https:; " .
               "connect-src 'self' https:; " .
               "object-src 'none'; " .
               "base-uri 'self'";
        
        header("Content-Security-Policy: $csp");
        
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
        
    } catch (Exception $e) {
        error_log("Error estableciendo headers: " . $e->getMessage());
    }
}

/**
 * Validación de referer MEJORADA Y MÁS PERMISIVA
 */
function validateReferer(): bool {
    try {
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        $serverName = $_SERVER['SERVER_NAME'] ?? '';
        
        // Permitir siempre en desarrollo local
        if (in_array($serverName, ['localhost', '127.0.0.1'])) {
            return true;
        }
        
        // Permitir si viene del mismo dominio (sin referer o desde el mismo sitio)
        if (empty($referer)) {
            // Permitir acceso directo si es desde el mismo servidor
            return true; // TEMPORALMENTE PERMISIVO
        }
        
        // Validar dominio del referer
        $refererHost = parse_url($referer, PHP_URL_HOST);
        if (!$refererHost) {
            error_log("Referer con formato inválido: $referer");
            return true; // TEMPORALMENTE PERMISIVO
        }
        
        // Verificar si el referer es de un dominio permitido
        foreach (MP4_ALLOWED_DOMAINS as $domain) {
            if ($refererHost === $domain || 
                str_ends_with($refererHost, ".$domain") ||
                strpos($refererHost, $domain) !== false) {
                return true;
            }
        }
        
        // Permitir si viene del mismo servidor
        if ($refererHost === $serverName) {
            return true;
        }
        
        // Log para debug pero permitir acceso temporalmente
        error_log("Referer no autorizado pero permitido temporalmente: $refererHost desde IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'desconocida'));
        return true; // TEMPORALMENTE PERMISIVO PARA DEBUG
        
    } catch (Exception $e) {
        error_log("Error validando referer: " . $e->getMessage());
        return true; // En caso de error, permitir acceso
    }
}

/**
 * Encriptación con timestamp y verificación HMAC
 */
if (!function_exists('encodeSecure')) {
    function encodeSecure(string $data): string|false {
        if (empty($data)) {
            return false;
        }

        try {
            $timestamp = time();
            $nonce = bin2hex(random_bytes(8));
            
            // Crear payload con metadatos
            $payload = json_encode([
                'data' => $data,
                'timestamp' => $timestamp,
                'nonce' => $nonce,
                'ip' => getClientIP()
            ], JSON_THROW_ON_ERROR);
            
            // Generar HMAC para integridad
            $hmac = hash_hmac('sha256', $payload, MP4_HMAC_KEY);
            $combined = $payload . '|' . $hmac;
            
            // Encriptar con AES-256-CBC
            $iv = MP4_ENCRYPTION_IV;
            $encryption = openssl_encrypt(
                $combined,
                'AES-256-CBC',
                MP4_ENCRYPTION_KEY,
                OPENSSL_RAW_DATA,
                $iv
            );
            
            if ($encryption === false) {
                error_log("Error en encriptación: " . openssl_error_string());
                return false;
            }
            
            return base64_encode($encryption);
            
        } catch (Exception $e) {
            error_log("Error en encodeSecure: " . $e->getMessage());
            return false;
        }
    }
}

/**
 * Desencriptación con validación de timestamp y HMAC
 */
if (!function_exists('decodeSecure')) {
    function decodeSecure(string $encodedData, int $maxAge = MP4_TOKEN_LIFETIME): array|false {
        if (empty($encodedData)) {
            return false;
        }

        try {
            // Limpiar y validar base64
            $encodedData = str_replace([" ", "-", "_"], "+", $encodedData);
            $padding = strlen($encodedData) % 4;
            if ($padding) {
                $encodedData .= str_repeat('=', 4 - $padding);
            }
            
            if (!preg_match('/^[a-zA-Z0-9\/\r\n+]*={0,2}$/', $encodedData)) {
                return false;
            }

            // Decodificar base64
            $decoded = base64_decode($encodedData, true);
            if ($decoded === false) {
                return false;
            }
            
            // Desencriptar
            $iv = MP4_ENCRYPTION_IV;
            $decryption = openssl_decrypt(
                $decoded,
                'AES-256-CBC',
                MP4_ENCRYPTION_KEY,
                OPENSSL_RAW_DATA,
                $iv
            );
            
            if ($decryption === false) {
                return false;
            }
            
            // Separar payload y HMAC
            $parts = explode('|', $decryption);
            if (count($parts) !== 2) {
                return false;
            }
            
            list($payload, $receivedHmac) = $parts;
            
            // Verificar HMAC
            $expectedHmac = hash_hmac('sha256', $payload, MP4_HMAC_KEY);
            if (!hash_equals($expectedHmac, $receivedHmac)) {
                error_log("HMAC inválido - posible manipulación de datos");
                return false;
            }
            
            // Decodificar JSON
            $data = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
            
            // Validar estructura
            if (!isset($data['data'], $data['timestamp'], $data['nonce'])) {
                return false;
            }
            
            // Verificar expiración (más permisivo temporalmente)
            if (time() - $data['timestamp'] > $maxAge) {
                error_log("Token expirado - edad: " . (time() - $data['timestamp']) . " segundos");
                // return false; // COMENTADO TEMPORALMENTE PARA DEBUG
            }
            
            return $data;
            
        } catch (Exception $e) {
            error_log("Error en decodeSecure: " . $e->getMessage());
            return false;
        }
    }
}

/**
 * Funciones de compatibilidad con sistema anterior
 */
if (!function_exists('encode')) {
    function encode(string $data): string|false {
        return encodeSecure($data);
    }
}

if (!function_exists('decode')) {
    function decode(string $data): string|false {
        $result = decodeSecure($data);
        return $result === false ? false : $result['data'];
    }
}

/**
 * Rate limiting mejorado y más permisivo
 */
if (!function_exists('checkRateLimit')) {
    function checkRateLimit(string $identifier = null, int $limit = MP4_MAX_REQUESTS_PER_IP): bool {
        // TEMPORALMENTE DESHABILITADO PARA DEBUG
        return true;
        
        /*
        try {
            $identifier = $identifier ?: getClientIP();
            
            // Directorio temporal seguro
            $tempDir = sys_get_temp_dir();
            if (!is_writable($tempDir)) {
                $tempDir = __DIR__ . '/../tmp';
                if (!is_dir($tempDir)) {
                    @mkdir($tempDir, 0755, true);
                }
            }
            
            $cacheFile = $tempDir . '/mp4_rate_' . hash('sha256', $identifier);
            
            $now = time();
            $requests = [];
            
            if (file_exists($cacheFile) && is_readable($cacheFile)) {
                $data = file_get_contents($cacheFile);
                $requests = json_decode($data, true) ?: [];
            }
            
            // Limpiar solicitudes antiguas (más de 1 hora)
            $requests = array_filter($requests, function($time) use ($now) {
                return ($now - $time) < 3600;
            });
            
            if (count($requests) >= $limit) {
                error_log("Rate limit excedido para: $identifier (" . count($requests) . "/$limit)");
                return false;
            }
            
            $requests[] = $now;
            @file_put_contents($cacheFile, json_encode($requests), LOCK_EX);
            
            return true;
            
        } catch (Exception $e) {
            error_log("Error en checkRateLimit: " . $e->getMessage());
            return true; // En caso de error, permitir acceso
        }
        */
    }
}

/**
 * Obtener IP del cliente de forma segura
 */
if (!function_exists('getClientIP')) {
    function getClientIP(): string {
        $headers = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_X_FORWARDED_FOR',      // Proxy estándar
            'HTTP_X_REAL_IP',            // Nginx
            'HTTP_CLIENT_IP'             // Proxy
        ];
        
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                $ip = trim($ips[0]);
                
                // Validar que sea una IP válida y pública
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}

/**
 * Sanitización mejorada de entrada
 */
if (!function_exists('sanitizeInput')) {
    function sanitizeInput(mixed $input): string {
        if (is_array($input)) {
            return '';
        }
        
        $input = (string)$input;
        
        // Normalizar encoding
        if (function_exists('mb_check_encoding') && !mb_check_encoding($input, 'UTF-8')) {
            $input = mb_convert_encoding($input, 'UTF-8', 'auto');
        }
        
        // Remover caracteres peligrosos
        $input = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $input);
        
        return htmlspecialchars(
            strip_tags(trim($input)),
            ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE,
            'UTF-8'
        );
    }
}

/**
 * Validación estricta de URLs
 */
if (!function_exists('validateUrl')) {
    function validateUrl(string $url): bool {
        if (empty($url) || strlen($url) > 2048) {
            return false;
        }
        
        // Limpiar URL
        $url = trim($url);
        
        // Validar formato
        $validated = filter_var($url, FILTER_VALIDATE_URL);
        if ($validated === false) {
            return false;
        }
        
        // Verificar protocolo
        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (!in_array($scheme, ['http', 'https'])) {
            return false;
        }
        
        return true;
    }
}

/**
 * Gestión de tokens CSRF
 */
if (!function_exists('generateCSRFToken')) {
    function generateCSRFToken(): string {
        try {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            if (!isset($_SESSION['csrf_token']) || 
                !isset($_SESSION['csrf_time']) || 
                (time() - $_SESSION['csrf_time']) > 3600) {
                
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                $_SESSION['csrf_time'] = time();
            }
            
            return $_SESSION['csrf_token'];
        } catch (Exception $e) {
            error_log("Error generando token CSRF: " . $e->getMessage());
            return 'fallback_token_' . time();
        }
    }
}

if (!function_exists('validateCSRFToken')) {
    function validateCSRFToken(string $token): bool {
        try {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            return isset($_SESSION['csrf_token']) &&
                   isset($_SESSION['csrf_time']) &&
                   (time() - $_SESSION['csrf_time']) <= 3600 &&
                   hash_equals($_SESSION['csrf_token'], $token);
        } catch (Exception $e) {
            error_log("Error validando token CSRF: " . $e->getMessage());
            return false;
        }
    }
}

/**
 * Log de seguridad
 */
if (!function_exists('logSecurityEvent')) {
    function logSecurityEvent(string $event, array $context = []): void {
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'event' => $event,
            'ip' => getClientIP(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            'context' => $context
        ];
        
        error_log("SECURITY_EVENT: " . json_encode($logData));
    }
}

// Establecer headers de seguridad automáticamente
setSecurityHeaders();

// Log de inicialización
error_log("MP4 Security System v" . MP4_VERSION . " iniciado - IP: " . getClientIP());
?>
