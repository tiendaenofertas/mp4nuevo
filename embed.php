<?php
declare(strict_types=1);

require_once __DIR__ . "/config/config.php";

// Iniciar sesión para tokens temporales
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

class MP4SecurePlayer {
    private string $data = "";
    private array $videoData = [];
    private string $clientIP;

    public function __construct() {
        setSecurityHeaders();
        $this->clientIP = getClientIP();
    }

    public function render(): void {
        // 1. Validar Referer
        if (!validateReferer()) {
            $this->showError("Acceso no autorizado.");
            return;
        }

        // 2. Rate Limit
        if (!checkRateLimit($this->clientIP, 150)) {
            $this->showError("Límite de tráfico excedido.");
            return;
        }

        // 3. Procesar datos
        $this->data = sanitizeInput($_GET["data"] ?? "");
        
        if (empty($this->data)) {
            $this->showError("Video no encontrado.");
            return;
        }

        // 4. Decodificar
        $decodedResult = decodeSecure($this->data);
        if ($decodedResult === false) {
            $this->showError("Enlace caducado.");
            return;
        }

        $jsonData = $decodedResult['data'];
        $this->videoData = is_array($jsonData) ? $jsonData : (json_decode($jsonData, true) ?? []);

        if (empty($this->videoData['link']) || !validateUrl($this->videoData['link'])) {
            $this->showError("Fuente de video inválida.");
            return;
        }

        $this->renderInterface();
    }

