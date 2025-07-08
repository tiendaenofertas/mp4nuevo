<?php
declare(strict_types=1);

require_once __DIR__ . "/../config/config.php";

class MP4Stream {
    public function __construct() {
        setSecurityHeaders();
    }

    public function process(): void {
        if (!validateReferer()) {
            $this->show404();
            return;
        }

        $data = sanitizeInput($_GET["data"] ?? "");
        
        if (empty($data)) {
            $this->showError("Datos vacíos");
            return;
        }

        $url = decode($data);
        if ($url === false) {
            error_log("Error decodificando URL en stream: " . $data);
            $this->showError("Error de decodificación");
            return;
        }

        if (!validateUrl($url)) {
            error_log("URL inválida en stream: " . $url);
            $this->showError("URL inválida");
            return;
        }

        // Log para debug
        error_log("Redirigiendo a: " . $url);

        // Configurar headers para video streaming
        header("Content-Type: video/mp4");
        header("Accept-Ranges: bytes");
        header("Cache-Control: public, max-age=3600");
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
        header("Access-Control-Allow-Headers: Range, Content-Type");

        // Manejar requests OPTIONS para CORS
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }

        // Redirigir al video real
        header("Location: " . $url, true, 302);
        exit;
    }

    private function show404(): void {
        http_response_code(404);
        if (file_exists(__DIR__ . "/../errors/404.html")) {
            include __DIR__ . "/../errors/404.html";
        } else {
            echo "<h1>404 - No encontrado</h1>";
        }
    }

    private function showError(string $message): void {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(["error" => $message]);
    }
}

$stream = new MP4Stream();
$stream->process();
?>
