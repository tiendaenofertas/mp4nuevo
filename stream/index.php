<?php
declare(strict_types=1);

require_once __DIR__ . "/../config/config.php";

class MP4SecureStream {
    private string $clientIP;

    public function __construct() {
        setSecurityHeaders();
        $this->clientIP = getClientIP();
        
        // Headers específicos para streaming
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Methods: GET, HEAD, OPTIONS");
        header("Access-Control-Allow-Headers: Range, Content-Type, Authorization");
        header("Accept-Ranges: bytes");
    }

    public function process(): void {
        // Manejar preflight requests
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }

        // Solo permitir GET y HEAD
        if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'HEAD'])) {
            logSecurityEvent("Invalid method for stream", [
                'method' => $_SERVER['REQUEST_METHOD'],
                'ip' => $this->clientIP
            ]);
            $this->showError("Método no permitido", 405);
            return;
        }

        // Rate limiting más estricto para streams
        if (!checkRateLimit($this->clientIP, 20)) {
            logSecurityEvent("Stream rate limit exceeded", ['ip' => $this->clientIP]);
            $this->showError("Demasiadas solicitudes", 429);
            return;
        }

        // Validar referer
        if (!validateReferer()) {
            logSecurityEvent("Invalid referer for stream", [
                'ip' => $this->clientIP,
                'referer' => $_SERVER['HTTP_REFERER'] ?? 'none'
            ]);
            $this->showError("Acceso no autorizado", 403);
            return;
        }

        // Obtener y validar datos
        $data = sanitizeInput($_GET["data"] ?? "");
        
        if (empty($data)) {
            logSecurityEvent("Empty data in stream", ['ip' => $this->clientIP]);
            $this->showError("Datos requeridos", 400);
            return;
        }

        // Decodificar token seguro
        $decodedResult = decodeSecure($data);
        if ($decodedResult === false) {
            logSecurityEvent("Failed to decode stream token", [
                'ip' => $this->clientIP,
                'data_length' => strlen($data)
            ]);
            $this->showError("Token inválido o expirado", 401);
            return;
        }

        $url = $decodedResult['data'];

        // Validar URL decodificada
        if (!validateUrl($url)) {
            logSecurityEvent("Invalid decoded URL in stream", [
                'ip' => $this->clientIP,
                'url_length' => strlen($url)
            ]);
            $this->showError("URL inválida", 400);
            return;
        }

        // Log de acceso exitoso
        logSecurityEvent("Stream accessed successfully", [
            'ip' => $this->clientIP,
            'target_domain' => parse_url($url, PHP_URL_HOST),
            'token_age' => time() - $decodedResult['timestamp'],
            'method' => $_SERVER['REQUEST_METHOD']
        ]);

        // Procesar stream
        $this->streamVideo($url);
    }

    private function streamVideo(string $url): void {
        try {
            // Obtener información del video remoto
            $context = $this->createStreamContext();
            $headers = @get_headers($url, true, $context);
            
            if ($headers === false) {
                logSecurityEvent("Failed to get headers for stream", [
                    'ip' => $this->clientIP,
                    'target_domain' => parse_url($url, PHP_URL_HOST)
                ]);
                $this->showError("Video no disponible", 404);
                return;
            }

            // Verificar que el contenido es válido
            $firstHeader = $headers[0] ?? '';
            if (strpos($firstHeader, '200') === false && 
                strpos($firstHeader, '206') === false &&
                strpos($firstHeader, '302') === false &&
                strpos($firstHeader, '301') === false) {
                
                logSecurityEvent("Invalid response from video source", [
                    'ip' => $this->clientIP,
                    'response' => $firstHeader
                ]);
                $this->showError("Video no accesible", 404);
                return;
            }

            // Obtener información del contenido
            $contentType = $this->getHeader($headers, 'Content-Type') ?: 'video/mp4';
            $contentLength = $this->getHeader($headers, 'Content-Length');
            $acceptRanges = $this->getHeader($headers, 'Accept-Ranges');

            // Configurar headers de respuesta
            header('Content-Type: ' . $contentType);
            header('Cache-Control: public, max-age=3600');
            header('X-Content-Duration: 0');
            
            if ($acceptRanges) {
                header('Accept-Ranges: ' . $acceptRanges);
            }

            // Manejar Range requests para streaming
            if (isset($_SERVER['HTTP_RANGE']) && $contentLength) {
                $this->handleRangeRequest($url, (int)$contentLength);
            } else {
                // Stream completo o redirección segura
                if ($_SERVER['REQUEST_METHOD'] === 'HEAD') {
                    // Solo headers para HEAD request
                    if ($contentLength) {
                        header('Content-Length: ' . $contentLength);
                    }
                } else {
                    // Para GET, redirigir de forma segura
                    $this->secureRedirect($url);
                }
            }

        } catch (Exception $e) {
            error_log("Error en streamVideo: " . $e->getMessage());
            $this->showError("Error interno del servidor", 500);
        }
    }

    private function createStreamContext(): resource {
        return stream_context_create([
            'http' => [
                'method' => 'HEAD',
                'timeout' => 15,
                'header' => [
                    'User-Agent: Mozilla/5.0 (compatible; MP4SecureStream/2.0)',
                    'Accept: video/mp4, video/*, */*',
                    'Connection: close'
                ],
                'follow_location' => true,
                'max_redirects' => 3
            ]
        ]);
    }

    private function getHeader(array $headers, string $name): ?string {
        foreach ($headers as $header) {
            if (is_string($header) && stripos($header, $name . ':') === 0) {
                return trim(substr($header, strlen($name) + 1));
            }
        }
        
        // También buscar en formato de array asociativo
        return $headers[$name] ?? $headers[strtolower($name)] ?? null;
    }

    private function handleRangeRequest(string $url, int $contentLength): void {
        $range = $_SERVER['HTTP_RANGE'];
        
        if (!preg_match('/bytes=(\d+)-(\d*)/', $range, $matches)) {
            $this->showError("Range inválido", 416);
            return;
        }

        $start = (int)$matches[1];
        $end = empty($matches[2]) ? $contentLength - 1 : (int)$matches[2];
        
        if ($start > $end || $start >= $contentLength) {
            header('Content-Range: bytes */' . $contentLength);
            $this->showError("Range inválido", 416);
            return;
        }

        // Ajustar
