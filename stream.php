<?php
declare(strict_types=1);

/**
 * MP4 Security System - Manejador de Streaming Corregido
 * Gestiona la entrega segura del video mediante tokens de sesión.
 */

require_once __DIR__ . "/config/config.php";

class MP4StreamHandler {
    private string $clientIP;

    public function __construct() {
        // Inicializar headers de seguridad definidos en config.php
        setSecurityHeaders();
        
        $this->clientIP = getClientIP();
        
        // Headers específicos para streaming: evitar caché del token
        header('Accept-Ranges: bytes');
        header('Cache-Control: private, no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
    }

    public function handleRequest(): void {
        // 1. Validar método HTTP
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            $this->error(405, 'Método no permitido');
            return;
        }

        // 2. Rate limiting específico para el flujo de video (30 peticiones por ventana)
        if (!checkRateLimit($this->clientIP, 30)) {
            logSecurityEvent("Límite de peticiones de stream excedido", ['ip' => $this->clientIP]);
            $this->error(429, 'Demasiadas solicitudes. Por favor, espere.');
            return;
        }

        // 3. Validar referer utilizando la lógica centralizada
        if (!$this->validateStreamReferer()) {
            logSecurityEvent("Referer inválido en stream", [
                'ip' => $this->clientIP,
                'referer' => $_SERVER['HTTP_REFERER'] ?? 'none'
            ]);
            $this->error(403, 'Acceso denegado: El dominio de origen no está autorizado.');
            return;
        }

        // 4. Obtener y sanear el token
        $token = sanitizeInput($_GET['token'] ?? '');
        
        if (empty($token)) {
            logSecurityEvent("Token vacío en petición de stream", ['ip' => $this->clientIP]);
            $this->error(400, 'Token de acceso requerido.');
            return;
        }

        // 5. Validar token y recuperar URL real del video de la sesión
        $videoUrl = $this->getVideoUrlFromToken($token);
        
        if ($videoUrl === false) {
            logSecurityEvent("Token inválido o expirado en stream", [
                'ip' => $this->clientIP,
                'token_prefix' => substr($token, 0, 8)
            ]);
            $this->error(401, 'El enlace de video ha expirado o no es válido para esta sesión.');
            return;
        }

        // 6. Limpiar token usado (Un solo uso por cada carga del reproductor)
        $this->cleanupToken($token);

        // 7. Log de acceso exitoso para auditoría
        logSecurityEvent("Acceso autorizado al stream", [
            'ip' => $this->clientIP,
            'target' => parse_url($videoUrl, PHP_URL_HOST)
        ]);

        // 8. Redirección final al video real
        $this->streamVideo($videoUrl);
    }

    /**
     * Valida que la petición provenga de un dominio autorizado.
     */
    private function validateStreamReferer(): bool {
        // Utilizamos la función global definida en config.php
        if (validateReferer()) {
            return true;
        }

        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        if (empty($referer)) return false;
        
        $refererHost = parse_url($referer, PHP_URL_HOST);
        $serverName = $_SERVER['SERVER_NAME'] ?? '';
        
        // Permitir explícitamente si viene del mismo servidor o entorno local
        return ($refererHost === $serverName || in_array($refererHost, ['localhost', '127.0.0.1']));
    }

    /**
     * Recupera la URL de la sesión y valida IP y tiempo de vida.
     */
    private function getVideoUrlFromToken(string $token): string|false {
        try {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            if (!isset($_SESSION['video_tokens'][$token])) {
                return false;
            }
            
            $tokenData = $_SESSION['video_tokens'][$token];
            
            // Validar expiración (basado en el tiempo de creación en embed.php)
            if ((time() - ($tokenData['created'] ?? 0)) > 1800) {
                unset($_SESSION['video_tokens'][$token]);
                return false;
            }
            
            // Validar vinculación con la IP actual para evitar el secuestro de tokens
            if (($tokenData['ip'] ?? '') !== $this->clientIP) {
                logSecurityEvent("Intento de uso de token desde IP distinta", [
                    'token_ip' => $tokenData['ip'],
                    'request_ip' => $this->clientIP
                ]);
                return false;
            }
            
            return $tokenData['url'] ?? false;
            
        } catch (Exception $e) {
            error_log("Error en validación de token de stream: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Elimina el token actual y realiza mantenimiento preventivo de la sesión.
     */
    private function cleanupToken(string $token): void {
        try {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            unset($_SESSION['video_tokens'][$token]);
            
            // Mantenimiento: Limpiar otros tokens de video que hayan expirado (evita hinchazón de sesión)
            if (isset($_SESSION['video_tokens']) && is_array($_SESSION['video_tokens'])) {
                $now = time();
                foreach ($_SESSION['video_tokens'] as $key => $data) {
                    if (($now - ($data['created'] ?? 0)) > 1800) {
                        unset($_SESSION['video_tokens'][$key]);
                    }
                }
            }
            
        } catch (Exception $e) {
            error_log("Error en limpieza de tokens de stream: " . $e->getMessage());
        }
    }

    /**
     * Ejecuta la redirección segura al video.
     */
    private function streamVideo(string $videoUrl): void {
        try {
            if (!validateUrl($videoUrl)) {
                $this->error(400, 'URL de video no válida.');
                return;
            }

            // Redirección 302 para delegar la carga al navegador de forma eficiente
            header('X-Protected-By: MP4Security-System');
            header('Location: ' . $videoUrl, true, 302);
            exit;
            
        } catch (Exception $e) {
            error_log("Error crítico en streamVideo: " . $e->getMessage());
            $this->error(500, 'Error interno al procesar el flujo de video.');
        }
    }

    /**
     * Manejo estandarizado de errores en formato JSON.
     */
    private function error(int $code, string $message): void {
        http_response_code($code);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'status' => 'error',
            'code' => $code,
            'message' => $message,
            'timestamp' => time()
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// Inicialización del manejador
try {
    $handler = new MP4StreamHandler();
    $handler->handleRequest();
} catch (Throwable $e) {
    error_log("Error fatal en stream.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error de ejecución interna']);
}
