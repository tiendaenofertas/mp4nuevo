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
        if ($url === false || !validateUrl($url)) {
            $this->showError("URL inválida");
            return;
        }

        header("Location: " . $url, true, 302);
        exit;
    }

    private function show404(): void {
        http_response_code(404);
        include __DIR__ . "/../errors/404.html";
    }

    private function showError(string $message): void {
        http_response_code(400);
        echo json_encode(["error" => $message]);
    }
}

$stream = new MP4Stream();
$stream->process();
?>