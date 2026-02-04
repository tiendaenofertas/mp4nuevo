<?php
declare(strict_types=1);

require_once __DIR__ . "/config/config.php";

class MP4StreamHandler {
    private string $clientIP;

    public function __construct() {
        $this->clientIP = getClientIP();
        
        // Headers básicos
        header('Accept-Ranges: bytes');
        header('Cache-Control: public, max-age=3600');
    }

    public function handleRequest(): void {
        // Validar método
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            $this->error405();
            return;
        }

        // Rate limiting
        if (!checkRateLimit($this->clientIP, 20)) {
            logSecurityEvent("Stream rate limit exceeded", ['ip' => $this->clientIP]);
            $this->error429();
            return;
        }

        // Validar referer
        if (!$this->validateStreamReferer()) {
            logSecurityEvent("Invalid referer for stream", [
                'ip' => $this->clientIP,
                'referer' => $_SERVER['HTTP_REFERER'] ?? 'none'
            ]);
            $this->error403();
            return;
        }

        // Obtener token
        $token = sanitizeInput($_GET['token'] ?? '');
        
        if (empty($token)) {
            logSecurityEvent("Empty token in stream", ['ip' => $this->clientIP]);
            $this->error400();
            return;
        }

        // Validar token y obtener URL
        $videoUrl = $this->getVideoUrlFromToken($token);
        
        if ($videoUrl === false) {
            logSecurityEvent("Invalid token in stream", [
                'ip' => $this->clientIP,
                'token_length' => strlen($token)
            ]);
            $this->error401();
            return;
        }

        // Limpiar token usado
        $this->cleanupToken($token);

        // Log de acceso exitoso
        logSecurityEvent("Stream access successful", [
            'ip' => $this->clientIP,
            'target_domain' => parse_url($videoUrl, PHP_URL_HOST)
        ]);

        // Redirigir al video real de forma segura
        $this->streamVideo($videoUrl);
    }

    private function validateStreamReferer(): bool {
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        
        if (empty($referer)) {
            return false;
        }
        
        $refererHost = parse_url($referer, PHP_URL_HOST);
        $serverName = $_SERVER['SERVER_NAME'] ?? '';
        
        // Debe venir del mismo servidor
        return $refererHost === $serverName || 
               in_array($refererHost, ['localhost', '127.0.0.1']);
    }

    private function getVideoUrlFromToken(string $token): string|false {
        try {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            // Verificar en sesión
            if (!isset($_SESSION['video_tokens'][$token])) {
                return false;
            }
            
            $tokenData = $_SESSION['video_tokens'][$token];
            
            // Verificar expiración (30 minutos)
            if (time() - $tokenData['created'] > 1800) {
                unset($_SESSION['video_tokens'][$token]);
                return false;
            }
            
            // Verificar IP
            if ($tokenData['ip'] !== $this->clientIP) {
                return false;
            }
            
            return $tokenData['url'];
            
        } catch (Exception $e) {
            error_log("Error getting video URL from token: " . $e->getMessage());
            return false;
        }
    }

    private function cleanupToken(string $token): void {
        try {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            // Limpiar token usado
            unset($_SESSION['video_tokens'][$token]);
            
            // Limpiar tokens expirados
            if (isset($_SESSION['video_tokens'])) {
                $now = time();
                foreach ($_SESSION['video_tokens'] as $key => $data) {
                    if ($now - $data['created'] > 1800) {
                        unset($_SESSION['video_tokens'][$key]);
                    }
                }
            }
            
        } catch (Exception $e) {
            error_log("Error cleaning up token: " . $e->getMessage());
        }
    }

    private function streamVideo(string $videoUrl): void {
        try {
            // Verificar que la URL sea válida
            if (!validateUrl($videoUrl)) {
                $this->error400();
                return;
            }

            // Para evitar problemas de CORS, hacer una redirección 302
            // Esto permite que el navegador maneje el video directamente
            header('Location: ' . $videoUrl, true, 302);
            exit;
            
        } catch (Exception $e) {
            error_log("Error in streamVideo: " . $e->getMessage());
            $this->error500();
        }
    }

    private function error400(): void {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Solicitud inválida']);
        exit;
    }

    private function error401(): void {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Token inválido o expirado']);
        exit;
    }

    private function error403(): void {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Acceso denegado']);
        exit;
    }

    private function error405(): void {
        http_response_code(405);
        header('Allow: GET');
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Método no permitido']);
        exit;
    }

    private function error429(): void {
        http_response_code(429);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Demasiadas solicitudes']);
        exit;
    }

    private function error500(): void {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Error interno del servidor']);
        exit;
    }
}

// Iniciar el manejador
$handler = new MP4StreamHandler();
$handler->handleRequest();
?>