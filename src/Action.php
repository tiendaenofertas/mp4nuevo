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
            return $this->error('Datos de entrada inválidos');
        }

        return $this->processData();
    }

    private function validateInput(): bool {
        $this->data['link'] = sanitizeInput($_POST['link'] ?? '');
        $this->data['poster'] = sanitizeInput($_POST['poster'] ?? '');
        
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

        $encoded = encode(json_encode($result, JSON_THROW_ON_ERROR));
        
        if ($encoded === false) {
            return $this->error('Error en la encriptación');
        }

        return $encoded;
    }

    private function processSubtitles(): array {
        $subtitles = [];
        $subs = $_POST['sub'] ?? [];
        $labels = $_POST['label'] ?? [];

        if (!is_array($subs) || !is_array($labels)) {
            return ['English' => ''];
        }

        foreach ($subs as $key => $value) {
            $value = sanitizeInput($value);
            $label = sanitizeInput($labels[$key] ?? 'Unknown');
            
            if (!empty($value) && validateUrl($value)) {
                $subtitles[$label] = $value;
            }
        }

        return empty($subtitles) ? ['English' => ''] : $subtitles;
    }

    private function error(string $message): string {
        http_response_code(400);
        return json_encode(['error' => $message], JSON_THROW_ON_ERROR);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = new MP4Action();
    echo $action->process();
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
}
?>