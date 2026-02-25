<?php
declare(strict_types=1);

require_once __DIR__ . "/../config/config.php";

class MP4SecureStream {
    private string $clientIP;

    public function __construct() {
        // Limpiar buffers
        if (ob_get_level()) ob_end_clean();
        
        setSecurityHeaders();
        $this->clientIP = getClientIP();
        $this->handleCors();
    }
    
    private function handleCors(): void {
        // CORS Dinámico para permitir reproductores externos autorizados
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        $allowOrigin = 'null';
        
        if ($origin) {
            $originHost = parse_url($origin, PHP_URL_HOST);
            foreach (MP4_ALLOWED_DOMAINS as $domain) {
                if ($originHost === $domain || str_ends_with($originHost, ".$domain")) {
                    $allowOrigin = $origin;
                    break;
                }
            }
        }
        
        header("Access-Control-Allow-Origin: $allowOrigin");
        header("Access-Control-Allow-Methods: GET, HEAD, OPTIONS");
        header("Access-Control-Allow-Headers: Range, Content-Type, Authorization");
        header("Access-Control-Allow-Credentials: true");
    }

    public function process(): void {
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }

        if (!checkRateLimit($this->clientIP, 120)) { // Límite más alto al ser solo redirección
            $this->showError("Demasiadas solicitudes", 429);
            return;
        }

        if (!validateReferer()) {
            $this->showError("Acceso no autorizado", 403);
            return;
        }

        $data = sanitizeInput($_GET["data"] ?? "");
        if (empty($data)) {
            $this->showError("Datos requeridos", 400);
            return;
        }

        // Decodificar
        $decodedResult = decodeSecure($data);
        if ($decodedResult === false) {
            $this->showError("Token inválido o expirado", 401);
            return;
        }

        $url = $decodedResult['data'];

        if (!validateUrl($url)) {
            $this->showError("URL inválida", 400);
            return;
        }

        // REDIRECCIÓN SEGURA (NO PROXY)
        // Usamos 302 Found para evitar caché permanente de la redirección
        header("Location: " . $url, true, 302);
        exit;
    }

    private function showError(string $message, int $code = 400): void {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode(['error' => $message]);
        exit;
    }
}

$stream = new MP4SecureStream();
$stream->process();
?>
