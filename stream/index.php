<?php
declare(strict_types=1);

require_once __DIR__ . "/../config/config.php";
require_once __DIR__ . "/../config/library.php"; // Necesario para legacy

class MP4SecureStream {
    private string $clientIP;

    public function __construct() {
        if (ob_get_level()) ob_end_clean();
        setSecurityHeaders();
        $this->clientIP = getClientIP();
        $this->handleCors();
    }
    
    private function handleCors(): void {
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
        header("Access-Control-Allow-Headers: Range, Content-Type");
        header("Access-Control-Allow-Credentials: true");
    }

    public function process(): void {
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
        // Rate limit amplio
        if (!checkRateLimit($this->clientIP, 300)) { $this->showError("Límite excedido", 429); return; }
        if (!validateReferer()) { $this->showError("No autorizado", 403); return; }

        $data = sanitizeInput($_GET["data"] ?? "");
        if (empty($data)) { $this->showError("Datos faltantes", 400); return; }

        // --- LÓGICA HÍBRIDA ---
        
        $url = "";
        
        // 1. Intentar token NUEVO
        $decodedResult = decodeSecure($data);
        if ($decodedResult !== false) {
            $url = $decodedResult['data'];
        } else {
            // 2. Intentar token ANTIGUO (Legacy)
            $legacyUrl = decodeLegacy($data);
            if ($legacyUrl !== false) {
                $url = $legacyUrl;
            }
        }

        // Si fallaron ambos o la URL no es válida
        if (empty($url) || !validateUrl($url)) {
            $this->showError("Enlace inválido o expirado", 400);
            return;
        }

        // REDIRECCIÓN DIRECTA (NO PROXY)
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
