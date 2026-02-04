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
        
        // Limpiar y normalizar los datos de entrada
        $pData = trim($pData);
        $pData = str_replace([" ", "-", "_"], "+", $pData);
        
        // Añadir padding si es necesario
        $padding = strlen($pData) % 4;
        if ($padding) {
            $pData .= str_repeat('=', 4 - $padding);
        }
        
        if (!preg_match('/^[a-zA-Z0-9\/\r\n+]*={0,2}$/', $pData)) {
            error_log("Formato base64 inválido");
            return false;
        }

        try {
            // Decodificar base64
            $decoded = base64_decode($pData, true);
            if ($decoded === false) {
                error_log("Error en base64_decode");
                return false;
            }
            
            // Desencriptar
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
            
            // Asegurar que es UTF-8 válido
            if (!mb_check_encoding($decryption, 'UTF-8')) {
                error_log("Resultado no es UTF-8 válido");
                // Intentar convertir a UTF-8
                $decryption = mb_convert_encoding($decryption, 'UTF-8', 'auto');
            }
            
            return $decryption;
            
        } catch (Exception $e) {
            error_log("Error en decode: " . $e->getMessage());
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
            // Asegurar que la entrada es UTF-8 válido
            if (!mb_check_encoding($pData, 'UTF-8')) {
                $pData = mb_convert_encoding($pData, 'UTF-8', 'auto');
            }
            
            // Encriptar
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
            
            // Codificar en base64
            $encoded = base64_encode($encryption);
            if ($encoded === false) {
                error_log("Error en base64_encode");
                return false;
            }
            
            return $encoded;
            
        } catch (Exception $e) {
            error_log("Error en encode: " . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('sanitizeInput')) {
    function sanitizeInput(mixed $input): string {
        if (is_array($input)) {
            return '';
        }
        
        $input = (string)$input;
        
        // Asegurar UTF-8 válido
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
        if (empty($url)) {
            return false;
        }
        
        // Limpiar URL de caracteres problemáticos
        $url = trim($url);
        
        $validated = filter_var($url, FILTER_VALIDATE_URL);
        if ($validated === false) {
            return false;
        }
        
        return (strpos($url, 'http://') === 0 || strpos($url, 'https://') === 0);
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

if (!function_exists('cleanJsonString')) {
    function cleanJsonString(string $jsonString): string {
        // Eliminar caracteres de control y caracteres no UTF-8
        $jsonString = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $jsonString);
        
        // Asegurar que las comillas están correctamente escapadas
        $jsonString = str_replace(['"', "'"], ['"', "'"], $jsonString);
        
        return $jsonString;
    }
}

if (!function_exists('safeJsonEncode')) {
    function safeJsonEncode(array $data): string|false {
        try {
            // Limpiar recursivamente todos los strings del array
            array_walk_recursive($data, function(&$item) {
                if (is_string($item)) {
                    // Asegurar UTF-8 válido
                    if (!mb_check_encoding($item, 'UTF-8')) {
                        $item = mb_convert_encoding($item, 'UTF-8', 'auto');
                    }
                    // Limpiar caracteres problemáticos
                    $item = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $item);
                }
            });
            
            $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            
            if ($json === false) {
                error_log("Error en json_encode: " . json_last_error_msg());
                return false;
            }
            
            return $json;
            
        } catch (Exception $e) {
            error_log("Error en safeJsonEncode: " . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('safeJsonDecode')) {
    function safeJsonDecode(string $jsonString): array|false {
        try {
            // Limpiar el string JSON
            $jsonString = cleanJsonString($jsonString);
            
            $data = json_decode($jsonString, true, 512, JSON_THROW_ON_ERROR);
            
            if (!is_array($data)) {
                error_log("JSON decodificado no es un array");
                return false;
            }
            
            return $data;
            
        } catch (JsonException $e) {
            error_log("Error en safeJsonDecode: " . $e->getMessage());
            error_log("JSON problemático: " . substr($jsonString, 0, 200));
            return false;
        }
    }
}
?>
