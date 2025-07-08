<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

class MP4Action {
    private array $errors = [];
    private array $data = [];

    public function __construct() {
        setSecurityHeaders();
    }

    public function process(): string {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return $this->error('Método no permitido');
        }

        $csrfToken = $_POST['csrf_token'] ?? '';
        if (!validateCSRFToken($csrfToken)) {
            return $this->error('Token de seguridad inválido');
        }

        if (!$this->validateInput()) {
            return $this->error(implode(', ', $this->errors));
        }

        return $this->processData();
    }

    private function validateInput(): bool {
        $this->data['link'] = sanitizeInput($_POST['link'] ?? '');
        $this->data['poster'] = sanitizeInput($_POST['poster'] ?? '');
        
        if (empty($this->data['link'])) {
            $this->errors[] = 'URL del video es requerida';
            return false;
        }

        if (!validateUrl($this->data['link'])) {
            $this->errors[] = 'URL del video inválida';
            return false;
        }

        if (!empty($this->data['poster']) && !validateUrl($this->data['poster'])) {
            $this->errors[] = 'URL del poster inválida';
            return false;
        }

        return true;
    }

    private function processData(): string {
        $result = [
            'link' => $this->data['link'],
            'poster' => $this->data['poster'],
            'sub' => $this->processSubtitles()
        ];

        // Debug: Log de datos antes de codificar
        error_log("Datos a procesar en Action: " . print_r($result, true));

        try {
            // Usar función segura si está disponible
            if (function_exists('safeJsonEncode')) {
                $jsonData = safeJsonEncode($result);
            } else {
                $jsonData = json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }
            
            if ($jsonData === false) {
                return $this->error('Error al generar JSON');
            }

            error_log("JSON generado: " . $jsonData);

            $encoded = encode($jsonData);
            
            if ($encoded === false) {
                return $this->error('Error en la encriptación');
            }

            // Verificación de integridad
            $testDecode = decode($encoded);
            if ($testDecode === false) {
                return $this->error('Error en verificación de encriptación');
            }

            // Verificar que el JSON decodificado es válido
            if (function_exists('safeJsonDecode')) {
                $testJson = safeJsonDecode($testDecode);
            } else {
                $testJson = json_decode($testDecode, true);
            }
            
            if ($testJson === false || $testJson === null) {
                error_log("Error en verificación JSON. Datos decodificados: " . $testDecode);
                return $this->error('Error en verificación de JSON');
            }

            error_log("Encriptación exitosa. Resultado: " . substr($encoded, 0, 50) . "...");

            return $encoded;

        } catch (JsonException $e) {
            error_log("Error JSON en Action.php: " . $e->getMessage());
            return $this->error('Error al procesar datos JSON: ' . $e->getMessage());
        } catch (Exception $e) {
            error_log("Error general en Action.php: " . $e->getMessage());
            return $this->error('Error interno del servidor');
        }
    }

    private function processSubtitles(): array {
        $subtitles = [];
        $subs = $_POST['sub'] ?? [];
        $labels = $_POST['label'] ?? [];

        if (!is_array($subs) || !is_array($labels)) {
            return [];
        }

        foreach ($subs as $key => $value) {
            $value = sanitizeInput($value);
            $label = sanitizeInput($labels[$key] ?? 'Subtítulo ' . ($key + 1));
            
            if (!empty($value) && validateUrl($value)) {
                $subtitles[$label] = $value;
            }
        }

        return $subtitles;
    }

    private function error(string $message): string {
        http_response_code(400);
        error_log("Error en MP4Action: " . $message);
        
        try {
            return json_encode(['error' => $message], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        } catch (JsonException $e) {
            error_log("Error al generar JSON de error: " . $e->getMessage());
            return '{"error":"Error interno del servidor"}';
        }
    }
}

// Procesar solicitud
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = new MP4Action();
        echo $action->process();
    } catch (Exception $e) {
        error_log("Error crítico en Action.php: " . $e->getMessage());
        http_response_code(500);
        echo '{"error":"Error interno del servidor"}';
    }
} else {
    http_response_code(405);
    echo '{"error":"Método no permitido"}';
}
?>
