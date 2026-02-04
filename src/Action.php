<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

class MP4SecureAction {
    private array $errors = [];
    private array $data = [];
    private string $clientIP;

    public function __construct() {
        setSecurityHeaders();
        $this->clientIP = getClientIP();
    }

    public function process(): string {
        // Verificar método HTTP
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            logSecurityEvent("Invalid HTTP method", ['method' => $_SERVER['REQUEST_METHOD']]);
            return $this->error('Método no permitido', 405);
        }

        // Rate limiting
        if (!checkRateLimit($this->clientIP)) {
            logSecurityEvent("Rate limit exceeded", ['ip' => $this->clientIP]);
            return $this->error('Demasiadas solicitudes. Intente más tarde.', 429);
        }

        // Validar token CSRF
        $csrfToken = $_POST['csrf_token'] ?? '';
        if (!validateCSRFToken($csrfToken)) {
            logSecurityEvent("Invalid CSRF token", ['ip' => $this->clientIP]);
            return $this->error('Token de seguridad inválido', 403);
        }

        // Validar entrada
        if (!$this->validateInput()) {
            logSecurityEvent("Input validation failed", [
                'errors' => $this->errors,
                'ip' => $this->clientIP
            ]);
            return $this->error(implode(', ', $this->errors), 400);
        }

        // Procesar datos
        return $this->processSecureData();
    }

    private function validateInput(): bool {
        // Limpiar y validar URL del video
        $this->data['link'] = sanitizeInput($_POST['link'] ?? '');
        if (empty($this->data['link'])) {
            $this->errors[] = 'URL del video es requerida';
            return false;
        }

        if (!validateUrl($this->data['link'])) {
            $this->errors[] = 'URL del video inválida';
            return false;
        }

        // Verificar que la URL sea accesible (opcional pero recomendado)
        if (!$this->checkUrlAccessibility($this->data['link'])) {
            $this->errors[] = 'El video no está disponible o no es accesible';
            return false;
        }

        // Validar poster (opcional)
        $this->data['poster'] = sanitizeInput($_POST['poster'] ?? '');
        if (!empty($this->data['poster']) && !validateUrl($this->data['poster'])) {
            $this->errors[] = 'URL del poster inválida';
            return false;
        }

        // Validar subtítulos
        $this->data['subtitles'] = $this->processSubtitles();

        return true;
    }

    private function checkUrlAccessibility(string $url): bool {
        try {
            $context = stream_context_create([
                'http' => [
                    'method' => 'HEAD',
                    'timeout' => 10,
                    'header' => [
                        'User-Agent: Mozilla/5.0 (compatible; MP4SecurityBot/1.0)',
                        'Accept: video/mp4, video/*, */*'
                    ]
                ]
            ]);

            $headers = @get_headers($url, false, $context);
            
            if ($headers === false) {
                return false;
            }

            // Verificar código de respuesta
            $firstHeader = $headers[0] ?? '';
            $isSuccessful = (
                strpos($firstHeader, '200') !== false || 
                strpos($firstHeader, '206') !== false ||
                strpos($firstHeader, '302') !== false ||
                strpos($firstHeader, '301') !== false
            );

            return $isSuccessful;

        } catch (Exception $e) {
            error_log("Error verificando accesibilidad de URL: " . $e->getMessage());
            return true; // En caso de error, permitir continuar
        }
    }

    private function processSubtitles(): array {
        $subtitles = [];
        $subs = $_POST['sub'] ?? [];
        $labels = $_POST['label'] ?? [];

        if (!is_array($subs) || !is_array($labels)) {
            return [];
        }

        foreach ($subs as $key => $url) {
            $url = sanitizeInput($url);
            $label = sanitizeInput($labels[$key] ?? "Subtítulo " . ($key + 1));
            
            if (!empty($url)) {
                if (validateUrl($url)) {
                    $subtitles[$label] = $url;
                } else {
                    error_log("URL de subtítulo inválida ignorada: $url");
                }
            }
        }

        return $subtitles;
    }

    private function processSecureData(): string {
        try {
            // Crear estructura de datos del video
            $videoData = [
                'link' => $this->data['link'],
                'poster' => $this->data['poster'],
                'sub' => $this->data['subtitles'],
                'metadata' => [
                    'created_at' => time(),
                    'ip_hash' => hash('sha256', $this->clientIP . MP4_HMAC_KEY),
                    'user_agent_hash' => hash('sha256', ($_SERVER['HTTP_USER_AGENT'] ?? '') . MP4_HMAC_KEY),
                    'version' => MP4_VERSION
                ]
            ];

            // Convertir a JSON con validación
            $jsonData = json_encode($videoData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            
            if ($jsonData === false) {
                throw new Exception("Error generando JSON: " . json_last_error_msg());
            }

            // Encriptar con el sistema seguro
            $encoded = encodeSecure($jsonData);
            
            if ($encoded === false) {
                throw new Exception("Error en la encriptación segura");
            }

            // Verificación de integridad
            $verification = decodeSecure($encoded);
            if ($verification === false) {
                throw new Exception("Error en verificación de integridad");
            }

            // Verificar que los datos coinciden
            $verificationJson = json_decode($verification['data'], true);
            if (!$verificationJson || $verificationJson['link'] !== $this->data['link']) {
                throw new Exception("Error en verificación de datos");
            }

            // Log de éxito
            logSecurityEvent("URL encoded successfully", [
                'ip' => $this->clientIP,
                'video_domain' => parse_url($this->data['link'], PHP_URL_HOST),
                'has_poster' => !empty($this->data['poster']),
                'subtitle_count' => count($this->data['subtitles'])
            ]);

            return $encoded;

        } catch (JsonException $e) {
            error_log("Error JSON en Action: " . $e->getMessage());
            return $this->error('Error procesando datos JSON: ' . $e->getMessage());
        } catch (Exception $e) {
            error_log("Error general en Action: " . $e->getMessage());
            return $this->error('Error interno del servidor');
        }
    }

    private function error(string $message, int $code = 400): string {
        http_response_code($code);
        
        // Log del error sin información sensible
        error_log("MP4Action Error ($code): $message - IP: " . $this->clientIP);
        
        // Respuesta JSON estructurada
        try {
            return json_encode([
                'error' => $message,
                'code' => $code,
                'timestamp' => time()
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        } catch (JsonException $e) {
            // Fallback si falla la generación de JSON
            error_log("Error generando JSON de error: " . $e->getMessage());
            return '{"error":"Error interno del servidor","code":500,"timestamp":' . time() . '}';
        }
    }
}

// Punto de entrada principal
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = new MP4SecureAction();
        echo $action->process();
    } catch (Exception $e) {
        error_log("Error crítico en Action.php: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'error' => 'Error crítico del sistema',
            'code' => 500,
            'timestamp' => time()
        ]);
    }
} else {
    // Método no permitido
    http_response_code(405);
    header('Allow: POST');
    echo json_encode([
        'error' => 'Método no permitido. Use POST.',
        'code' => 405,
        'timestamp' => time()
    ]);
}
?>
