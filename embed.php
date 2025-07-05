<?php
declare(strict_types=1);

require_once __DIR__ . "/config/config.php";

class MP4Embed {
    private string $data = "";
    private array $videoData = [];

    public function __construct() {
        setSecurityHeaders();
    }

    public function render(): void {
        if (!validateReferer()) {
            $this->show404();
            return;
        }

        $this->data = sanitizeInput($_GET["data"] ?? "");
        
        if (empty($this->data)) {
            $this->showError("Datos vacíos");
            return;
        }

        // Debug: Log de datos recibidos
        error_log("Datos recibidos en embed: " . substr($this->data, 0, 100) . "...");

        // Decodificar la URL
        $decoded = decode($this->data);
        if ($decoded === false) {
            error_log("Error de decodificación en embed");
            $this->showError("Error de decodificación");
            return;
        }

        // Debug: Log de datos decodificados
        error_log("Datos decodificados: " . $decoded);

        // Usar función segura para decodificar JSON
        if (function_exists('safeJsonDecode')) {
            $this->videoData = safeJsonDecode($decoded);
        } else {
            try {
                $this->videoData = json_decode($decoded, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                error_log("Error JSON en embed: " . $e->getMessage());
                $this->showError("Datos JSON inválidos: " . $e->getMessage());
                return;
            }
        }
        
        if ($this->videoData === false || !is_array($this->videoData)) {
            $this->showError("Error al procesar datos del video");
            return;
        }

        $this->showPlayer();
    }

    private function showPlayer(): void {
        $link = $this->videoData["link"] ?? "";
        $poster = $this->videoData["poster"] ?? "";
        $subtitles = $this->videoData["sub"] ?? [];

        if (!validateUrl($link)) {
            $this->showError("URL de video inválida");
            return;
        }

        $tracks = $this->generateSubtitleTracks($subtitles);
        
        $domainServer = (isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== 'off' ? "https" : "http") . 
                       "://" . $_SERVER["SERVER_NAME"] . 
                       dirname($_SERVER["PHP_SELF"]);
        
        // Crear URL de stream
        $streamUrl = $domainServer . "/stream/?data=" . urlencode(encode($link));
        
        // Configurar fuentes del video
        $sources = [
            [
                "label" => "Auto", 
                "type" => "mp4", 
                "file" => $streamUrl,
                "default" => true
            ]
        ];

        $sourcesJson = json_encode($sources, JSON_UNESCAPED_SLASHES);

        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>MP4 Premium Player</title>
            <meta name="robots" content="noindex, nofollow">
            
            <link href="<?= $domainServer ?>/assets/css/netflix.css" rel="stylesheet">
            <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
            <script src="https://ssl.p.jwpcdn.com/player/v/8.24.0/jwplayer.js"></script>
            
            <script>jwplayer.key="64HPbvSQorQcd52B8XFuhMtEoitbvY/EXJmMBfKcXZQU2Rnn";</script>
            
            <style>
                html, body {
                    padding: 0;
                    margin: 0;
                    height: 100%;
                    overflow: hidden;
                    background: #000;
                    font-family: Arial, sans-serif;
                }
                #apicodes-player {
                    width: 100% !important;
                    height: 100% !important;
                    background-color: #000;
                }
                .loading {
                    position: absolute;
                    top: 50%;
                    left: 50%;
                    transform: translate(-50%, -50%);
                    color: #fff;
                    font-size: 18px;
                    z-index: 1000;
                    text-align: center;
                }
                .error-message {
                    position: absolute;
                    top: 50%;
                    left: 50%;
                    transform: translate(-50%, -50%);
                    color: #ff6666;
                    font-size: 16px;
                    z-index: 1000;
                    text-align: center;
                    background: rgba(0,0,0,0.8);
                    padding: 20px;
                    border-radius: 10px;
                }
            </style>
        </head>
        <body>
            <div class="loading" id="loading">
                <div>Cargando reproductor...</div>
                <div style="font-size: 12px; margin-top: 10px;">MP4 Premium Player v2.0</div>
            </div>
            <div id="apicodes-player"></div>

            <script>
                // Deshabilitar clic derecho y teclas de desarrollador
                document.addEventListener("contextmenu", function(e) {
                    e.preventDefault();
                });
                
                document.addEventListener("keydown", function(e) {
                    if (e.keyCode === 123 || 
                        (e.ctrlKey && e.shiftKey && e.keyCode === 73) ||
                        (e.ctrlKey && e.keyCode === 85) ||
                        (e.ctrlKey && e.keyCode === 83)) {
                        e.preventDefault();
                        return false;
                    }
                });

                // Deshabilitar selección de texto
                document.onselectstart = function() {
                    return false;
                };

                function showError(message) {
                    document.getElementById("loading").innerHTML = 
                        '<div class="error-message">' +
                        '<h3>❌ Error</h3>' +
                        '<p>' + message + '</p>' +
                        '<button onclick="location.reload()" style="padding: 10px 20px; margin-top: 10px; background: #0066cc; color: white; border: none; border-radius: 5px; cursor: pointer;">Reintentar</button>' +
                        '</div>';
                }

                // Configuración del reproductor
                const playerConfig = {
                    sources: <?= $sourcesJson ?>,
                    width: "100%",
                    height: "100%",
                    primary: "html5",
                    fullscreen: true,
                    autostart: false,
                    preload: "metadata",
                    stretching: "uniform",
                    <?php if (!empty($poster)): ?>
                    image: "<?= htmlspecialchars($poster, ENT_QUOTES, 'UTF-8') ?>",
                    <?php endif; ?>
                    skin: {
                        name: "netflix"
                    },
                    captions: {
                        color: "#FFFFFF",
                        fontSize: 16,
                        backgroundOpacity: 0,
                        fontfamily: "Arial, sans-serif",
                        edgeStyle: "raised"
                    },
                    advertising: {
                        client: "vast"
                    }
                    <?php if (!empty($tracks)): ?>
                    ,tracks: [<?= $tracks ?>]
                    <?php endif; ?>
                };
                
                // Inicializar reproductor cuando el DOM esté listo
                document.addEventListener("DOMContentLoaded", function() {
                    try {
                        console.log("Iniciando reproductor JWPlayer...");
                        const player = jwplayer("apicodes-player");
                        
                        player.setup(playerConfig);
                        
                        player.on("ready", function() {
                            console.log("Reproductor listo");
                            document.getElementById("loading").style.display = "none";
                        });
                        
                        player.on("play", function() {
                            console.log("Reproducción iniciada");
                        });
                        
                        player.on("error", function(e) {
                            console.error("Error del reproductor:", e);
                            showError("Error al cargar el video: " + (e.message || "Error desconocido"));
                        });

                        player.on("setupError", function(e) {
                            console.error("Error de configuración:", e);
                            showError("Error de configuración del reproductor");
                        });

                        // Intentar autoplay después de 2 segundos
                        setTimeout(function() {
                            try {
                                player.play().catch(function(error) {
                                    console.log("Autoplay bloqueado por el navegador:", error);
                                });
                            } catch(e) {
                                console.log("Autoplay no disponible");
                            }
                        }, 2000);

                    } catch(error) {
                        console.error("Error al inicializar el reproductor:", error);
                        showError("Error al inicializar el reproductor");
                    }
                });

                // Manejar errores no capturados
                window.addEventListener('error', function(event) {
                    console.error('Error global:', event.error);
                });

                window.addEventListener('unhandledrejection', function(event) {
                    console.error('Promesa rechazada:', event.reason);
                });

            </script>
        </body>
        </html>
        <?php
    }

