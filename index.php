<?php
declare(strict_types=1);

/**
 * MP4 Security System - Panel de Control Corregido
 * Implementa seguridad OWASP y protección de sesión.
 */

// Asegurar que la sesión esté iniciada con parámetros seguros
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/config/config.php";
require_once __DIR__ . "/config/library.php";

// Configuración de autenticación (Credenciales originales preservadas)
const USERNAME = "admin";
const PASSWORD = "mp4secure2025";
const SECRET_KEY_1 = "xzorra_key_2025";
const SECRET_KEY_2 = "secure_panel_key";

// Generación del hash de validación
$hash = hash('sha256', SECRET_KEY_1 . PASSWORD . SECRET_KEY_2);
$self = $_SERVER["REQUEST_URI"] ?? '/index.php';

// Manejar logout de forma segura
if (isset($_GET["logout"])) {
    logSecurityEvent("Cierre de sesión manual", ['id' => session_id()]);
    $_SESSION = array();
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
    header("Location: " . strtok($self, "?"));
    exit;
}

// Lógica Principal de Autenticación
if (isset($_SESSION["login"]) && $_SESSION["login"] === $hash) {
    // Usuario autenticado correctamente
    showMainPanel();
} elseif ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["submit"])) {
    
    // 1. VALIDACIÓN CSRF (Crítico)
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!validateCSRFToken($submittedToken)) {
        logSecurityEvent("Intento de ataque CSRF detectado en Login");
        http_response_code(403);
        die("Error de seguridad: Solicitud no autorizada.");
    }

    // 2. SANEAMIENTO Y VALIDACIÓN DE CREDENCIALES
    $username = sanitizeInput($_POST["username"] ?? "");
    $password = $_POST["password"] ?? ""; // No sanitizar para permitir caracteres especiales
    
    if ($username === USERNAME && $password === PASSWORD) {
        // 3. PROTECCIÓN CONTRA SESSION FIXATION
        session_regenerate_id(true);
        
        $_SESSION["login"] = $hash;
        $_SESSION["last_activity"] = time();
        
        logSecurityEvent("Inicio de sesión exitoso", ['user' => $username]);
        header("Location: " . strtok($self, "?"));
        exit;
    } else {
        logSecurityEvent("Fallo de autenticación", ['user' => $username]);
        showLoginForm(true);
    }
} else {
    // Mostrar formulario por defecto
    showLoginForm();
}

/**
 * Renderiza el formulario de acceso con protecciones visuales y de seguridad.
 */
