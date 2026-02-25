<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

/* SISTEMA DE COMPATIBILIDAD INTELIGENTE (MULTI-KEY)
   Este archivo intentará abrir los enlaces antiguos probando varias claves posibles.
*/

if (!function_exists('decodeLegacy')) {
    function decodeLegacy(string $pData): string|false {
        if (empty($pData)) return false;

        // --- LISTA DE POSIBLES CLAVES ANTIGUAS ---
        // El sistema probará una por una hasta que funcione.
        $candidate_keys = [
            "xzorra_key_2025",        // La más probable basada en tu index.php
            "mp4_secure_key_2025",    // Clave por defecto común
            "mp4_secure_key_2024",    // Versión anterior
            "1234567890123456",       // Clave numérica estándar
            "secret_key",             // Genérica
            "xcuca_key_2025"          // Variante de tu dominio
        ];

        // --- LISTA DE POSIBLES IVs ANTIGUOS ---
        $candidate_ivs = [
            "1234567891011121",       // IV por defecto del script original (16 bytes)
            "0000000000000000"        // IV vacío/nulo
        ];

        // Preparar datos
        $pData = str_replace([" ", "-", "_"], "+", $pData);
        $padding = strlen($pData) % 4;
        if ($padding) {
            $pData .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode($pData, true);
        if ($decoded === false) return false;

        // INTENTAR DESENCRIPTAR CON CADA COMBINACIÓN
        foreach ($candidate_keys as $key) {
            foreach ($candidate_ivs as $iv) {
                try {
                    $decryption = openssl_decrypt(
                        $decoded, 
                        "AES-256-CTR", 
                        $key, 
                        OPENSSL_RAW_DATA, 
                        $iv
                    );
                    
                    if ($decryption !== false) {
                        // Limpiar resultado
                        $clean_url = preg_replace('/[\x00-\x1F\x7F]/', '', $decryption);
                        $clean_url = trim($clean_url);

                        // VERIFICACIÓN: ¿El resultado parece una URL válida?
                        // Si empieza por http o https, asumimos que la clave es CORRECTA.
                        if (strpos($clean_url, 'http://') === 0 || strpos($clean_url, 'https://') === 0) {
                            return $clean_url;
                        }
                    }
                } catch (Exception $e) {
                    continue; // Si falla, probar la siguiente
                }
            }
        }

        return false; // Ninguna clave funcionó
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
        
        // 2. Si falla, intentar con el sistema ANTIGUO (Legacy Multi-Key)
        return decodeLegacy($pData);
    }
}

if (!function_exists('encode')) {
    function encode(string $pData): string|false {
        // Siempre encriptar con el sistema NUEVO para máxima seguridad
        return encodeSecure($pData);
    }
}

// --- FUNCIONES DE UTILIDAD ---

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
