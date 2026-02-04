<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . "/config/config.php";

// Configuración de autenticación
const USERNAME = "admin";
const PASSWORD = "xxxx";
const SECRET_KEY_1 = "xzorra_key_2025";
const SECRET_KEY_2 = "secure_panel_key";

$hash = hash('sha256', SECRET_KEY_1 . PASSWORD . SECRET_KEY_2);
$self = $_SERVER["REQUEST_URI"];

// Manejar logout
if (isset($_GET["logout"])) {
    unset($_SESSION["login"]);
    logSecurityEvent("User logout");
    header("Location: " . strtok($self, "?"));
    exit;
}

// Verificar autenticación
if (isset($_SESSION["login"]) && $_SESSION["login"] === $hash) {
    showMainPanel();
} elseif ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["submit"])) {
    $username = sanitizeInput($_POST["username"] ?? "");
    $password = sanitizeInput($_POST["password"] ?? "");
    
    if ($username === USERNAME && $password === PASSWORD) {
        $_SESSION["login"] = $hash;
        logSecurityEvent("User login success", ['username' => $username]);
        header("Location: " . strtok($self, "?"));
        exit;
    } else {
        logSecurityEvent("Login failed", ['username' => $username]);
        showLoginForm(true);
    }
} else {
    showLoginForm();
}

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
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            
            body {
                font-family: 'Inter', sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
                box-shadow: 0 25px 50px rgba(0, 0, 0, 0.2);
                overflow: hidden;
                width: 100%;
                max-width: 400px;
                animation: slideUp 0.8s ease-out;
            }
            
            @keyframes slideUp {
                from {
                    opacity: 0;
                    transform: translateY(30px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
            
            .login-header {
                background: linear-gradient(135deg, #667eea, #764ba2);
                color: white;
                padding: 2.5rem 2rem;
                text-align: center;
                position: relative;
                overflow: hidden;
            }
            
            .login-header::before {
                content: '';
                position: absolute;
                top: -50%;
                left: -50%;
                width: 200%;
                height: 200%;
                background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
                animation: pulse 4s ease-in-out infinite;
            }
            
            @keyframes pulse {
                0%, 100% { opacity: 0.3; }
                50% { opacity: 0.1; }
            }
            
            .login-header i {
                font-size: 3rem;
                margin-bottom: 1rem;
                position: relative;
                z-index: 1;
            }
            
            .login-header h2 {
                font-weight: 600;
                margin-bottom: 0.5rem;
                position: relative;
                z-index: 1;
            }
            
            .login-header p {
                opacity: 0.9;
                font-weight: 300;
                position: relative;
                z-index: 1;
            }
            
            .login-form {
                padding: 2rem;
            }
            
            .form-group {
                margin-bottom: 1.5rem;
                position: relative;
            }
            
            .form-group label {
                display: block;
                margin-bottom: 0.5rem;
                font-weight: 500;
                color: #374151;
                font-size: 0.9rem;
            }
            
            .form-group input {
                width: 100%;
                padding: 0.75rem 1rem 0.75rem 2.5rem;
                border: 2px solid #e5e7eb;
                border-radius: 10px;
                font-size: 1rem;
                transition: all 0.3s ease;
                background: #f9fafb;
            }
            
            .form-group input:focus {
                outline: none;
                border-color: #667eea;
                background: white;
                box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            }
            
            .form-group i {
                position: absolute;
                left: 0.75rem;
                top: 2.2rem;
                color: #9ca3af;
                transition: color 0.3s ease;
            }
            
            .form-group input:focus + i {
                color: #667eea;
            }
            
            .login-btn {
                width: 100%;
                background: linear-gradient(135deg, #667eea, #764ba2);
                color: white;
                border: none;
                padding: 0.875rem 1rem;
                border-radius: 10px;
                font-size: 1rem;
                font-weight: 500;
                cursor: pointer;
                transition: all 0.3s ease;
                position: relative;
                overflow: hidden;
            }
            
            .login-btn::before {
                content: '';
                position: absolute;
                top: 0;
                left: -100%;
                width: 100%;
                height: 100%;
                background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
                transition: left 0.5s ease;
            }
            
            .login-btn:hover::before {
                left: 100%;
            }
            
            .login-btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
            }
            
            .error-alert {
                background: linear-gradient(135deg, #fee2e2, #fecaca);
                border: 1px solid #f87171;
                color: #dc2626;
                padding: 1rem;
                border-radius: 10px;
                margin-bottom: 1.5rem;
                text-align: center;
                font-weight: 500;
                animation: shake 0.5s ease-in-out;
            }
            
            @keyframes shake {
                0%, 100% { transform: translateX(0); }
                25% { transform: translateX(-5px); }
                75% { transform: translateX(5px); }
            }
            
            .footer {
                text-align: center;
                padding: 1rem 2rem 2rem;
                color: #6b7280;
                font-size: 0.85rem;
            }
        </style>
    </head>
    <body>
        <div class="login-container">
            <div class="login-header">
                <i class="fas fa-shield-alt"></i>
                <h2>MP4 Security Panel</h2>
                <p>Sistema de Protección de Videos</p>
            </div>
            
            <div class="login-form">
                <?php if ($showError): ?>
                <div class="error-alert">
                    <i class="fas fa-exclamation-triangle"></i>
                    Credenciales incorrectas
                </div>
                <?php endif; ?>
                
                <form method="post" autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    
                    <div class="form-group">
                        <label for="username">Usuario</label>
                        <input type="text" id="username" name="username" required autocomplete="off">
                        <i class="fas fa-user"></i>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Contraseña</label>
                        <input type="password" id="password" name="password" required autocomplete="new-password">
                        <i class="fas fa-lock"></i>
                    </div>
                    
                    <button type="submit" name="submit" class="login-btn">
                        <i class="fas fa-sign-in-alt"></i>
                        Iniciar Sesión
                    </button>
                </form>
            </div>
            
            <div class="footer">
                MP4 Security System v<?= MP4_VERSION ?> • <?= date('Y') ?>
            </div>
        </div>
    </body>
    </html>
    <?php
}

function showMainPanel(): void {
    $csrfToken = generateCSRFToken();
    $domainServer = (isset($_SERVER["HTTPS"]) ? "https" : "http") . 
                   "://" . $_SERVER["SERVER_NAME"] . 
                   dirname($_SERVER["PHP_SELF"]);
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>MP4 Security Panel</title>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            
            body {
                font-family: 'Inter', sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                padding: 20px;
            }
            
            .container {
                max-width: 1200px;
                margin: 0 auto;
            }
            
            .panel-header {
                background: rgba(255, 255, 255, 0.95);
                backdrop-filter: blur(20px);
                border-radius: 20px 20px 0 0;
                padding: 2rem;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
                display: flex;
                justify-content: space-between;
                align-items: center;
                flex-wrap: wrap;
                gap: 1rem;
            }
            
            .panel-title {
                display: flex;
                align-items: center;
                gap: 1rem;
            }
            
            .panel-title i {
                font-size: 2rem;
                color: #667eea;
            }
            
            .panel-title h1 {
                font-size: 2rem;
                font-weight: 700;
                color: #1f2937;
                margin-bottom: 0.5rem;
            }
            
            .panel-title p {
                color: #6b7280;
                font-weight: 400;
            }
            
            .panel-actions {
                display: flex;
                gap: 1rem;
                align-items: center;
            }
            
            .status-indicator {
                display: flex;
                align-items: center;
                gap: 0.5rem;
                padding: 0.5rem 1rem;
                background: linear-gradient(135deg, #10b981, #059669);
                color: white;
                border-radius: 25px;
                font-size: 0.9rem;
                font-weight: 500;
            }
            
            .status-dot {
                width: 8px;
                height: 8px;
                background: white;
                border-radius: 50%;
                animation: pulse 2s infinite;
            }
            
            .logout-btn {
                background: linear-gradient(135deg, #ef4444, #dc2626);
                color: white;
                text-decoration: none;
                padding: 0.75rem 1.5rem;
                border-radius: 10px;
                font-weight: 500;
                transition: all 0.3s ease;
                display: flex;
                align-items: center;
                gap: 0.5rem;
            }
            
            .logout-btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 10px 25px rgba(239, 68, 68, 0.3);
            }
            
            .main-content {
                background: rgba(255, 255, 255, 0.95);
                backdrop-filter: blur(20px);
                border-radius: 0 0 20px 20px;
                padding: 2rem;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            }
            
            .form-section {
                background: white;
                border-radius: 15px;
                padding: 2rem;
                margin-bottom: 2rem;
                box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
                border: 1px solid #f3f4f6;
            }
            
            .section-header {
                display: flex;
                align-items: center;
                gap: 1rem;
                margin-bottom: 1.5rem;
                padding-bottom: 1rem;
                border-bottom: 2px solid #f3f4f6;
            }
            
            .section-header i {
                font-size: 1.5rem;
                color: #667eea;
            }
            
            .section-header h3 {
                font-size: 1.25rem;
                font-weight: 600;
                color: #1f2937;
            }
            
            .form-grid {
                display: grid;
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }
            
            .form-group {
                position: relative;
            }
            
            .form-group label {
                display: block;
                margin-bottom: 0.5rem;
                font-weight: 500;
                color: #374151;
                font-size: 0.9rem;
            }
            
            .form-group input {
                width: 100%;
                padding: 0.875rem 1rem;
                border: 2px solid #e5e7eb;
                border-radius: 10px;
                font-size: 1rem;
                transition: all 0.3s ease;
                background: #f9fafb;
            }
            
            .form-group input:focus {
                outline: none;
                border-color: #667eea;
                background: white;
                box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            }
            
            .subtitle-grid {
                display: grid;
                grid-template-columns: 1fr auto;
                gap: 1rem;
                align-items: end;
            }
            
            .add-subtitle-btn {
                background: linear-gradient(135deg, #10b981, #059669);
                color: white;
                border: none;
                padding: 0.875rem 1rem;
                border-radius: 10px;
                cursor: pointer;
                font-weight: 500;
                transition: all 0.3s ease;
                white-space: nowrap;
            }
            
            .add-subtitle-btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 5px 15px rgba(16, 185, 129, 0.3);
            }
            
            .generate-btn {
                background: linear-gradient(135deg, #667eea, #764ba2);
                color: white;
                border: none;
                padding: 1rem 2rem;
                border-radius: 12px;
                font-size: 1.1rem;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.3s ease;
                width: 100%;
                margin-top: 1rem;
                position: relative;
                overflow: hidden;
            }
            
            .generate-btn::before {
                content: '';
                position: absolute;
                top: 0;
                left: -100%;
                width: 100%;
                height: 100%;
                background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
                transition: left 0.5s ease;
            }
            
            .generate-btn:hover::before {
                left: 100%;
            }
            
            .generate-btn:hover {
                transform: translateY(-3px);
                box-shadow: 0 15px 35px rgba(102, 126, 234, 0.4);
            }
            
            .results-section {
                background: linear-gradient(135deg, #f0f9ff, #e0f2fe);
                border: 2px solid #0ea5e9;
                border-radius: 15px;
                padding: 2rem;
                margin-top: 2rem;
            }
            
            .result-group {
                margin-bottom: 1.5rem;
            }
            
            .result-group label {
                display: block;
                margin-bottom: 0.5rem;
                font-weight: 600;
                color: #0c4a6e;
            }
            
            .result-group textarea,
            .result-group input {
                width: 100%;
                padding: 1rem;
                border: 2px solid #7dd3fc;
                border-radius: 10px;
                font-family: 'Monaco', 'Menlo', monospace;
                font-size: 0.9rem;
                background: white;
                resize: vertical;
                min-height: 80px;
            }
            
            .copy-btn {
                background: linear-gradient(135deg, #0ea5e9, #0284c7);
                color: white;
                border: none;
                padding: 0.5rem 1rem;
                border-radius: 8px;
                cursor: pointer;
                font-size: 0.9rem;
                font-weight: 500;
                margin-top: 0.5rem;
                transition: all 0.3s ease;
            }
            
            .copy-btn:hover {
                background: linear-gradient(135deg, #0284c7, #0369a1);
                transform: translateY(-1px);
            }
            
            .test-examples {
                background: linear-gradient(135deg, #fef3c7, #fde68a);
                border: 2px solid #f59e0b;
                border-radius: 15px;
                padding: 2rem;
                margin-top: 2rem;
            }
            
            .examples-header {
                display: flex;
                align-items: center;
                gap: 1rem;
                margin-bottom: 1rem;
                color: #92400e;
            }
            
            .examples-header i {
                font-size: 1.5rem;
            }
            
            .examples-header h4 {
                font-size: 1.2rem;
                font-weight: 600;
            }
            
            .example-url {
                background: white;
                padding: 0.75rem;
                border-radius: 8px;
                margin: 0.5rem 0;
                font-family: monospace;
                font-size: 0.9rem;
                border: 1px solid #d97706;
                word-break: break-all;
            }
            
            .loading {
                display: none;
                text-align: center;
                padding: 2rem;
            }
            
            .spinner {
                border: 3px solid #f3f4f6;
                border-top: 3px solid #667eea;
                border-radius: 50%;
                width: 40px;
                height: 40px;
                animation: spin 1s linear infinite;
                margin: 0 auto 1rem;
            }
            
            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
            
            .success-message {
                background: linear-gradient(135deg, #d1fae5, #a7f3d0);
                border: 2px solid #10b981;
                color: #065f46;
                padding: 1rem;
                border-radius: 10px;
                margin: 1rem 0;
                font-weight: 500;
                text-align: center;
            }
            
            .error-message {
                background: linear-gradient(135deg, #fee2e2, #fecaca);
                border: 2px solid #ef4444;
                color: #991b1b;
                padding: 1rem;
                border-radius: 10px;
                margin: 1rem 0;
                font-weight: 500;
                text-align: center;
            }
            
            @media (max-width: 768px) {
                .panel-header {
                    flex-direction: column;
                    text-align: center;
                }
                
                .panel-actions {
                    width: 100%;
                    justify-content: center;
                }
                
                .subtitle-grid {
                    grid-template-columns: 1fr;
                }
            }
        </style>
        <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    </head>
    <body>
        <div class="container">
            <div class="panel-header">
                <div class="panel-title">
                    <i class="fas fa-shield-alt"></i>
                    <div>
                        <h1>MP4 Security Panel</h1>
                        <p>Sistema Avanzado de Protección de Videos</p>
                    </div>
                </div>
                <div class="panel-actions">
                    <div class="status-indicator">
                        <div class="status-dot"></div>
                        Sistema Activo
                    </div>
                    <a href="?logout=true" class="logout-btn">
                        <i class="fas fa-sign-out-alt"></i>
                        Cerrar Sesión
                    </a>
                </div>
            </div>
            
            <div class="main-content">
                <form id="video-form">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    
                    <div class="form-section">
                        <div class="section-header">
                            <i class="fas fa-video"></i>
                            <h3>Información del Video</h3>
                        </div>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="video-url">
                                    <i class="fas fa-link"></i>
                                    URL del Video MP4 *
                                </label>
                                <input type="url" id="video-url" name="link" 
                                       placeholder="https://ejemplo.com/video.mp4" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="poster-url">
                                    <i class="fas fa-image"></i>
                                    URL del Poster (opcional)
                                </label>
                                <input type="url" id="poster-url" name="poster" 
                                       placeholder="https://ejemplo.com/poster.jpg">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <div class="section-header">
                            <i class="fas fa-closed-captioning"></i>
                            <h3>Subtítulos</h3>
                        </div>
                        
                        <div id="subtitles-container">
                            <div class="subtitle-grid">
                                <div class="form-group">
                                    <label>URL del Subtítulo</label>
                                    <input type="url" name="sub[0]" 
                                           placeholder="https://ejemplo.com/subtitulo.srt">
                                </div>
                                <div class="form-group">
                                    <label>Idioma</label>
                                    <input type="text" name="label[0]" 
                                           placeholder="Español" value="Español">
                                </div>
                            </div>
                        </div>
                        
                        <button type="button" id="add-subtitle" class="add-subtitle-btn">
                            <i class="fas fa-plus"></i>
                            Agregar Subtítulo
                        </button>
                    </div>
                    
                    <button type="submit" class="generate-btn">
                        <i class="fas fa-lock"></i>
                        Generar URL Protegida
                    </button>
                </form>
                
                <div class="loading" id="loading">
                    <div class="spinner"></div>
                    <p>Generando URL protegida...</p>
                </div>
                
                <div class="results-section" id="results" style="display: none;">
                    <div class="section-header">
                        <i class="fas fa-check-circle"></i>
                        <h3>URLs Generadas</h3>
                    </div>
                    
                    <div class="result-group">
                        <label>URL Protegida del Reproductor:</label>
                        <textarea id="embed-url" readonly></textarea>
                        <button type="button" class="copy-btn" onclick="copyToClipboard('embed-url')">
                            <i class="fas fa-copy"></i> Copiar URL
                        </button>
                    </div>
                    
                    <div class="result-group">
                        <label>Código iframe para Embeber:</label>
                        <textarea id="iframe-code" readonly></textarea>
                        <button type="button" class="copy-btn" onclick="copyToClipboard('iframe-code')">
                            <i class="fas fa-copy"></i> Copiar Código
                        </button>
                    </div>
                    
                    <div style="text-align: center; margin-top: 1.5rem;">
                        <button type="button" id="test-player" class="generate-btn" style="width: auto; padding: 1rem 2rem;">
                            <i class="fas fa-play"></i>
                            Probar Reproductor
                        </button>
                    </div>
                </div>
                
                <div class="test-examples">
                    <div class="examples-header">
                        <i class="fas fa-flask"></i>
                        <h4>URLs de Prueba</h4>
                    </div>
                    <p><strong>Video de ejemplo:</strong></p>
                    <div class="example-url">https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4</div>
                    
                    <p><strong>Poster de ejemplo:</strong></p>
                    <div class="example-url">https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/images/BigBuckBunny.jpg</div>
                    
                    <p><strong>Tu video de prueba:</strong></p>
                    <div class="example-url">https://earnvids.xzorra.net/s3-proxy.php?v=gjYh8pWdEU</div>
                </div>
            </div>
        </div>

        <script>
            let subtitleCount = 1;
            
            // Agregar nuevo campo de subtítulo
            $('#add-subtitle').click(function() {
                const container = $('#subtitles-container');
                const newSubtitle = `
                    <div class="subtitle-grid" style="margin-top: 1rem;">
                        <div class="form-group">
                            <input type="url" name="sub[${subtitleCount}]" 
                                   placeholder="https://ejemplo.com/subtitulo.srt">
                        </div>
                        <div class="form-group">
                            <input type="text" name="label[${subtitleCount}]" 
                                   placeholder="Idioma ${subtitleCount + 1}">
                        </div>
                    </div>
                `;
                container.append(newSubtitle);
                subtitleCount++;
            });
            
            // Manejar envío del formulario
            $('#video-form').on('submit', function(e) {
                e.preventDefault();
                
                $('#loading').show();
                $('#results').hide();
                $('.success-message, .error-message').remove();
                
                $.ajax({
                    type: 'POST',
                    url: 'src/Action.php',
                    data: $(this).serialize(),
                    dataType: 'text',
                    success: function(result) {
                        $('#loading').hide();
                        
                        if (result.startsWith('{') && result.includes('error')) {
                            const error = JSON.parse(result);
                            showMessage('Error: ' + error.error, 'error');
                        } else {
                            const baseUrl = "<?= htmlspecialchars($domainServer) ?>";
                            const embedUrl = baseUrl + "/embed.php?data=" + encodeURIComponent(result);
                            const iframeCode = `<iframe src="${embedUrl}" width="100%" height="500" frameborder="0" allowfullscreen></iframe>`;
                            
                            $('#embed-url').val(embedUrl);
                            $('#iframe-code').val(iframeCode);
                            $('#results').show();
                            $('#test-player').data('url', embedUrl);
                            
                            showMessage('¡URL protegida generada exitosamente!', 'success');
                        }
                    },
                    error: function(xhr) {
                        $('#loading').hide();
                        showMessage('Error del servidor: ' + xhr.responseText, 'error');
                    }
                });
            });
            
            // Probar reproductor
            $('#test-player').click(function() {
                const url = $(this).data('url');
                if (url) {
                    window.open(url, '_blank', 'width=1000,height=600');
                }
            });
            
            // Copiar al portapapeles
            function copyToClipboard(elementId) {
                const element = document.getElementById(elementId);
                element.select();
                element.setSelectionRange(0, 99999);
                
                navigator.clipboard.writeText(element.value).then(function() {
                    showMessage('¡Copiado al portapapeles!', 'success');
                }).catch(function() {
                    document.execCommand('copy');
                    showMessage('¡Copiado al portapapeles!', 'success');
                });
            }
            
            // Mostrar mensajes
            function showMessage(message, type) {
                $('.success-message, .error-message').remove();
                
                const messageClass = type === 'success' ? 'success-message' : 'error-message';
                const messageHtml = `<div class="${messageClass}">${message}</div>`;
                
                $('#results').before(messageHtml);
                
                setTimeout(() => {
                    $('.' + messageClass).fadeOut();
                }, 5000);
            }
            
            // Llenar campos con URL de ejemplo
            $('.example-url').click(function() {
                const url = $(this).text();
                if (url.includes('.mp4')) {
                    $('#video-url').val(url);
                } else if (url.includes('.jpg') || url.includes('.png')) {
                    $('#poster-url').val(url);
                } else {
                    $('#video-url').val(url);
                }
            });
        </script>
    </body>
    </html>
    <?php
}
?>
