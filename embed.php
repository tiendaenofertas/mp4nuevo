<?php
declare(strict_types=1);

require_once __DIR__ . "/config/config.php";

class MP4SimpleEmbed {
    private string $data = "";
    private array $videoData = [];
    private string $clientIP;

    public function __construct() {
        setSecurityHeaders();
        $this->clientIP = getClientIP();
    }

    public function render(): void {
        // Validar referer (ahora más permisivo)
        if (!validateReferer()) {
            logSecurityEvent("Invalid referer for embed", [
                'ip' => $this->clientIP,
                'referer' => $_SERVER['HTTP_REFERER'] ?? 'none'
            ]);
            $this->show403();
            return;
        }

        // Rate limiting (ahora deshabilitado para debug)
        if (!checkRateLimit($this->clientIP, 30)) {
            logSecurityEvent("Rate limit exceeded for embed", ['ip' => $this->clientIP]);
            $this->showError("Demasiadas solicitudes");
            return;
        }

        // Obtener y validar datos
        $this->data = sanitizeInput($_GET["data"] ?? "");
        
        if (empty($this->data)) {
            logSecurityEvent("Empty data in embed", ['ip' => $this->clientIP]);
            $this->showError("Datos requeridos");
            return;
        }

        // Decodificar datos - INTENTAR AMBOS MÉTODOS
        $decodedResult = decodeSecure($this->data);
        if ($decodedResult === false) {
            // Intentar con método simple
            $simpleDecoded = decode($this->data);
            if ($simpleDecoded !== false) {
                // Es una URL simple
                $this->videoData = ['link' => $simpleDecoded, 'poster' => '', 'sub' => []];
            } else {
                logSecurityEvent("Failed to decode embed data", [
                    'ip' => $this->clientIP,
                    'data_length' => strlen($this->data)
                ]);
                $this->showError("Token inválido o expirado");
                return;
            }
        } else {
            // Decodificación segura exitosa
            $jsonData = $decodedResult['data'];
            $this->videoData = json_decode($jsonData, true);
            
            if ($this->videoData === null) {
                logSecurityEvent("Invalid JSON in embed data", ['ip' => $this->clientIP]);
                $this->showError("Datos corruptos");
                return;
            }
        }

        // Validar estructura de datos
        if (!isset($this->videoData['link']) || !validateUrl($this->videoData['link'])) {
            logSecurityEvent("Invalid video URL in embed", ['ip' => $this->clientIP]);
            $this->showError("URL de video inválida");
            return;
        }

        // Log de acceso exitoso
        logSecurityEvent("Embed accessed successfully", [
            'ip' => $this->clientIP,
            'video_domain' => parse_url($this->videoData['link'], PHP_URL_HOST)
        ]);

        $this->showPlayerWithWatermark();
    }

    private function showPlayerWithWatermark(): void {
        $link = $this->videoData["link"];
        $poster = $this->videoData["poster"] ?? "";
        $subtitles = $this->videoData["sub"] ?? [];
        
        // Usar URL directa temporalmente para debug
        $videoUrl = $link;
        
        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>MP4 Player</title>
            <meta name="robots" content="noindex, nofollow">
            
            <style>
                * {
                    margin: 0;
                    padding: 0;
                    box-sizing: border-box;
                    user-select: none;
                }
                
                html, body {
                    height: 100%;
                    overflow: hidden;
                    background: #000;
                    font-family: Arial, sans-serif;
                }
                
                #video-container {
                    width: 100%;
                    height: 100%;
                    background: #000;
                    position: relative;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                }
                
