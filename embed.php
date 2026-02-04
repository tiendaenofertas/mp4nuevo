<?php
declare(strict_types=1);

require_once __DIR__ . "/config/config.php";

// ✅ Iniciar sesión ANTES de cualquier output HTML
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

class MP4SimpleEmbed {
    private string $data = "";
    private array $videoData = [];
    private string $clientIP;

    public function __construct() {
        setSecurityHeaders();
        $this->clientIP = getClientIP();
    }

    public function render(): void {
        // Validar referer (más permisivo)
        if (!validateReferer()) {
            logSecurityEvent("Invalid referer for embed", [
                'ip' => $this->clientIP,
                'referer' => $_SERVER['HTTP_REFERER'] ?? 'none'
            ]);
            // Permitir pero logear
            error_log("SECURITY WARNING: Invalid referer allowed");
        }

        // Rate limiting
        if (!checkRateLimit($this->clientIP, 50)) {
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

        // Decodificar datos
        $decodedResult = decodeSecure($this->data);
        if ($decodedResult === false) {
            $simpleDecoded = decode($this->data);
            if ($simpleDecoded !== false) {
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

        $this->showPlayerWithHiddenUrl();
    }

    private function showPlayerWithHiddenUrl(): void {
        $originalVideoUrl = $this->videoData["link"];
        $originalPoster = $this->videoData["poster"] ?? "";
        $originalSubtitles = $this->videoData["sub"] ?? [];
        
        // Crear token temporal único para esta sesión
        $sessionToken = bin2hex(random_bytes(32));
        $videoToken = encodeSecure($originalVideoUrl);
        
        // Guardar en sesión temporal (se limpia automáticamente)
        $_SESSION['video_tokens'][$sessionToken] = [
            'url' => $originalVideoUrl,
            'created' => time(),
            'ip' => $this->clientIP
        ];
        
        $encryptedPoster = !empty($originalPoster) ? base64_encode($originalPoster) : "";
        
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
                
                /* BOTÓN DE PLAY GRANDE */
                .play-overlay {
                    position: absolute;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0, 0, 0, 0.7);
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    z-index: 998;
                    cursor: pointer;
                    transition: opacity 0.3s ease;
                }
                
                .play-overlay:hover {
                    background: rgba(0, 0, 0, 0.8);
                }
                
                .play-button {
                    width: 120px;
                    height: 120px;
                    background: rgba(255, 255, 255, 0.15);
                    border-radius: 50%;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    cursor: pointer;
                    transition: all 0.3s ease;
                    backdrop-filter: blur(10px);
                    border: 3px solid rgba(255, 255, 255, 0.3);
                }
                
                .play-button:hover {
                    background: rgba(255, 255, 255, 0.25);
                    transform: scale(1.1);
                    border-color: rgba(255, 255, 255, 0.5);
                }
                
                .play-icon {
                    width: 0;
                    height: 0;
                    border-left: 35px solid #e50914;
                    border-top: 20px solid transparent;
                    border-bottom: 20px solid transparent;
                    margin-left: 8px;
                    filter: drop-shadow(0 2px 8px rgba(0, 0, 0, 0.3));
                }
                
                .play-overlay.hidden {
                    opacity: 0;
                    pointer-events: none;
                }
                
                /* Ocultar controles inicialmente */
                #main-video.not-started {
                    pointer-events: none;
                }
                
                #main-video.not-started::-webkit-media-controls {
                    display: none !important;
                }
                
                #main-video.not-started::-webkit-media-controls-enclosure {
                    display: none !important;
                }
                
                /* MARCA DE AGUA */
                .watermark {
                    position: absolute;
                    top: 20px;
                    right: 20px;
                    width: 150px;
                    height: 80px;
                    background: linear-gradient(135deg, rgba(102, 126, 234, 0.15), rgba(118, 75, 162, 0.15));
                    backdrop-filter: blur(10px);
                    -webkit-backdrop-filter: blur(10px);
                    border: 1px solid rgba(255, 255, 255, 0.2);
                    border-radius: 10px;
                    display: flex;
                    flex-direction: column;
                    justify-content: center;
                    align-items: center;
                    z-index: 1000;
                    pointer-events: none;
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    transition: opacity 0.3s ease, top 0.4s ease, right 0.4s ease, bottom 0.4s ease, left 0.4s ease;
                    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
                    animation: watermark-pulse 4s ease-in-out infinite;
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
                
                @keyframes watermark-pulse {
                    0%, 100% { opacity: 0.8; }
                    50% { opacity: 1; }
                }
                
                .loading-overlay {
                    position: absolute;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0, 0, 0, 0.9);
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
                    background: rgba(255, 68, 68, 0.95);
                    color: white;
                    padding: 30px;
                    border-radius: 15px;
                    text-align: center;
                    z-index: 1001;
                    max-width: 400px;
                }
                
