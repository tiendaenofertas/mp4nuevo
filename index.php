<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . "/config/config.php";

const USERNAME = "user";
const PASSWORD = "admin";
const SECRET_KEY_1 = "secret_key1_2025";
const SECRET_KEY_2 = "secret_key2_2025";

$hash = md5(SECRET_KEY_1 . PASSWORD . SECRET_KEY_2);
$self = $_SERVER["REQUEST_URI"];

if (isset($_GET["logout"])) {
    unset($_SESSION["login"]);
    header("Location: " . strtok($self, "?"));
    exit;
}

if (isset($_SESSION["login"]) && $_SESSION["login"] === $hash) {
    showMainPanel();
} elseif ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["submit"])) {
    $username = sanitizeInput($_POST["username"] ?? "");
    $password = sanitizeInput($_POST["password"] ?? "");
    
    if ($username === USERNAME && $password === PASSWORD) {
        $_SESSION["login"] = $hash;
        header("Location: " . strtok($self, "?"));
        exit;
    } else {
        showLoginForm(true);
    }
} else {
    showLoginForm();
}

function showLoginForm(bool $showError = false): void {
    setSecurityHeaders();
    $csrfToken = generateCSRFToken();
    $self = $_SERVER["REQUEST_URI"];
    
    include __DIR__ . "/templates/login.php";
}

function showMainPanel(): void {
    setSecurityHeaders();
    $csrfToken = generateCSRFToken();
    $domainServer = (isset($_SERVER["HTTPS"]) ? "https" : "http") . 
                   "://" . $_SERVER["SERVER_NAME"] . 
                   dirname($_SERVER["PHP_SELF"]);
    
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <title>Panel MP4 - Encriptador</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
        <style>
            body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
            .main-container { background: rgba(255,255,255,0.95); border-radius: 20px; box-shadow: 0 15px 35px rgba(0,0,0,0.1); margin: 2rem auto; }
            .panel-header { background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 2rem; border-radius: 20px 20px 0 0; }
        </style>
        <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    </head>
    <body>
        <div class="container">
            <div class="main-container">
                <div class="panel-header text-center">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <i class="fas fa-video fa-2x mb-2"></i>
                            <h2>Panel MP4</h2>
                            <p class="mb-0">Sistema de Encriptación v2.0</p>
                        </div>
                        <a href="?logout=true" class="btn btn-outline-light">
                            <i class="fas fa-sign-out-alt me-2"></i>Cerrar Sesión
                        </a>
                    </div>
                </div>
                
                <div class="p-4">
                    <form id="action-form" action="src/Action.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        
                        <div class="card mb-4">
                            <div class="card-header bg-primary text-white">
                                <i class="fas fa-link me-2"></i>Información del Video
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label class="form-label fw-bold">
                                        <i class="fas fa-play-circle me-2"></i>URL del Video MP4:
                                    </label>
                                    <input type="url" name="link" class="form-control" 
                                           placeholder="https://ejemplo.com/video.mp4" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label fw-bold">
                                        <i class="fas fa-image me-2"></i>URL de la Imagen (Poster):
                                    </label>
                                    <input type="url" name="poster" class="form-control" 
                                           placeholder="https://ejemplo.com/poster.jpg">
                                </div>
                            </div>
                        </div>
                        
                        <div class="card mb-4">
                            <div class="card-header bg-success text-white">
                                <i class="fas fa-closed-captioning me-2"></i>Subtítulos
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <input type="url" class="form-control" name="sub[0]" 
                                               placeholder="https://ejemplo.com/subtitulo.srt">
                                    </div>
                                    <div class="col-md-6">
                                        <input type="text" class="form-control" name="label[0]" 
                                               placeholder="Español" value="Español">
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="text-center mb-4">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-lock me-2"></i>Encriptar URL
                            </button>
                        </div>
                    </form>
                    
                    <div class="card">
                        <div class="card-header bg-info text-white">
                            <i class="fas fa-code me-2"></i>Resultados
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label fw-bold">URL Encriptada:</label>
                                <textarea id="url-encode" class="form-control" rows="2" readonly 
                                          placeholder="La URL encriptada aparecerá aquí..."></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label fw-bold">Código Iframe:</label>
                                <textarea id="iframe-encode" class="form-control" rows="3" readonly 
                                          placeholder="El código iframe aparecerá aquí..."></textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script>
            $("#action-form").on("submit", function(e) {
                e.preventDefault();
                
                $.ajax({
                    type: "POST",
                    url: $(this).attr("action"),
                    data: $(this).serialize(),
                    dataType: "text",
                    success: function(result) {
                        if (result.startsWith("{") && result.includes("error")) {
                            const error = JSON.parse(result);
                            alert("Error: " + error.error);
                        } else {
                            const baseUrl = "<?= htmlspecialchars($domainServer) ?>";
                            $("#url-encode").val(baseUrl + "/embed.php?data=" + result);
                            $("#iframe-encode").val("<iframe src=\"" + baseUrl + "/embed.php?data=" + result + "\" width=\"100%\" height=\"500\" frameborder=\"0\" allowfullscreen></iframe>");
                            alert("¡URL encriptada correctamente!");
                        }
                    },
                    error: function() {
                        alert("Error al procesar la solicitud");
                    }
                });
            });
        </script>
    </body>
    </html>
    <?php
}
?>
