<?php
declare(strict_types=1);

/**
 * MP4 Security System - Reproductor Embebido Corregido
 * Gestiona la visualización segura y la protección de la fuente del video.
 */

require_once __DIR__ . "/config/config.php";

// Iniciar sesión con parámetros seguros antes de cualquier salida
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

class MP4SecureEmbed {
    private string $data = "";
    private array $videoData = [];
    private string $clientIP;

    public function __construct() {
        setSecurityHeaders();
        $this->clientIP = getClientIP();
    }

    public function render(): void {
        // 1. Validar referer (Protección contra incrustación no autorizada)
        if (!validateReferer()) {
            logSecurityEvent("Referer no autorizado en embed", [
                'ip' => $this->clientIP,
                'referer' => $_SERVER['HTTP_REFERER'] ?? 'directo'
            ]);
            // Bloqueamos en producción, permitimos con advertencia solo si es necesario
            $this->showError("Este dominio no está autorizado para reproducir este contenido.", "ERR_AUTH_01");
            return;
        }

        // 2. Control de peticiones (Rate Limit)
        if (!checkRateLimit($this->clientIP, 50)) {
            logSecurityEvent("Rate limit excedido en embed", ['ip' => $this->clientIP]);
            $this->showError("Demasiadas solicitudes. Intente más tarde.", "ERR_RATE_LIMIT");
            return;
        }

        // 3. Procesar parámetro de datos
        $this->data = sanitizeInput($_GET["data"] ?? "");
        
        if (empty($this->data)) {
            $this->showError("Identificador de video requerido.", "ERR_DATA_MISSING");
            return;
        }

        // 4. Decodificación compatible (Nuevo sistema y sistema antiguo)
        if (!$this->decryptVideoData()) {
            $this->showError("El enlace ha expirado o el token es inválido.", "ERR_DECODE_FAIL");
            return;
        }

        // 5. Validar integridad de la URL final
        if (!isset($this->videoData['link']) || !validateUrl($this->videoData['link'])) {
            logSecurityEvent("URL de video inválida en datos decodificados", ['ip' => $this->clientIP]);
            $this->showError("Error en la fuente del video.", "ERR_INVALID_SOURCE");
            return;
        }

        // 6. Registrar acceso exitoso y mostrar reproductor
        logSecurityEvent("Reproductor cargado con éxito", [
            'ip' => $this->clientIP,
            'host' => parse_url($this->videoData['link'], PHP_URL_HOST)
        ]);

        $this->displayPlayer();
    }

    /**
     * Intenta decodificar los datos usando ambos métodos de cifrado sincronizados.
     */
    private function decryptVideoData(): bool {
        // Intentar con el decodificador de alta seguridad (JSON + HMAC)
        $decoded = decodeSecure($this->data);
        
        if ($decoded !== false && isset($decoded['data'])) {
            $this->videoData = json_decode($decoded['data'], true);
            return is_array($this->videoData);
        }

        // Fallback: Intentar con el decodificador simple (compatibilidad con links antiguos)
        $legacyDecoded = decode($this->data);
        if ($legacyDecoded !== false) {
            $this->videoData = [
                'link' => $legacyDecoded,
                'poster' => '',
                'sub' => []
            ];
            return true;
        }

        return false;
    }

