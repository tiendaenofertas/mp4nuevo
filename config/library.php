<?php
declare(strict_types=1);

if (!function_exists('decode')) {
    function decode(string $pData): string|false {
        if (empty($pData)) {
            return false;
        }

        $encryption_key = MP4_ENCRYPTION_KEY;
        $decryption_iv = MP4_ENCRYPTION_IV;
        $ciphering = "AES-256-CTR";
        
        $pData = str_replace([" ", "-", "_"], "+", $pData);
        
        if (!preg_match('/^[a-zA-Z0-9\/\r\n+]*={0,2}$/', $pData)) {
            return false;
        }

        try {
            $decryption = openssl_decrypt(
                $pData, 
                $ciphering, 
                $encryption_key, 
                OPENSSL_RAW_DATA, 
                $decryption_iv
            );
            
            return $decryption !== false ? $decryption : false;
        } catch (Exception $e) {
            error_log("Decryption error: " . $e->getMessage());
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
        $ciphering = "AES-256-CTR";

        try {
            $encryption = openssl_encrypt(
                $pData, 
                $ciphering, 
                $encryption_key, 
                OPENSSL_RAW_DATA, 
                $encryption_iv
            );
            
            return $encryption !== false ? base64_encode($encryption) : false;
        } catch (Exception $e) {
            error_log("Encryption error: " . $e->getMessage());
            return false;
        }
    }
}

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

if (!function_exists('validateUrl')) {
    function validateUrl(string $url): bool {
        return filter_var($url, FILTER_VALIDATE_URL) !== false 
            && (str_starts_with($url, 'http://') || str_starts_with($url, 'https://'));
    }
}

if (!function_exists('generateCSRFToken')) {
    function generateCSRFToken(): string {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('validateCSRFToken')) {
    function validateCSRFToken(string $token): bool {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        return isset($_SESSION['csrf_token']) 
            && hash_equals($_SESSION['csrf_token'], $token);
    }
}
?>