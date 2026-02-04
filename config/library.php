<?php
declare(strict_types=1);

/**
 * MP4 Security System - Librería de Funciones Corregida
 * Sincronizada con config.php para mantener compatibilidad de enlaces.
 */

if (!function_exists('decode')) {
    function decode(string $pData): string|false {
        if (empty($pData)) {
            return false;
        }

        // Sincronizado con config.php para evitar errores de desencriptación
        $encryption_key = MP4_ENCRYPTION_KEY;
        $decryption_iv = MP4_ENCRYPTION_IV;
        $ciphering = "AES-256-CBC";
        
        // Normalización estricta para enlaces provenientes de URLs
        $pData = trim($pData);
        $pData = str_replace([" ", "-", "_"], ["+", "+", "/"], $pData);
        
        // Corrección de padding base64
        $padding = strlen($pData) % 4;
        if ($padding) {
            $pData .= str_repeat('=', 4 - $padding);
        }
        
        try {
            $decoded = base64_decode($pData, true);
            if ($decoded === false) {
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
                return false;
            }
            
            // Garantizar integridad de caracteres especiales
            if (!mb_check_encoding($decryption, 'UTF-8')) {
                $decryption = mb_convert_encoding($decryption, 'UTF-8', 'auto');
            }
            
            return $decryption;
            
        } catch (Exception $e) {
            error_log("Error en library->decode: " . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('encode')) {
    function encode(string $pData): string|false {
        if (empty($pData)) {
            return false;
        }

        $encryption_key = MP4_ENCRYPTION_KEY;
        $encryption_iv = MP4_ENCRYPTION_IV;
        $ciphering = "AES-256-CBC";

        try {
            if (!mb_check_encoding($pData, 'UTF-8')) {
                $pData = mb_convert_encoding($pData, 'UTF-8', 'auto');
            }
            
            $encryption = openssl_encrypt(
                $pData, 
                $ciphering, 
                $encryption_key, 
                OPENSSL_RAW_DATA, 
                $encryption_iv
            );
            
            if ($encryption === false) {
                return false;
            }
            
            return base64_encode($encryption);
            
        } catch (Exception $e) {
            error_log("Error en library->encode: " . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('sanitizeInput')) {
    function sanitizeInput(mixed $input): string {
        if (is_array($input)) return '';
        
        $input = (string)$input;
        if (!mb_check_encoding($input, 'UTF-8')) {
            $input = mb_convert_encoding($input, 'UTF-8', 'auto');
        }
        
        return htmlspecialchars(
            strip_tags(trim($input)), 
            ENT_QUOTES | ENT_HTML5, 
            'UTF-8'
        );
    }
}

if (!function_exists('validateUrl')) {
    function validateUrl(string $url): bool {
        if (empty($url)) return false;
        $url = trim($url);
        $validated = filter_var($url, FILTER_VALIDATE_URL);
        if ($validated === false) return false;
        
        return (str_starts_with($url, 'http://') || str_starts_with($url, 'https://'));
    }
}

if (!function_exists('generateCSRFToken')) {
    function generateCSRFToken(): string {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('validateCSRFToken')) {
    function validateCSRFToken(string $token): bool {
        if (session_status() === PHP_SESSION_NONE) session_start();
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}

if (!function_exists('cleanJsonString')) {
    function cleanJsonString(string $jsonString): string {
        // Eliminar caracteres de control (0-31) que rompen el JSON
        return preg_replace('/[\x00-\x1F\x7F]/', '', $jsonString);
    }
}

if (!function_exists('safeJsonEncode')) {
    function safeJsonEncode(array $data): string|false {
        try {
            array_walk_recursive($data, function(&$item) {
                if (is_string($item)) {
                    if (!mb_check_encoding($item, 'UTF-8')) {
                        $item = mb_convert_encoding($item, 'UTF-8', 'auto');
                    }
                    $item = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $item);
                }
            });
            
            $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($json === false) {
                error_log("JSON Encode Error: " . json_last_error_msg());
                return false;
            }
            return $json;
        } catch (Exception $e) {
            return false;
        }
    }
}

if (!function_exists('safeJsonDecode')) {
    function safeJsonDecode(string $jsonString): array|false {
        try {
            $jsonString = cleanJsonString($jsonString);
            $data = json_decode($jsonString, true, 512, JSON_THROW_ON_ERROR);
            return is_array($data) ? $data : false;
        } catch (JsonException $e) {
            return false;
        }
    }
}