    private function renderInterface(): void {
        $videoUrl = $this->videoData["link"];
        $poster = $this->videoData["poster"] ?? "";
        $subs = $this->videoData["sub"] ?? [];

        // Generar token seguro para stream (Redirección 302)
        $streamToken = encodeSecure($videoUrl);
        $streamUrl = "stream/index.php?data=" . urlencode($streamToken);

        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
            <title>Reproductor XZORRA</title>
            <meta name="robots" content="noindex, nofollow">
            <style>
                /* RESET & BASE */
                * { margin: 0; padding: 0; box-sizing: border-box; }
                html, body {
                    width: 100%; height: 100%;
                    background: #000; overflow: hidden;
                    font-family: 'Arial', sans-serif;
                    -webkit-tap-highlight-color: transparent;
                }

                /* CONTENEDOR PRINCIPAL */
                .player-wrapper {
                    position: relative;
                    width: 100%;
                    height: 100%;
                    background: #000;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                }

                /* VIDEO */
                video {
                    width: 100%;
                    height: 100%;
                    object-fit: contain;
                    display: block;
                }

                /* --- MARCA DE AGUA (XZORRA) --- */
                .watermark {
                    position: absolute;
                    top: 25px;
                    left: 25px;
                    display: flex;
                    align-items: center;
                    gap: 12px;
                    padding: 8px 16px;
                    background: rgba(12, 12, 18, 0.85);
                    border: 1px solid rgba(255, 255, 255, 0.1);
                    border-radius: 12px;
                    backdrop-filter: blur(4px);
                    z-index: 20;
                    pointer-events: none; /* Click traspasa al video */
                    box-shadow: 0 4px 15px rgba(0,0,0,0.5);
                    transition: opacity 0.3s;
                }

                .watermark-icon {
                    width: 24px;
                    height: 24px;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    font-size: 18px;
                }

                .watermark-text {
                    font-family: 'Impact', sans-serif; /* Fuente gruesa */
                    font-size: 22px;
                    color: #d10000; /* Rojo oscuro */
                    letter-spacing: 1px;
                    text-transform: uppercase;
                    text-shadow: 0 2px 4px rgba(0,0,0,0.8);
                    font-weight: bold;
                    background: -webkit-linear-gradient(#ff0000, #990000);
                    -webkit-background-clip: text;
                    -webkit-text-fill-color: transparent;
                }

                /* --- BOTÓN PLAY GIGANTE --- */
                .play-overlay {
                    position: absolute;
                    top: 0; left: 0;
                    width: 100%; height: 100%;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    background: rgba(0, 0, 0, 0.4); /* Fondo oscuro semitransparente */
                    cursor: pointer;
                    z-index: 10;
                    transition: opacity 0.3s ease, visibility 0.3s;
                }

                .play-circle {
                    width: 90px;
                    height: 90px;
                    border-radius: 50%;
                    background: rgba(30, 30, 30, 0.6);
                    border: 2px solid rgba(255, 255, 255, 0.4);
                    backdrop-filter: blur(2px);
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    box-shadow: 0 0 20px rgba(0,0,0,0.5);
                    transition: transform 0.2s ease, background 0.2s;
                }

                .play-triangle {
                    width: 0; 
                    height: 0; 
                    border-top: 18px solid transparent;
                    border-bottom: 18px solid transparent;
                    border-left: 32px solid #e50914; /* Triángulo Rojo */
                    margin-left: 6px; /* Ajuste visual del centro */
                    filter: drop-shadow(0 2px 4px rgba(0,0,0,0.5));
                }

                /* Efecto Hover en Desktop */
                @media (hover: hover) {
                    .play-overlay:hover .play-circle {
                        transform: scale(1.1);
                        background: rgba(50, 50, 50, 0.7);
                        border-color: #fff;
                    }
                }

                /* OCULTAR INTERFAZ CUANDO REPRODUCE */
                .is-playing .play-overlay {
                    opacity: 0;
                    visibility: hidden;
                    pointer-events: none;
                }
                
                /* --- RESPONSIVE (Móviles y Tablets) --- */
                @media (max-width: 768px) {
                    .watermark {
                        top: 15px; left: 15px;
                        padding: 6px 12px;
                        border-radius: 8px;
                    }
                    .watermark-icon { font-size: 16px; width: 20px; }
                    .watermark-text { font-size: 18px; }
                    
                    .play-circle {
                        width: 70px; height: 70px;
                    }
                    .play-triangle {
                        border-top-width: 14px;
                        border-bottom-width: 14px;
                        border-left-width: 24px;
                        margin-left: 4px;
                    }
                }

                @media (max-width: 480px) {
                    .watermark {
                        top: 10px; left: 10px;
                        transform-origin: top left;
                        transform: scale(0.9);
                    }
                    .play-circle {
                        width: 60px; height: 60px;
                    }
                }
            </style>
        </head>
        <body oncontextmenu="return false;">
            
            <div class="player-wrapper" id="wrapper">
                
                <div class="watermark">
                    <div class="watermark-icon">🛡️</div>
                    <div class="watermark-text">XZORRA</div>
                </div>

                <div class="play-overlay" id="playBtn">
                    <div class="play-circle">
                        <div class="play-triangle"></div>
                    </div>
                </div>

                <video 
                    id="mainVideo" 
                    playsinline 
                    webkit-playsinline 
                    preload="metadata"
                    controlsList="nodownload"
                >
                    <source src="<?= htmlspecialchars($streamUrl) ?>" type="video/mp4">
                    
                    <?php if (!empty($subs)): ?>
                        <?php foreach ($subs as $label => $url): ?>
                            <track label="<?= htmlspecialchars($label) ?>" kind="captions" srclang="es" src="<?= htmlspecialchars($url) ?>">
                        <?php endforeach; ?>
                    <?php endif; ?>
                </video>
            </div>

            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    const video = document.getElementById('mainVideo');
                    const playBtn = document.getElementById('playBtn');
                    const wrapper = document.getElementById('wrapper');
                    const posterUrl = "<?= htmlspecialchars($poster) ?>";

                    // Configurar poster si existe
                    if (posterUrl) {
                        video.poster = posterUrl;
                    }

                    // FUNCIÓN: Iniciar video
                    function startVideo() {
                        video.play().then(() => {
                            wrapper.classList.add('is-playing');
                            video.controls = true; // Activar controles nativos al reproducir
                        }).catch(err => {
                            console.log("Autoplay bloqueado o error:", err);
                            // Si falla (ej: requiere mute), intentar muted
                            video.muted = true;
                            video.play().then(() => {
                                wrapper.classList.add('is-playing');
                                video.controls = true;
                                video.muted = false; // Intentar desmutear
                            });
                        });
                    }

                    // Eventos Click (Desktop y Móvil)
                    playBtn.addEventListener('click', startVideo);
                    
                    // Eventos de estado del video
                    video.addEventListener('play', () => {
                        wrapper.classList.add('is-playing');
                        video.controls = true;
                    });

                    video.addEventListener('pause', () => {
                        // Opcional: Si quieres que el botón vuelva a aparecer al pausar, 
                        // quita el comentario de la línea de abajo:
                        // wrapper.classList.remove('is-playing');
                        // video.controls = false;
                    });

                    // Protección contra teclas de inspección
                    document.addEventListener('keydown', (e) => {
                        if (e.key === 'F12' || (e.ctrlKey && e.shiftKey && e.key === 'I')) {
                            e.preventDefault();
                        }
                    });
                });
            </script>
        </body>
        </html>
        <?php
    }

    private function showError(string $msg): void {
        http_response_code(400);
        echo "<div style='color:#fff;background:#000;height:100vh;display:flex;justify-content:center;align-items:center;font-family:sans-serif;'>⚠️ " . htmlspecialchars($msg) . "</div>";
        exit;
    }
}

$player = new MP4SecurePlayer();
$player->render();
?>
