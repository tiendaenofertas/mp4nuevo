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

        $decoded = decode($this->data);
        if ($decoded === false) {
            $this->showError("Error de decodificación");
            return;
        }

        try {
            $this->videoData = json_decode($decoded, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $this->showError("Datos JSON inválidos");
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
        
        $domainServer = (isset($_SERVER["HTTPS"]) ? "https" : "http") . 
                       "://" . $_SERVER["SERVER_NAME"] . 
                       dirname($_SERVER["PHP_SELF"]);
        
        $streamUrl = $domainServer . "/stream/?data=" . urlencode(encode($link));
        
        $sources = json_encode([
            ["label" => "HD", "type" => "mp4", "file" => $streamUrl]
        ], JSON_THROW_ON_ERROR);

        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>MP4 Premium Player</title>
            <meta name="robots" content="noindex">
            
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
                }
                #apicodes-player {
                    width: 100% !important;
                    height: 100% !important;
                    background-color: #000;
                }
            </style>
        </head>
        <body>
            <div id="apicodes-player"></div>

            <script>
                document.addEventListener("contextmenu", function(e) {
                    e.preventDefault();
                });
                
                document.addEventListener("keydown", function(e) {
                    if (e.keyCode === 123 || 
                        (e.ctrlKey && e.shiftKey && e.keyCode === 73) ||
                        (e.ctrlKey && e.keyCode === 85)) {
                        e.preventDefault();
                        return false;
                    }
                });
                
                const playerConfig = {
                    sources: <?= $sources ?>,
                    width: "100%",
                    height: "100%",
                    primary: "html5",
                    fullscreen: true,
                    autostart: false,
                    preload: "auto",
                    <?php if (!empty($poster)): ?>
                    image: "<?= htmlspecialchars($poster) ?>",
                    <?php endif; ?>
                    skin: {
                        name: "netflix"
                    },
                    captions: {
                        color: "#f3f368",
                        fontSize: 16,
                        backgroundOpacity: 0,
                        fontfamily: "Helvetica",
                        edgeStyle: "raised"
                    }
                    <?php if (!empty($tracks)): ?>
                    ,tracks: [<?= $tracks ?>]
                    <?php endif; ?>
                };
                
                document.addEventListener("DOMContentLoaded", function() {
                    const player = jwplayer("apicodes-player");
                    player.setup(playerConfig);
                    
                    player.on("ready", function() {
                        console.log("Reproductor listo");
                    });
                    
                    player.on("error", function(e) {
                        console.error("Error del reproductor:", e);
                    });
                });
            </script>
        </body>
        </html>
        <?php
    }

    private function generateSubtitleTracks(array $subtitles): string {
        $tracks = [];
        
        foreach ($subtitles as $label => $url) {
            if (validateUrl($url)) {
                $tracks[] = sprintf(
                    '{ file: "%s", label: "%s", kind: "captions" }',
                    addslashes($url),
                    addslashes($label)
                );
            }
        }

        return implode(",", $tracks);
    }

    private function show404(): void {
        http_response_code(404);
        echo "<h1>404 - Página No Encontrada</h1>";
    }

    private function showError(string $message): void {
        http_response_code(400);
        echo json_encode(["error" => $message]);
    }
}

$embed = new MP4Embed();
$embed->render();
?>