                video::-webkit-media-controls-download-button {
                    display: none !important;
                }
                
                /* Responsive - Tablet */
                @media (max-width: 768px) {
                    .watermark {
                        width: 130px;
                        height: 85px;
                    }
                    
                    .watermark-logo {
                        font-size: 20px;
                    }
                    
                    .watermark-text {
                        font-size: 10px;
                    }
                    
                    .watermark-icon {
                        width: 18px;
                        height: 18px;
                        font-size: 9px;
                    }
                    
                    .play-button {
                        width: 100px;
                        height: 100px;
                    }
                    
                    .play-icon {
                        border-left: 28px solid #e50914;
                        border-top: 17px solid transparent;
                        border-bottom: 17px solid transparent;
                        margin-left: 6px;
                    }
                }
                
                /* Responsive - Móvil */
                @media (max-width: 480px) {
                    .watermark {
                        top: 8px;
                        right: 8px;
                        left: auto;
                        bottom: auto;
                        width: 105px;
                        height: 68px;
                        border-radius: 8px;
                        backdrop-filter: blur(6px);
                        -webkit-backdrop-filter: blur(6px);
                    }
                    
                    .watermark-logo {
                        font-size: 16px;
                        margin-bottom: 1px;
                    }
                    
                    .watermark-text {
                        font-size: 8px;
                    }
                    
                    .watermark-icon {
                        top: 5px;
                        left: 5px;
                        width: 16px;
                        height: 16px;
                        font-size: 8px;
                    }
                    
                    .play-button {
                        width: 80px;
                        height: 80px;
                    }
                    
                    .play-icon {
                        border-left: 22px solid #e50914;
                        border-top: 13px solid transparent;
                        border-bottom: 13px solid transparent;
                        margin-left: 5px;
                    }
                }

                /* Pantalla muy pequeña */
                @media (max-width: 320px) {
                    .watermark {
                        top: 5px;
                        right: 5px;
                        width: 90px;
                        height: 58px;
                        border-radius: 6px;
                    }
                    
                    .watermark-logo {
                        font-size: 14px;
                    }
                    
                    .watermark-text {
                        font-size: 7px;
                    }
                    
                    .watermark-icon {
                        top: 4px;
                        left: 4px;
                        width: 14px;
                        height: 14px;
                        font-size: 7px;
                    }
                }
                