                #main-video {
                    width: 100%;
                    height: 100%;
                    background: #000;
                }
                
                /* MARCA DE AGUA - 150x100px */
                .watermark {
                    position: absolute;
                    top: 20px;
                    right: 20px;
                    width: 150px;
                    height: 100px;
                    background: linear-gradient(135deg, rgba(102, 126, 234, 0.15), rgba(118, 75, 162, 0.15));
                    backdrop-filter: blur(10px);
                    border: 1px solid rgba(255, 255, 255, 0.2);
                    border-radius: 10px;
                    display: flex;
                    flex-direction: column;
                    justify-content: center;
                    align-items: center;
                    z-index: 1000;
                    pointer-events: none;
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    transition: opacity 0.3s ease;
                    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
                }
                
                .watermark-logo {
                    font-size: 24px;
                    font-weight: 800;
                    color: #ffffff;
                    text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.8);
                    margin-bottom: 2px;
                    background-image: linear-gradient(to right top, #e10505, #e80404, #f00303, #f70101, #ff0000);
                    -webkit-background-clip: text;
                    -webkit-text-fill-color: transparent;
                    background-clip: text;
                }
                
                .watermark-text {
                    font-size: 11px;
                    color: rgba(255, 255, 255, 0.9);
                    text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.8);
                    font-weight: 600;
                    letter-spacing: 0.5px;
                }
                
                .watermark-icon {
                    position: absolute;
                    top: 8px;
                    left: 8px;
                    width: 20px;
                    height: 20px;
                    background: linear-gradient(45deg, #667eea, #764ba2);
                    border-radius: 50%;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    color: white;
                    font-size: 10px;
                    font-weight: bold;
                }
                
                /* Animación sutil de la marca de agua */
                @keyframes watermark-pulse {
                    0%, 100% { opacity: 0.8; }
                    50% { opacity: 1; }
                }
                
                .watermark {
                    animation: watermark-pulse 4s ease-in-out infinite;
                }
                
                /* Responsive para móviles */
                @media (max-width: 768px) {
                    .watermark {
                        top: 15px;
                        right: 15px;
                        width: 120px;
                        height: 80px;
                    }
                    
                    .watermark-logo {
                        font-size: 18px;
                    }
                    
                    .watermark-text {
                        font-size: 9px;
                    }
                    
                    .watermark-icon {
                        width: 16px;
                        height: 16px;
                        font-size: 8px;
                        top: 6px;
                        left: 6px;
                    }
                }
                
                @media (max-width: 480px) {
                    .watermark {
                        width: 100px;
                        height: 70px;
                    }
                    
                    .watermark-logo {
                        font-size: 16px;
                    }
                    
                    .watermark-text {
                        font-size: 8px;
                    }
                }
                
                /* Ocultar marca de agua temporalmente al hacer hover en controles */
                #video-container:hover .watermark {
                    opacity: 0.6;
                }
                
                .loading-overlay {
                    position: absolute;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0, 0, 0, 0.8);
                    display: flex;
                    flex-direction: column;
                    justify-content: center;
                    align-items: center;
                    z-index: 999;
                    color: white;
                }
                
                .loading-spinner {
                    width: 40px;
                    height: 40px;
                    border: 3px solid rgba(255, 255, 255, 0.3);
                    border-top: 3px solid #667eea;
                    border-radius: 50%;
                    animation: spin 1s linear infinite;
                    margin-bottom: 20px;
                }
                
                @keyframes spin {
                    0% { transform: rotate(0deg); }
                    100% { transform: rotate(360deg); }
                }
                
                .error-overlay {
                    position: absolute;
                    top: 50%;
                    left: 50%;
                    transform: translate(-50%, -50%);
                    background: rgba(255, 68, 68, 0.9);
                    color: white;
                    padding: 20px;
                    border-radius: 10px;
                    text-align: center;
                    z-index: 1001;
                }
                
                /* Protecciones básicas */
                video::-webkit-media-controls-download-button {
                    display: none;
                }
                
                video::-webkit-media-controls-fullscreen-button {
                    display: block;
                }
                
                /* Marca de agua adicional en pantalla completa */
                #video-container:-webkit-full-screen .watermark {
                    top: 30px;
                    right: 30px;
                    width: 180px;
                    height: 120px;
                }
                
                #video-container:-webkit-full-screen .watermark-logo {
                    font-size: 28px;
                }
                
                #video-container:-webkit-full-screen .watermark-text {
                    font-size: 13px;
                }
                
                /* Para Firefox */
                #video-container:-moz-full-screen .watermark {
                    top: 30px;
                    right: 30px;
                    width: 180px;
                    height: 120px;
                }
                
                /* Para otros navegadores */
                #video-container:fullscreen .watermark {
                    top: 30px;
                    right: 30px;
                    width: 180px;
                    height: 120px;
                }
            </style>
        </head>
        <body>
            <div id="video-container">
                <div class="loading-overlay" id="loadingOverlay">
                    <div class="loading-spinner"></div>
                    <div>Cargando video...</div>
                </div>
                
                <!-- MARCA DE AGUA 150x100 -->
                <div class="watermark" id="watermark">
                    <div class="watermark-icon">🛡️</div>
                    <div class="watermark-logo">XZORRA</div>
                    <div class="watermark-text"></div>
                </div>
                
                <video 
                    id="main-video" 
                    controls 
                    preload="metadata"
                    <?php if (!empty($poster)): ?>
                    poster="<?= htmlspecialchars($poster, ENT_QUOTES, 'UTF-8') ?>"
                    <?php endif; ?>
                    controlsList="nodownload"
                    oncontextmenu="return false;">
                    
                    <source src="<?= htmlspecialchars($videoUrl, ENT_QUOTES, 'UTF-8') ?>" type="video/mp4">
                    
                    <?php if (!empty($subtitles)): ?>
                    <?php foreach ($subtitles as $label => $url): ?>
                    <?php if (validateUrl($url)): ?>
                    <track kind="captions" 
                           src="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>" 
                           srclang="es" 
                           label="<?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>">
                    <?php endif; ?>
                    <?php endforeach; ?>
                    <?php endif; ?>
                    
                    Tu navegador no soporta el elemento video.
                </video>
            </div>

            <script>
                // Protecciones básicas
                document.addEventListener('contextmenu', function(e) {
                    e.preventDefault();
                    return false;
                });
                
                document.addEventListener('keydown', function(e) {
                    // F12, Ctrl+Shift+I, Ctrl+U, Ctrl+S, Print Screen
                    if (e.keyCode === 123 || 
                        (e.ctrlKey && e.shiftKey && e.keyCode === 73) ||
                        (e.ctrlKey && e.keyCode === 85) ||
                        (e.ctrlKey && e.keyCode === 83) ||
                        e.keyCode === 44) { // Print Screen
                        e.preventDefault();
                        return false;
                    }
                });

                // Variables del reproductor
                const video = document.getElementById('main-video');
                const loadingOverlay = document.getElementById('loadingOverlay');
                const watermark = document.getElementById('watermark');
                
                // Event listeners del video
                video.addEventListener('loadstart', function() {
                    console.log('Video: Iniciando carga...');
                });
                
                video.addEventListener('loadedmetadata', function() {
                    console.log('Video: Metadatos cargados');
                    loadingOverlay.style.display = 'none';
                });
                
                video.addEventListener('canplay', function() {
                    console.log('Video: Listo para reproducir');
                    loadingOverlay.style.display = 'none';
                });
                
                video.addEventListener('error', function(e) {
                    console.error('Error del video:', e);
                    loadingOverlay.innerHTML = `
                        <div class="error-overlay">
                            <h3>Error de Video</h3>
                            <p>No se pudo cargar el video</p>
                            <small>Código: ${video.error ? video.error.code : 'Desconocido'}</small>
                        </div>
                    `;
                });
                
                // Protección adicional contra captura de pantalla
                document.addEventListener('keyup', function(e) {
                    if (e.keyCode === 44) { // Print Screen
                        // Ocultar video temporalmente
                        video.style.visibility = 'hidden';
                        setTimeout(() => {
                            video.style.visibility = 'visible';
                        }, 100);
                    }
                });
                
                // Cambiar posición de marca de agua aleatoriamente (anti-crop)
                function randomizeWatermarkPosition() {
                    const positions = [
                        { top: '20px', right: '20px', bottom: 'auto', left: 'auto' },
                        { top: '20px', right: 'auto', bottom: 'auto', left: '20px' },
                        { top: 'auto', right: '20px', bottom: '80px', left: 'auto' },
                        { top: 'auto', right: 'auto', bottom: '80px', left: '20px' }
                    ];
                    
                    const randomPos = positions[Math.floor(Math.random() * positions.length)];
                    
                    Object.assign(watermark.style, randomPos);
                }
                
                // Cambiar posición cada 30 segundos
                setInterval(randomizeWatermarkPosition, 30000);
                
                // Asegurar que la marca de agua siempre esté visible
                video.addEventListener('play', function() {
                    watermark.style.display = 'flex';
                });
                
                video.addEventListener('pause', function() {
                    watermark.style.display = 'flex';
                });
                
                // Ocultar loading después de 5 segundos máximo
                setTimeout(function() {
                    if (loadingOverlay.style.display !== 'none') {
                        loadingOverlay.style.display = 'none';
                    }
                }, 5000);
                
                // Intentar reproducir automáticamente
                video.play().catch(function(error) {
                    console.log('Autoplay bloqueado:', error);
                });
                
                // Protección contra manipulación del DOM
                const observer = new MutationObserver(function(mutations) {
                    mutations.forEach(function(mutation) {
                        if (mutation.type === 'childList') {
                            // Verificar que la marca de agua sigue presente
                            if (!document.getElementById('watermark')) {
                                location.reload();
                            }
                        }
                    });
                });
                
                observer.observe(document.body, {
                    childList: true,
                    subtree: true
                });
            </script>
        </body>
        </html>
        <?php
    }

    private function show403(): void {
        http_response_code(403);
        $this->showError("Acceso denegado", "FORBID_001");
    }

    private function showError(string $message, string $code = "ERR_001"): void {
        http_response_code(400);
        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <title>Error - MP4 Player</title>
            <style>
                body {
                    background: #1a1a1a;
                    color: #fff;
                    font-family: Arial, sans-serif;
                    height: 100vh;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    margin: 0;
                }
                .error-container {
                    text-align: center;
                    background: rgba(255, 255, 255, 0.1);
                    padding: 40px;
                    border-radius: 15px;
                    max-width: 400px;
                }
                .error-icon { font-size: 4rem; margin-bottom: 20px; }
                .error-title { font-size: 1.5rem; color: #ef4444; margin-bottom: 10px; }
                .error-message { margin-bottom: 20px; }
                .error-code { font-size: 0.8rem; color: #888; }
            </style>
        </head>
        <body>
            <div class="error-container">
                <div class="error-icon">🛡️</div>
                <div class="error-title">Error del Reproductor</div>
                <div class="error-message"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
                <div class="error-code">Error: <?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?></div>
            </div>
        </body>
        </html>
        <?php
    }
}

$embed = new MP4SimpleEmbed();
$embed->render();
?>
