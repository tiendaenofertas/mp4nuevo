<?php
declare(strict_types=1);

// Configuración de errores
error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Configuración de sesión
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure', '1');
ini_set('session.use_strict_mode', '1');

// Intentar cargar library.php de forma segura
$libraryPath = __DIR__ . '/library.php';
if (file_exists($libraryPath)) {
    try {
        require_once $libraryPath;
    } catch (Exception $e) {
        error_log("Error cargando library.php: " . $e->getMessage());
    }
}

// Constantes del sistema
define('MP4_VERSION', '2.0');
define('MP4_ENCRYPTION_KEY', 'apicodesdotcom_v2_2025');
define('MP4_ENCRYPTION_IV', '1234567890123456');
define('MP4_ALLOWED_DOMAINS', [
    'xzorra.net',
    'localhost',
    '127.0.0.1'
]);

/**
 * Establece headers de seguridad
 */
function setSecurityHeaders(): void {
    try {
        if (!headers_sent()) {
            header('X-Content-Type-Options: nosniff');
            header('X-Frame-Options: SAMEORIGIN');
            header('X-XSS-Protection: 1; mode=block');
            header('Referrer-Policy: strict-origin-when-cross-origin');
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');
        }
    } catch (Exception $e) {
        error_log("Error estableciendo headers de seguridad: " . $e->getMessage());
    }
}

/**
 * Valida el referer contra dominios permitidos
 */
function validateReferer(): bool {
    try {
        // En desarrollo, permitir acceso directo
        if (!isset($_SERVER['HTTP_REFERER']) || empty($_SERVER['HTTP_REFERER'])) {
            // Permitir acceso directo en desarrollo
            return true;
        }
        
        $referer = $_SERVER['HTTP_REFERER'];
        
        foreach (MP4_ALLOWED_DOMAINS as $domain) {
            if (strpos($referer, $domain) !== false) {
                return true;
            }
        }
        
        // Log del referer no válido para debug
        error_log("Referer no válido: " . $referer);
        
        return false;
    } catch (Exception $e) {
        error_log("Error validando referer: " . $e->getMessage());
        return true; // En caso de error, permitir acceso
    }
}

/**
 * Función de encriptación mejorada
 */
if (!function_exists('encode')) {
    function encode(string $pData): string|false {
        if (empty($pData)) {
            return false;
        }

        try {
            $encryption_key = MP4_ENCRYPTION_KEY;
            $encryption_iv = MP4_ENCRYPTION_IV;
            $ciphering = "AES-256-CTR";

            $encryption = openssl_encrypt(
                $pData, 
                $ciphering, 
                $encryption_key, 
                OPENSSL_RAW_DATA, 
                $encryption_iv
            );
            
            if ($encryption === false) {
                error_log("Error en openssl_encrypt");
                return false;
            }
            
            $encoded = base64_encode($encryption);
            if ($encoded === false) {
                error_log("Error en base64_encode");
                return false;
            }
            
            return $encoded;
            
        } catch (Exception $e) {
            error_log("Error en encode(): " . $e->getMessage());
            return false;
        }
    }
}

/**
 * Función de desencriptación mejorada
 */
if (!function_exists('decode')) {
    function decode(string $pData): string|false {
        if (empty($pData)) {
            return false;
        }

        try {
            $encryption_key = MP4_ENCRYPTION_KEY;
            $decryption_iv = MP4_ENCRYPTION_IV;
            $ciphering = "AES-256-CTR";
            
            // Limpiar caracteres que pueden causar problemas
            $pData = str_replace([" ", "-", "_"], "+", $pData);
            
            // Validar que sea base64 válido
            if (!preg_match('/^[a-zA-Z0-9\/\r\n+]*={0,2}$/', $pData)) {
                error_log("Datos no son base64 válido: " . substr($pData, 0, 50));
                return false;
            }

            $decoded = base64_decode($pData, true);
            if ($decoded === false) {
                error_log("Error en base64_decode");
                return false;
            }
            
            $decryption = openssl_decrypt(
                $decoded, 
                $ciphering, 
                $encryption_key, 
                OPENSSL_RAW_DATA, 
                $decryption_iv
            );
            
            if ($decryption === false) {
                error_log("Error en openssl_decrypt");
                return false;
            }
            
            return $decryption;
            
        } catch (Exception $e) {
            error_log("Error en decode(): " . $e->getMessage());
            return false;
        }
    }
}

/**
 * Sanitizar entrada de datos
 */
if (!function_exists('sanitizeInput')) {
    function sanitizeInput(mixed $input): string {
        if (is_array($input)) {
            return '';
        }
        
        return htmlspecialchars(
            strip_tags(trim((string)$input)), 
            ENT_QUOTES | ENT_HTML5, 
            'UTF-8'
        );
    }
}

/**
 * Validar URL
 */
if (!function_exists('validateUrl')) {
    function validateUrl(string $url): bool {
        if (empty($url)) {
            return false;
        }
        
        $validated = filter_var($url, FILTER_VALIDATE_URL);
        if ($validated === false) {
            return false;
        }
        
        return (strpos($url, 'http://') === 0 || strpos($url, 'https://') === 0);
    }
}

/**
 * Generar token CSRF
 */
if (!function_exists('generateCSRFToken')) {
    function generateCSRFToken(): string {
        try {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            if (!isset($_SESSION['csrf_token'])) {
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            }
            
            return $_SESSION['csrf_token'];
        } catch (Exception $e) {
            error_log("Error generando token CSRF: " . $e->getMessage());
            return 'fallback_token_' . time();
        }
    }
}

/**
 * Validar token CSRF
 */
if (!function_exists('validateCSRFToken')) {
    function validateCSRFToken(string $token): bool {
        try {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            return isset($_SESSION['csrf_token']) 
                && hash_equals($_SESSION['csrf_token'], $token);
        } catch (Exception $e) {
            error_log("Error validando token CSRF: " . $e->getMessage());
            return false;
        }
    }
}

/**
 * Función de debug para log
 */
if (!function_exists('debugLog')) {
    function debugLog(string $message): void {
        error_log("[MP4_DEBUG] " . $message);
    }
}

/**
 * Verificar si estamos en modo debug
 */
if (!function_exists('isDebugMode')) {
    function isDebugMode(): bool {
        return isset($_GET['debug']) || 
               (defined('MP4_DEBUG') && MP4_DEBUG === true) ||
               strpos($_SERVER['REQUEST_URI'] ?? '', 'debug.php') !== false;
    }
}

// Establecer headers de seguridad automáticamente
if (!headers_sent()) {
    setSecurityHeaders();
}

// Log de carga exitosa
if (function_exists('debugLog')) {
    debugLog("Config cargado exitosamente - Versión " . MP4_VERSION);
}
?>