                /* Fullscreen - con prefijos para iOS/Safari */
                #video-container:-webkit-full-screen .watermark,
                #video-container:-moz-full-screen .watermark,
                #video-container:fullscreen .watermark {
                    top: 30px;
                    right: 30px;
                    left: auto;
                    bottom: auto;
                    width: 180px;
                    height: 120px;
                }
                
                #video-container:-webkit-full-screen .watermark-logo,
                #video-container:-moz-full-screen .watermark-logo,
                #video-container:fullscreen .watermark-logo {
                    font-size: 28px;
                }
                
                #video-container:-webkit-full-screen .watermark-icon,
                #video-container:-moz-full-screen .watermark-icon,
                #video-container:fullscreen .watermark-icon {
                    width: 24px;
                    height: 24px;
                    font-size: 12px;
                }
            </style>
        </head>
        <body>
            <div id="video-container">
                <div class="loading-overlay" id="loadingOverlay">
                    <div class="loading-spinner"></div>
                    <div>Cargando video seguro...</div>
                </div>
                
                <!-- BOTÓN DE PLAY GRANDE -->
                <div class="play-overlay" id="playOverlay">
                    <div class="play-button" id="playButton">
                        <div class="play-icon"></div>
                    </div>
                </div>
                
                <!-- MARCA DE AGUA -->
                <div class="watermark" id="watermark">
                    <div class="watermark-icon">🛡️</div>
                    <div class="watermark-logo">Xcuca</div>
                  
                </div>
                
                <!-- VIDEO QUE SE CARGA DINÁMICAMENTE -->
                <video 
                    id="main-video" 
                    controls 
                    playsinline
                    webkit-playsinline
                    preload="metadata"
                    controlsList="nodownload"
                    oncontextmenu="return false;"
                    class="not-started">
                    
                    Tu navegador no soporta el elemento video.
                </video>
            </div>

            <script>
                // ✅ DATOS SEGUROS (NO CONTIENEN URL REAL)
                const sessionToken = '<?= $sessionToken ?>';
                const encryptedPoster = '<?= $encryptedPoster ?>';
                const subtitlesData = <?= json_encode($originalSubtitles) ?>;
                
                // Variables
                let videoLoaded = false;
                let videoStarted = false;
                
                // Referencias DOM
                const video = document.getElementById('main-video');
                const loadingOverlay = document.getElementById('loadingOverlay');
                const playOverlay = document.getElementById('playOverlay');
                const playButton = document.getElementById('playButton');
                const watermark = document.getElementById('watermark');
                
                // Protecciones básicas
                document.addEventListener('contextmenu', e => e.preventDefault());
                document.addEventListener('keydown', function(e) {
                    if (e.keyCode === 123 || 
                        (e.ctrlKey && e.shiftKey && e.keyCode === 73) ||
                        (e.ctrlKey && e.keyCode === 85) ||
                        (e.ctrlKey && e.keyCode === 83)) {
                        e.preventDefault();
                        return false;
                    }
                });
                
                // ✅ CARGAR VIDEO DE FORMA OCULTA
                function loadVideoSecurely() {
                    if (videoLoaded) return;
                    
                    try {
                        console.log('🔒 Iniciando carga de video seguro...');
                        
                        // ✅ USAR ENDPOINT INTERNO QUE NO REVELA LA URL
                        const secureVideoUrl = `stream.php?token=${sessionToken}&t=${Date.now()}`;
                        
                        console.log('🎬 URL segura generada');
                        
                        // Crear source con URL interna
                        const source = document.createElement('source');
                        source.src = secureVideoUrl; // ← ESTA NO ES LA URL REAL
                        source.type = 'video/mp4';
                        video.appendChild(source);
                        
                        // Configurar poster si existe
                        if (encryptedPoster) {
                            try {
                                const posterUrl = atob(encryptedPoster);
                                video.poster = posterUrl;
                                console.log('🖼️ Poster configurado');
                            } catch (e) {
                                console.log('⚠️ Error en poster');
                            }
                        }
                        
                        // Agregar subtítulos
                        if (subtitlesData && Object.keys(subtitlesData).length > 0) {
                            for (const [label, url] of Object.entries(subtitlesData)) {
                                const track = document.createElement('track');
                                track.kind = 'captions';
                                track.src = url;
                                track.srclang = 'es';
                                track.label = label;
                                video.appendChild(track);
                                console.log('📝 Subtítulo agregado:', label);
                            }
                        }
                        
                        videoLoaded = true;
                        video.load();
                        
                        console.log('✅ Video configurado correctamente');
                        
                    } catch (error) {
                        console.error('❌ Error cargando video:', error);
                        showError('Error al configurar el video: ' + error.message);
                    }
                }
                
                function showError(message) {
                    loadingOverlay.innerHTML = `
                        <div class="error-overlay">
                            <h3>Error de Video</h3>
                            <p>${message}</p>
                            <button onclick="location.reload()" style="margin-top: 15px; padding: 10px 20px; background: #667eea; color: white; border: none; border-radius: 5px; cursor: pointer;">
                                Reintentar
                            </button>
                        </div>
                    `;
                }
                
                // Event listeners del video
                video.addEventListener('loadstart', function() {
                    console.log('📹 Video: Iniciando carga...');
                });
                
                video.addEventListener('loadedmetadata', function() {
                    console.log('📹 Video: Metadatos cargados');
                    loadingOverlay.style.display = 'none';
                    
                    // Mostrar botón de play cuando el video esté listo
                    if (!videoStarted) {
                        playOverlay.style.display = 'flex';
                    }
                });
                
                video.addEventListener('canplay', function() {
                    console.log('📹 Video: Listo para reproducir');
                    loadingOverlay.style.display = 'none';
                    
                    // Mostrar botón de play
                    if (!videoStarted) {
                        playOverlay.style.display = 'flex';
                    }
                });
                
                video.addEventListener('play', function() {
                    // Ocultar botón de play cuando empiece a reproducir
                    playOverlay.classList.add('hidden');
                    video.classList.remove('not-started');
                    videoStarted = true;
                });
                
                video.addEventListener('pause', function() {
                    // No mostrar el botón de nuevo al pausar
                    // El video ya tiene controles nativos
                });
                
                video.addEventListener('error', function(e) {
                    console.error('❌ Error del video:', e);
                    const errorCode = video.error ? video.error.code : 'Desconocido';
                    showError(`Error del reproductor (Código: ${errorCode})`);
                });
                
                // ✅ MANEJAR CLIC EN BOTÓN DE PLAY (compatible iOS)
                playButton.addEventListener('click', function(e) {
                    e.stopPropagation();
                    if (videoLoaded) {
                        // iOS requiere que play() se llame directamente desde un gesture del usuario
                        const playPromise = video.play();
                        if (playPromise !== undefined) {
                            playPromise.then(function() {
                                console.log('✅ Video reproduciendo');
                            }).catch(function(error) {
                                console.error('❌ Error al reproducir:', error);
                                // Fallback iOS: intentar con muted primero
                                video.muted = true;
                                video.play().then(function() {
                                    video.muted = false;
                                    console.log('✅ Video reproduciendo (iOS fallback)');
                                }).catch(function(err2) {
                                    showError('Error al iniciar la reproducción. Por favor, toque de nuevo.');
                                });
                            });
                        }
                    }
                });
                
                // También permitir clic en toda la overlay
                playOverlay.addEventListener('click', function(e) {
                    if (e.target === playOverlay) {
                        playButton.click();
                    }
                });
                
                // Marca de agua - posición aleatoria sin conflictos
                function randomizeWatermarkPosition() {
                    const isMobile = window.innerWidth <= 480;
                    const isSmall = window.innerWidth <= 320;
                    
                    const margin = isSmall ? '5px' : isMobile ? '8px' : '20px';
                    const bottomMargin = isMobile ? '50px' : '80px'; // evitar controles del video
                    
                    // Posiciones disponibles según dispositivo
                    const positions = [
                        { top: margin, right: margin, bottom: 'auto', left: 'auto' },
                        { top: margin, right: 'auto', bottom: 'auto', left: margin },
                        { top: 'auto', right: margin, bottom: bottomMargin, left: 'auto' },
                        { top: 'auto', right: 'auto', bottom: bottomMargin, left: margin }
                    ];
                    
                    const pos = positions[Math.floor(Math.random() * positions.length)];
                    
                    // ✅ Limpiar TODAS las propiedades primero, luego aplicar nuevas
                    watermark.style.top = pos.top;
                    watermark.style.right = pos.right;
                    watermark.style.bottom = pos.bottom;
                    watermark.style.left = pos.left;
                }
                
                setInterval(randomizeWatermarkPosition, 30000);
                
                // Protección DOM
                const observer = new MutationObserver(function(mutations) {
                    mutations.forEach(function(mutation) {
                        if (!document.getElementById('watermark')) {
                            location.reload();
                        }
                    });
                });
                
                observer.observe(document.body, { childList: true, subtree: true });
                
                // Timeout de loading
                setTimeout(function() {
                    if (loadingOverlay.style.display !== 'none') {
                        loadingOverlay.style.display = 'none';
                    }
                }, 8000);
                
                // ✅ INICIALIZAR
                document.addEventListener('DOMContentLoaded', function() {
                    console.log('🚀 Iniciando reproductor seguro...');
                    setTimeout(loadVideoSecurely, 500);
                });
                
                // Limpiar datos
                setTimeout(function() {
                    window.sessionToken = null;
                    window.encryptedPoster = null;
                    window.subtitlesData = null;
                }, 3000);
            </script>
        </body>
        </html>
        <?php
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
