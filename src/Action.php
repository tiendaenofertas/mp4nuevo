<?php
declare(strict_types=1);

/**
 * MP4 Security System - Lógica de Procesamiento Segura
 * Maneja la validación, generación y cifrado de datos de video.
 */

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
        // 1. Verificar método HTTP
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            logSecurityEvent("Método HTTP inválido", ['method' => $_SERVER['REQUEST_METHOD']]);
            return $this->error('Método no permitido', 405);
        }

        // 2. Rate limiting basado en la IP del cliente
        if (!checkRateLimit($this->clientIP)) {
            logSecurityEvent("Límite de peticiones excedido", ['ip' => $this->clientIP]);
            return $this->error('Demasiadas solicitudes. Intente más tarde.', 429);
        }

        // 3. Validar token CSRF para prevenir ataques de terceros
        $csrfToken = $_POST['csrf_token'] ?? '';
        if (!validateCSRFToken($csrfToken)) {
            logSecurityEvent("Token CSRF inválido", ['ip' => $this->clientIP]);
            return $this->error('Sesión de seguridad inválida o expirada', 403);
        }

        // 4. Validar y sanear entradas
        if (!$this->validateInput()) {
            return $this->error(implode(', ', $this->errors), 400);
        }

        // 5. Procesar y cifrar datos
        return $this->processSecureData();
    }

    private function validateInput(): bool {
        // Limpiar y validar URL del video (Campo obligatorio)
        $this->data['link'] = trim($_POST['link'] ?? '');
        if (empty($this->data['link'])) {
            $this->errors[] = 'La URL del video es obligatoria';
            return false;
        }

        if (!validateUrl($this->data['link'])) {
            $this->errors[] = 'La URL del video proporcionada no es válida';
            return false;
        }

        // Verificar disponibilidad de la URL
        if (!$this->checkUrlAccessibility($this->data['link'])) {
            $this->errors[] = 'El video no es accesible o el servidor de origen denegó la conexión';
            return false;
        }

        // Validar poster (opcional)
        $this->data['poster'] = sanitizeInput($_POST['poster'] ?? '');
        if (!empty($this->data['poster']) && !validateUrl($this->data['poster'])) {
            $this->errors[] = 'La URL de la imagen de portada es inválida';
            return false;
        }

        // Procesar subtítulos de forma estructurada
        $this->data['subtitles'] = $this->processSubtitles();

        return empty($this->errors);
    }

    private function checkUrlAccessibility(string $url): bool {
        try {
            $context = stream_context_create([
                'http' => [
                    'method' => 'HEAD',
                    'timeout' => 8,
                    'header' => [
                        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) MP4Security/1.0',
                        'Accept: */*'
                    ],
                    'follow_location' => 1,
                    'max_redirects' => 3
                ]
            ]);

            $headers = @get_headers($url, 1, $context);
            if ($headers === false) return false;

            $status = $headers[0] ?? '';
            return (str_contains($status, '200') || str_contains($status, '206') || str_contains($status, '302'));

        } catch (Exception $e) {
            return true; // En caso de fallo del check, permitimos para no bloquear legítimos
        }
    }

    private function processSubtitles(): array {
        $subtitles = [];
        $subs = $_POST['sub'] ?? [];
        $labels = $_POST['label'] ?? [];

        if (!is_array($subs)) return [];

        foreach ($subs as $key => $url) {
            $url = trim((string)$url);
            if (!empty($url) && validateUrl($url)) {
                $label = sanitizeInput($labels[$key] ?? "Opcional " . ($key + 1));
                $subtitles[$label] = $url;
            }
        }
        return $subtitles;
    }

    private function processSecureData(): string {
        try {
            // Construcción del payload con metadatos de seguridad
            $videoData = [
                'link' => $this->data['link'],
                'poster' => $this->data['poster'],
                'sub' => $this->data['subtitles'],
                'metadata' => [
                    'ts' => time(),
                    'v' => MP4_VERSION,
                    'hid' => hash('sha256', $this->clientIP . MP4_HMAC_KEY)
                ]
            ];

            $jsonData = json_encode($videoData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

            // Cifrado utilizando el sistema compatible con IV estático
            $encoded = encodeSecure($jsonData);
            
            if ($encoded === false) {
                throw new Exception("Fallo crítico en el motor de cifrado");
            }

            // Verificación inmediata de integridad del token generado
            $check = decodeSecure($encoded);
            if ($check === false) {
                throw new Exception("Error de consistencia: El token generado no pudo ser validado");
            }

            logSecurityEvent("Enlace generado con éxito", ['domain' => parse_url($this->data['link'], PHP_URL_HOST)]);

            header('Content-Type: application/json');
            return json_encode(['status' => 'success', 'token' => $encoded]);

        } catch (Exception $e) {
            logSecurityEvent("Error en generación segura", ['msg' => $e->getMessage()]);
            return $this->error('Error interno al procesar la seguridad del enlace');
        }
    }

    private function error(string $message, int $code = 400): string {
        http_response_code($code);
        header('Content-Type: application/json');
        
        return json_encode([
            'status' => 'error',
            'message' => $message,
            'code' => $code,
            'timestamp' => time()
        ], JSON_UNESCAPED_UNICODE);
    }
}

// Punto de ejecución
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = new MP4SecureAction();
    echo $action->process();
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Acceso no autorizado']);
}
