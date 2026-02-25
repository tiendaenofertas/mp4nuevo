<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

/* SISTEMA DE RECUPERACIÓN INTELIGENTE (MULTI-CIPHER & MULTI-KEY)
   Este archivo prueba automáticamente múltiples combinaciones de claves y algoritmos 
   hasta lograr abrir los enlaces antiguos.
*/

if (!function_exists('decodeLegacy')) {
    function decodeLegacy(string $pData): string|false {
        if (empty($pData)) return false;

        // 1. LISTA DE POSIBLES CLAVES (Añade aquí tu clave antigua si la recuerdas)
        $candidate_keys = [
            "xzorra_key_2025",        // Clave del login
            "mp4_secure_key_2025",    // Clave estándar
            "1234567891011121",       // Numérica común
            "xzorra_protection",      // Frase común
            "xcuca.net",              // Dominio como clave
            "xcuca_key",
            "admin",
            "123456"
        ];

        // 2. LISTA DE ALGORITMOS COMUNES
        // Muchos scripts viejos usan CBC por defecto, no CTR.
        $candidate_ciphers = [
            "AES-256-CTR",
            "AES-256-CBC",
            "AES-128-CTR",
            "AES-128-CBC",
            "BF-CBC" // Blowfish (muy antiguo)
        ];

        // 3. LISTA DE IVs (Vectores de Inicialización)
        $candidate_ivs = [
            "1234567891011121",       // IV estándar de 16 bytes
            "0000000000000000",       // IV de ceros
            str_repeat("\0", 16)      // IV nulo binario
        ];

        // Limpieza y decodificación Base64
        $pData = str_replace([" ", "-", "_"], "+", $pData);
        $padding = strlen($pData) % 4;
        if ($padding) {
            $pData .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode($pData, true);
        if ($decoded === false) return false;

        // --- FUERZA BRUTA INTELIGENTE ---
        foreach ($candidate_ciphers as $cipher) {
            foreach ($candidate_keys as $key) {
                foreach ($candidate_ivs as $iv) {
                    try {
                        // Ajustar longitud del IV según el cifrado si es necesario
                        $ivLen = openssl_cipher_iv_length($cipher);
                        $currentIv = substr($iv, 0, $ivLen);
                        
                        // Intentar desencriptar
                        $decryption = openssl_decrypt(
                            $decoded, 
                            $cipher, 
                            $key, 
                            OPENSSL_RAW_DATA, 
                            $currentIv
                        );
                        
                        if ($decryption !== false) {
                            // Limpiar basura binary
                            $clean_url = preg_replace('/[\x00-\x1F\x7F]/', '', $decryption);
                            $clean_url = trim($clean_url);

                            // VALIDACIÓN: ¿Parece una URL real?
                            if (strpos($clean_url, 'http://') === 0 || strpos($clean_url, 'https://') === 0) {
                                // ¡ÉXITO! Encontramos la combinación
                                return $clean_url;
                            }
                        }
                    } catch (Exception $e) {
                        continue;
                    }
                }
            }
        }

        return false; // Ninguna combinación funcionó
    }
}

// --- WRAPPERS PRINCIPALES ---

if (!function_exists('decode')) {
    function decode(string $pData): string|false {
        // 1. Intentar primero con el sistema NUEVO (Seguro)
        $result = decodeSecure($pData);
        if ($result !== false && isset($result['data'])) {
            return $result['data'];
        }
        
        // 2. Si falla, activar el escáner de legado
        return decodeLegacy($pData);
    }
}

if (!function_exists('encode')) {
    function encode(string $pData): string|false {
        return encodeSecure($pData);
    }
}

// --- UTILIDADES ---

if (!function_exists('sanitizeInput')) {
    function sanitizeInput(mixed $input): string {
        if (is_array($input)) return '';
        $input = (string)$input;
        if (!mb_check_encoding($input, 'UTF-8')) {
            $input = mb_convert_encoding($input, 'UTF-8', 'auto');
        }
        return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}

if (!function_exists('cleanJsonString')) {
    function cleanJsonString(string $jsonString): string {
        $jsonString = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $jsonString);
        return str_replace(['"', "'"], ['"', "'"], $jsonString);
    }
}

if (!function_exists('safeJsonEncode')) {
    function safeJsonEncode(array $data): string|false {
        try {
            array_walk_recursive($data, function(&$item) {
                if (is_string($item) && !mb_check_encoding($item, 'UTF-8')) {
                    $item = mb_convert_encoding($item, 'UTF-8', 'auto');
                }
            });
            return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
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
?>