function showLoginForm(bool $showError = false): void {
    $csrfToken = generateCSRFToken();
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>MP4 Security Panel - Login</title>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body {
                font-family: 'Inter', sans-serif;
                background: linear-gradient(135deg, #1a1c2c 0%, #421959 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
            }
            .login-container {
                background: rgba(255, 255, 255, 0.95);
                backdrop-filter: blur(20px);
                border-radius: 20px;
                box-shadow: 0 25px 50px rgba(0, 0, 0, 0.4);
                overflow: hidden;
                width: 100%;
                max-width: 400px;
                animation: slideUp 0.6s ease-out;
            }
            @keyframes slideUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
            .login-header {
                background: linear-gradient(135deg, #667eea, #764ba2);
                color: white;
                padding: 2.5rem 2rem;
                text-align: center;
            }
            .login-header i { font-size: 3rem; margin-bottom: 1rem; }
            .login-form { padding: 2rem; }
            .form-group { margin-bottom: 1.5rem; position: relative; }
            .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 500; color: #374151; font-size: 0.9rem; }
            .form-group input {
                width: 100%;
                padding: 0.75rem 1rem 0.75rem 2.5rem;
                border: 2px solid #e5e7eb;
                border-radius: 10px;
                transition: all 0.3s ease;
            }
            .form-group input:focus { outline: none; border-color: #667eea; box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1); }
            .form-group i { position: absolute; left: 0.75rem; top: 2.2rem; color: #9ca3af; }
            .login-btn {
                width: 100%;
                background: linear-gradient(135deg, #667eea, #764ba2);
                color: white;
                border: none;
                padding: 0.875rem;
                border-radius: 10px;
                font-weight: 600;
                cursor: pointer;
                transition: transform 0.2s;
            }
            .login-btn:hover { transform: translateY(-2px); }
            .error-alert {
                background: #fee2e2;
                border: 1px solid #f87171;
                color: #dc2626;
                padding: 0.75rem;
                border-radius: 10px;
                margin-bottom: 1.5rem;
                text-align: center;
                font-size: 0.9rem;
            }
            .footer { text-align: center; padding-bottom: 2rem; color: #6b7280; font-size: 0.8rem; }
        </style>
    </head>
    <body>
        <div class="login-container">
            <div class="login-header">
                <i class="fas fa-user-shield"></i>
                <h2>Acceso Seguro</h2>
                <p>MP4 Protection System</p>
            </div>
            
            <div class="login-form">
                <?php if ($showError): ?>
                <div class="error-alert">
                    <i class="fas fa-times-circle"></i> Credenciales no válidas.
                </div>
                <?php endif; ?>
                
                <form method="post" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    
                    <div class="form-group">
                        <label for="username">Nombre de Usuario</label>
                        <i class="fas fa-user"></i>
                        <input type="text" id="username" name="username" required autofocus>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Contraseña</label>
                        <i class="fas fa-lock"></i>
                        <input type="password" id="password" name="password" required>
                    </div>
                    
                    <button type="submit" name="submit" class="login-btn">
                        <i class="fas fa-sign-in-alt"></i> Entrar al Panel
                    </button>
                </form>
            </div>
            <div class="footer">
                &copy; <?= date('Y') ?> MP4 Security Engine v<?= MP4_VERSION ?>
            </div>
        </div>
    </body>
    </html>
    <?php
}

/**
 * Renderiza el Panel Principal. 
 * Nota: En sistemas de producción, este contenido debería estar en archivos de plantilla separados.
 */
function showMainPanel(): void {
    $csrfToken = generateCSRFToken();
    $domainServer = (isset($_SERVER["HTTPS"]) ? "https" : "http") . "://" . $_SERVER["SERVER_NAME"] . dirname($_SERVER["PHP_SELF"]);
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Panel de Administración - MP4 Security</title>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { font-family: 'Inter', sans-serif; background: #f3f4f6; color: #1f2937; }
            .nav-bar { background: #fff; padding: 1rem 2rem; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
            .container { max-width: 1000px; margin: 2rem auto; padding: 0 1rem; }
            .card { background: #fff; border-radius: 12px; padding: 2rem; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); margin-bottom: 2rem; }
            .btn-logout { color: #ef4444; text-decoration: none; font-weight: 600; font-size: 0.9rem; }
            /* ... Estilos originales adicionales ... */
        </style>
    </head>
    <body>
        <div class="nav-bar">
            <div style="font-weight: 700; font-size: 1.2rem; color: #4f46e5;">
                <i class="fas fa-shield-alt"></i> MP4 Admin
            </div>
            <a href="?logout=true" class="btn-logout">
                <i class="fas fa-power-off"></i> Cerrar Sesión
            </a>
        </div>

        <div class="container">
            <div class="card">
                <h2 style="margin-bottom: 1.5rem;"><i class="fas fa-link"></i> Generador de Enlaces Protegidos</h2>
                <form id="generatorForm">
                    <input type="hidden" name="csrf_token" id="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <p style="color: #6b7280; font-size: 0.9rem;">Utiliza el formulario para encriptar tus URLs de MP4 de forma segura.</p>
                </form>
            </div>
        </div>

        <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
        <script src="assets/js/apicodes.min.js"></script>
    </body>
    </html>
    <?php
}