    private function generateSubtitleTracks(array $subtitles): string {
        $tracks = [];
        
        foreach ($subtitles as $label => $url) {
            if (!empty($url) && validateUrl($url)) {
                $tracks[] = sprintf(
                    '{ file: "%s", label: "%s", kind: "captions", "default": %s }',
                    addslashes($url),
                    addslashes($label),
                    empty($tracks) ? 'true' : 'false'
                );
            }
        }

        return implode(",", $tracks);
    }

    private function show404(): void {
        http_response_code(404);
        if (file_exists(__DIR__ . "/errors/404.html")) {
            include __DIR__ . "/errors/404.html";
        } else {
            echo "<h1>404 - Página No Encontrada</h1>";
        }
    }

    private function showError(string $message): void {
        http_response_code(400);
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Error - MP4 Player</title>
            <style>
                body { 
                    background: #000; 
                    color: #fff; 
                    font-family: Arial; 
                    text-align: center; 
                    padding: 50px;
                    margin: 0;
                }
                .error { 
                    background: rgba(255, 68, 68, 0.2); 
                    padding: 30px; 
                    border-radius: 10px; 
                    display: inline-block;
                    border: 2px solid #ff4444;
                    max-width: 500px;
                }
                .error h3 {
                    color: #ff6666;
                    margin-top: 0;
                }
                button {
                    background: #0066cc;
                    color: white;
                    border: none;
                    padding: 10px 20px;
                    border-radius: 5px;
                    cursor: pointer;
                    margin-top: 15px;
                }
                button:hover {
                    background: #0080ff;
                }
            </style>
        </head>
        <body>
            <div class="error">
                <h3>❌ Error en el Reproductor</h3>
                <p><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
                <button onclick="history.back()">← Volver</button>
                <button onclick="location.reload()">🔄 Reintentar</button>
            </div>
        </body>
        </html>
        <?php
    }
}

$embed = new MP4Embed();
$embed->render();
?>
