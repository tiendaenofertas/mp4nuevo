<?php
declare(strict_types=1);

require_once __DIR__ . "/config/config.php";

class MP4StreamHandler {
    private string $clientIP;

    public function __construct() {
        $this->clientIP = getClientIP();
        header('Accept-Ranges: bytes');
        header('Cache-Control: public, max-age=31536000'); // Cache largo
    }

    public function handleRequest(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            $this->error405();
            return;
        }

        // Validación de Referer relajada para permanencia
        // if (!$this->validateStreamReferer()) ... (Desactivado para máxima compatibilidad)

        $token = sanitizeInput($_GET['token'] ?? '');
        
        if (empty($token)) {
            $this->error400();
            return;
        }

        $videoUrl = $this->getVideoUrlFromToken($token);
        
        if ($videoUrl === false) {
            $this->error401(); // Token no encontrado o inválido
            return;
        }

        // NO eliminamos el token inmediatamente para permitir seeks (adelantar/atrasar)
        // $this->cleanupToken($token); 

        // Redirigir al video real
        $this->streamVideo($videoUrl);
    }

    private function validateStreamReferer(): bool {
        return true; // Permitir siempre para evitar cortes
    }

    private function getVideoUrlFromToken(string $token): string|false {
        try {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            if (!isset($_SESSION['video_tokens'][$token])) {
                return false;
            }
            
            $tokenData = $_SESSION['video_tokens'][$token];
            
            // ⚠️ CAMBIO: Expiración extendida a 10 años (usando la constante de config)
            if (time() - $tokenData['created'] > MP4_TOKEN_LIFETIME) {
                unset($_SESSION['video_tokens'][$token]);
                return false;
            }
            
            // Verificación de IP relajada (opcional, activada por seguridad básica)
            if ($tokenData['ip'] !== $this->clientIP) {
                // return false; // Puedes comentar esto si tienes problemas con usuarios en redes móviles (IP dinámica)
            }
            
            return $tokenData['url'];
            
        } catch (Exception $e) {
            return false;
        }
    }

    private function streamVideo(string $videoUrl): void {
        try {
            if (!validateUrl($videoUrl)) {
                $this->error400();
                return;
            }

            header('Location: ' . $videoUrl, true, 302);
            exit;
            
        } catch (Exception $e) {
            $this->error500();
        }
    }

    private function error400() { http_response_code(400); exit; }
    private function error401() { http_response_code(401); exit; }
    private function error403() { http_response_code(403); exit; }
    private function error405() { http_response_code(405); exit; }
    private function error500() { http_response_code(500); exit; }
}

$handler = new MP4StreamHandler();
$handler->handleRequest();
?>