    /**
     * Prepara el entorno del cliente y renderiza el HTML del reproductor.
     */
    private function displayPlayer(): void {
        $originalVideoUrl = $this->videoData["link"];
        $posterUrl = $this->videoData["poster"] ?? "";
        $subtitles = $this->videoData["sub"] ?? [];
        
        // Generar token de sesión único para la comunicación con stream.php
        $sessionToken = bin2hex(random_bytes(16));
        
        // Registrar el token en la sesión del servidor (IP Binded)
        if (!isset($_SESSION['video_tokens'])) {
            $_SESSION['video_tokens'] = [];
        }
        
        $_SESSION['video_tokens'][$sessionToken] = [
            'url' => $originalVideoUrl,
            'created' => time(),
            'ip' => $this->clientIP
        ];
        
        // Ofuscar el poster para la capa de presentación
        $obfuscatedPoster = !empty($posterUrl) ? base64_encode($posterUrl) : "";
        
        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Video Player</title>
            <meta name="robots" content="noindex, nofollow">
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; user-select: none; -webkit-tap-highlight-color: transparent; }
                html, body { height: 100%; overflow: hidden; background: #000; font-family: -apple-system, system-ui, sans-serif; }
                #video-container { width: 100%; height: 100%; background: #000; position: relative; display: flex; justify-content: center; align-items: center; }
                #main-video { width: 100%; height: 100%; background: #000; outline: none; }
                
                /* Overlay de carga y play */
                .overlay { position: absolute; top: 0; left: 0; width: 100%; height: 100%; display: flex; justify-content: center; align-items: center; z-index: 10; transition: opacity 0.4s ease; }
                .loading-overlay { background: #000; color: #fff; flex-direction: column; }
                .play-overlay { background: rgba(0,0,0,0.5); cursor: pointer; }
                .hidden { opacity: 0; pointer-events: none; }
                
                /* Icono de Play Estilo Netflix */
                .play-btn { width: 90px; height: 90px; background: rgba(255,255,255,0.1); border-radius: 50%; display: flex; justify-content: center; align-items: center; border: 2px solid rgba(255,255,255,0.4); backdrop-filter: blur(5px); transition: transform 0.2s; }
                .play-btn:hover { transform: scale(1.1); border-color: #fff; }
                .play-icon { width: 0; height: 0; border-left: 30px solid #e50914; border-top: 18px solid transparent; border-bottom: 18px solid transparent; margin-left: 8px; }
                
                /* Marca de agua dinámica */
                .watermark { position: absolute; padding: 10px 15px; background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; color: #fff; z-index: 100; font-size: 14px; font-weight: bold; pointer-events: none; transition: all 0.5s ease; backdrop-filter: blur(4px); }
                .watermark span { color: #e50914; }

                /* Spinner de carga */
                .spinner { width: 40px; height: 40px; border: 4px solid rgba(255,255,255,0.1); border-top: 4px solid #e50914; border-radius: 50%; animation: spin 1s linear infinite; margin-bottom: 15px; }
                @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
                
                /* Ocultar botón de descarga nativo */
                video::-internal-media-controls-download-button { display:none; }
                video::-webkit-media-controls-enclosure { overflow:hidden; }
                video::-webkit-media-controls-panel { width: calc(100% + 30px); }
            </style>
        </head>
        <body oncontextmenu="return false;">
            <div id="video-container">
                <div id="loader" class="overlay loading-overlay">
                    <div class="spinner"></div>
                    <div style="font-size: 0.9rem; letter-spacing: 1px;">CARGANDO...</div>
                </div>

                <div id="play-gate" class="overlay play-overlay hidden">
                    <div class="play-btn">
                        <div class="play-icon"></div>
                    </div>
                </div>

                <div id="wm" class="watermark" style="top: 20px; right: 20px;">
                    🛡️ <span>Xcuca</span> Protection
                </div>

                <video id="main-video" playsinline webkit-playsinline controlsList="nodownload" preload="metadata">
                    Tu navegador no soporta video.
                </video>
            </div>

            <script>
                (function() {
                    const config = {
                        token: <?= json_encode($sessionToken) ?>,
                        poster: <?= json_encode($obfuscatedPoster) ?>,
                        subs: <?= json_encode($subtitles) ?>
                    };

                    const video = document.getElementById('main-video');
                    const loader = document.getElementById('loader');
                    const gate = document.getElementById('play-gate');
                    const wm = document.getElementById('wm');

                    // 1. Configuración de Seguridad
                    function setupVideo() {
                        const streamUrl = `stream.php?token=${config.token}&_t=${Date.now()}`;
                        const source = document.createElement('source');
                        source.src = streamUrl;
                        source.type = 'video/mp4';
                        video.appendChild(source);

                        if (config.poster) {
                            video.poster = atob(config.poster);
                        }

                        if (config.subs) {
                            Object.entries(config.subs).forEach(([label, url]) => {
                                const track = document.createElement('track');
                                track.kind = 'captions';
                                track.label = label;
                                track.src = url;
                                track.srclang = 'es';
                                video.appendChild(track);
                            });
                        }
                        video.load();
                    }

                    // 2. Control de Interfaz
                    video.addEventListener('loadedmetadata', () => {
                        loader.classList.add('hidden');
                        gate.classList.remove('hidden');
                    });

                    gate.addEventListener('click', () => {
                        video.play().then(() => {
                            gate.classList.add('hidden');
                        }).catch(err => {
                            console.error("Playback fallido:", err);
                        });
                    });

                    video.addEventListener('error', () => {
                        loader.innerHTML = '<div style="color:#ff4444; padding:20px; text-align:center;">Error al cargar la fuente del video.</div>';
                        loader.classList.remove('hidden');
                    });

                    // 3. Marca de Agua Dinámica
                    function moveWM() {
                        const positions = [
                            {t: '20px', r: '20px', b: 'auto', l: 'auto'},
                            {t: '20px', r: 'auto', b: 'auto', l: '20px'},
                            {t: 'auto', r: '20px', b: '80px', l: 'auto'},
                            {t: 'auto', r: 'auto', b: '80px', l: '20px'}
                        ];
                        const p = positions[Math.floor(Math.random() * positions.length)];
                        wm.style.top = p.t; wm.style.right = p.r;
                        wm.style.bottom = p.b; wm.style.left = p.l;
                    }
                    setInterval(moveWM, 20000);

                    // 4. Bloqueos de Inspección
                    document.addEventListener('keydown', e => {
                        if (e.key === 'F12' || (e.ctrlKey && e.shiftKey && (e.key === 'I' || e.key === 'J')) || (e.ctrlKey && e.key === 'U')) {
                            e.preventDefault();
                        }
                    });

                    // Limpieza de memoria local
                    setupVideo();
                    setTimeout(() => { config.token = null; config.poster = null; }, 5000);
                })();
            </script>
        </body>
        </html>
        <?php
    }

    private function showError(string $msg, string $code): void {
        http_response_code(400);
        echo "<body style='background:#000;color:#888;display:flex;justify-content:center;align-items:center;height:100vh;font-family:sans-serif;text-align:center;'>";
        echo "<div><div style='font-size:2rem;margin-bottom:10px;'>🛡️</div><div>$msg</div><div style='font-size:0.7rem;margin-top:10px;opacity:0.5;'>CODE: $code</div></div>";
        echo "</body>";
    }
}

$embed = new MP4SecureEmbed();
$embed->render();
