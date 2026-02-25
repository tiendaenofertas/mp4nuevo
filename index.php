<?php
declare(strict_types=1);

// 1. Cargar configuración
require_once __DIR__ . "/config/config.php";

// 2. Iniciar sesión
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- CREDENCIALES ---
const USERNAME = "admin";
const PASSWORD = "xxxx"; // <--- ¡TU CONTRASEÑA AQUÍ!
const SECRET_KEY_1 = "xzorra_key_elegant_2025";
const SECRET_KEY_2 = "premium_panel_access";

$hash = hash('sha256', SECRET_KEY_1 . PASSWORD . SECRET_KEY_2);
$self = $_SERVER["REQUEST_URI"];

// Logout
if (isset($_GET["logout"])) {
    unset($_SESSION["login"]);
    header("Location: " . strtok($self, "?"));
    exit;
}

// Login Check
if (isset($_SESSION["login"]) && $_SESSION["login"] === $hash) {
    showMainPanel();
} elseif ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["submit"])) {
    $u = htmlspecialchars(trim($_POST["username"] ?? ""));
    $p = $_POST["password"] ?? "";
    
    if ($u === USERNAME && $p === PASSWORD) {
        $_SESSION["login"] = $hash;
        header("Location: " . strtok($self, "?"));
        exit;
    } else {
        showLoginForm(true);
    }
} else {
    showLoginForm();
}

// --- VISTA LOGIN ---
function showLoginForm(bool $showError = false): void {
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Acceso Seguro</title>
        <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
        <style>
            /* RESET ESTRICTO PARA EVITAR DESBORDAMIENTOS */
            *, *::before, *::after {
                margin: 0;
                padding: 0;
                box-sizing: border-box; /* Crucial: el padding no aumenta el ancho */
            }

            :root { 
                --bg-dark: #0f172a; 
                --accent: #cba32a; 
                --text: #f8fafc; 
            }

            body { 
                font-family: 'Montserrat', sans-serif; 
                background: radial-gradient(circle at top right, #1e293b, #0f172a); 
                min-height: 100vh; 
                display: flex; 
                align-items: center; 
                justify-content: center; 
                color: var(--text); 
                overflow-x: hidden; /* Evita scroll horizontal */
            }

            .login-box { 
                background: rgba(30, 41, 59, 0.7); 
                backdrop-filter: blur(20px); 
                padding: 3rem; 
                border-radius: 24px; 
                border: 1px solid rgba(255,255,255,0.1); 
                width: 90%; /* Adaptable en móviles */
                max-width: 420px; 
                box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5); 
                margin: 20px; /* Margen de seguridad */
            }

            .login-header { 
                text-align: center; 
                margin-bottom: 2.5rem; 
            }

            .login-header i { 
                font-size: 3.5rem; 
                background: linear-gradient(135deg, var(--accent), #fbf2c0); 
                -webkit-background-clip: text; 
                -webkit-text-fill-color: transparent; 
                margin-bottom: 1rem; 
            }

            .login-header h2 { 
                font-family: 'Playfair Display', serif; 
                margin: 0; 
                font-size: 1.8rem; 
            }

            .input-group { 
                position: relative; 
                margin-bottom: 1.5rem; 
                width: 100%;
            }

            .input-group i { 
                position: absolute; 
                left: 1rem; 
                top: 50%; 
                transform: translateY(-50%); 
                color: #94a3b8; 
                pointer-events: none;
            }

            input { 
                width: 100%; 
                max-width: 100%; /* Asegura que no se salga */
                padding: 1rem 1rem 1rem 3rem; 
                background: rgba(255,255,255,0.05); 
                border: 1px solid rgba(255,255,255,0.1); 
                border-radius: 12px; 
                color: white; 
                outline: none; 
                transition: 0.3s; 
                font-size: 1rem; 
            }

            input:focus { 
                border-color: var(--accent); 
                background: rgba(255,255,255,0.1); 
            }

            button { 
                width: 100%; 
                max-width: 100%;
                padding: 1rem; 
                background: linear-gradient(135deg, var(--accent), #b88a1e); 
                border: none; 
                border-radius: 12px; 
                color: white; 
                font-weight: 600; 
                cursor: pointer; 
                transition: 0.3s; 
                font-size: 1.1rem; 
                margin-top: 1rem; 
            }

            button:hover { 
                transform: translateY(-2px); 
                box-shadow: 0 10px 20px rgba(212, 175, 55, 0.3); 
            }

            .error { 
                background: rgba(239, 68, 68, 0.2); 
                color: #fca5a5; 
                padding: 1rem; 
                border-radius: 12px; 
                margin-bottom: 2rem; 
                text-align: center; 
                border: 1px solid rgba(239, 68, 68, 0.3); 
                display: flex; 
                align-items: center; 
                justify-content: center; 
                gap: 10px; 
            }
        </style>
    </head>
    <body>
        <div class="login-box">
            <div class="login-header"><i class="fas fa-shield-halved"></i><h2>Acceso Privado</h2></div>
            <?php if ($showError): ?><div class="error"><i class="fas fa-circle-exclamation"></i> Datos incorrectos</div><?php endif; ?>
            <form method="post">
                <div class="input-group"><i class="fas fa-user"></i><input type="text" name="username" placeholder="Usuario" required autocomplete="off"></div>
                <div class="input-group"><i class="fas fa-lock"></i><input type="password" name="password" placeholder="Contraseña" required></div>
                <button type="submit" name="submit">INGRESAR</button>
            </form>
        </div>
    </body>
    </html>
    <?php
}

// --- VISTA PANEL PRINCIPAL ---
function showMainPanel(): void {
    $csrfToken = function_exists('generateCSRFToken') ? generateCSRFToken() : '';
    $serverUrl = (isset($_SERVER["HTTPS"]) ? "https" : "http") . "://" . $_SERVER["SERVER_NAME"] . dirname($_SERVER["PHP_SELF"]);
    if (substr($serverUrl, -1) === '/') $serverUrl = rtrim($serverUrl, '/');
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Panel Premium</title>
        <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
        <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
        <style>
            /* RESET CORREGIDO */
            *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

            :root { --bg: #f8fafc; --nav-bg: #0f172a; --accent: #cba32a; --card: #ffffff; --text: #1e293b; }
            
            body { 
                font-family: 'Montserrat', sans-serif; 
                background: var(--bg); 
                min-height: 100vh; 
                display: flex; 
                flex-direction: column;
                overflow-x: hidden;
            }
            
            /* HEADER */
            .navbar {
                background: var(--nav-bg); 
                color: white; 
                padding: 1rem 0;
                box-shadow: 0 4px 20px rgba(0,0,0,0.1);
                position: sticky;
                top: 0;
                z-index: 100;
            }
            .nav-container {
                max-width: 1000px; 
                margin: 0 auto; 
                padding: 0 20px;
                display: flex; 
                justify-content: space-between; 
                align-items: center;
            }
            .brand { 
                display: flex; align-items: center; gap: 12px; 
                font-family: 'Playfair Display', serif; font-size: 1.5rem; color: var(--accent); 
            }
            .btn-logout {
                background: rgba(255,255,255,0.1); color: white; text-decoration: none;
                padding: 0.6rem 1.2rem; border-radius: 8px; font-size: 0.9rem; font-weight: 600;
                transition: 0.3s; display: flex; align-items: center; gap: 8px;
            }
            .btn-logout:hover { background: #ef4444; }

            /* MAIN */
            .main-container {
                max-width: 900px; 
                width: 100%; 
                margin: 40px auto; 
                padding: 0 20px;
                flex: 1;
            }
            .page-header { text-align: center; margin-bottom: 3rem; }
            .page-header h2 { font-family: 'Playfair Display', serif; font-size: 2.2rem; color: var(--text); margin-bottom: 0.5rem; }
            .page-header p { color: #64748b; font-size: 1.1rem; }

            /* CARDS */
            .card {
                background: var(--card); border-radius: 20px; padding: 2.5rem;
                box-shadow: 0 10px 30px -5px rgba(0,0,0,0.05); border: 1px solid #e2e8f0;
                margin-bottom: 2rem;
                width: 100%; /* Asegura que respete el contenedor padre */
            }
            .card-title {
                display: flex; align-items: center; gap: 10px; margin-bottom: 1.5rem;
                padding-bottom: 1rem; border-bottom: 1px solid #f1f5f9;
                font-size: 1.2rem; font-weight: 700; color: var(--text);
            }
            .card-title i { color: var(--accent); font-size: 1.4rem; }

            /* FORMS */
            .form-grid { 
                display: grid; 
                grid-template-columns: 1fr; 
                gap: 1.5rem; 
            }
            @media(min-width: 768px) { 
                .form-grid { grid-template-columns: 1fr 1fr; } 
            }
            
            label { display: block; margin-bottom: 8px; font-weight: 600; color: #334155; font-size: 0.95rem; }
            
            .input-box { 
                position: relative; 
                width: 100%;
            }
            
            .input-box i { 
                position: absolute; 
                left: 1rem; 
                top: 50%; 
                transform: translateY(-50%); 
                color: #94a3b8; 
                pointer-events: none;
            }
            
            input {
                width: 100%; 
                max-width: 100%; /* Evita desbordamiento */
                padding: 12px 12px 12px 45px;
                border: 2px solid #e2e8f0; border-radius: 12px;
                font-family: inherit; font-size: 1rem; transition: 0.3s;
                background: #f8fafc;
            }
            input:focus { border-color: var(--accent); background: white; outline: none; box-shadow: 0 0 0 4px rgba(203, 163, 42, 0.1); }

            /* BOTONES */
            .btn-add {
                background: transparent; border: 2px dashed #cbd5e1; color: #64748b;
                width: 100%; padding: 10px; border-radius: 10px; cursor: pointer;
                font-weight: 600; transition: 0.3s; margin-top: 10px;
                max-width: 100%;
            }
            .btn-add:hover { border-color: var(--accent); color: var(--accent); }

            .btn-submit {
                background: linear-gradient(135deg, var(--accent), #b08d26);
                color: white; border: none; padding: 1.2rem; width: 100%;
                border-radius: 14px; font-size: 1.2rem; font-weight: 700;
                cursor: pointer; transition: 0.3s; letter-spacing: 1px;
                box-shadow: 0 10px 25px -5px rgba(203, 163, 42, 0.4);
                display: flex; justify-content: center; align-items: center; gap: 10px;
                max-width: 100%;
            }
            .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 15px 35px -5px rgba(203, 163, 42, 0.5); }

            /* RESULTADOS */
            .results { display: none; background: #f0f9ff; border: 2px solid var(--accent); animation: fadeIn 0.5s; }
            @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
            
            textarea {
                width: 100%; 
                max-width: 100%;
                padding: 15px; border: 1px solid #cbd5e1;
                border-radius: 12px; min-height: 90px; font-family: monospace;
                resize: vertical; margin-bottom: 10px;
            }
            
            .action-row { display: flex; gap: 10px; flex-wrap: wrap; }
            
            .btn-copy {
                background: #334155; color: white; border: none; padding: 10px 20px;
                border-radius: 8px; cursor: pointer; font-weight: 600;
                display: flex; align-items: center; gap: 8px; transition: 0.3s;
            }
            .btn-copy:hover { background: #1e293b; }
            .btn-test { background: var(--accent); }
            
            footer { text-align: center; padding: 2rem; color: #94a3b8; font-size: 0.9rem; margin-top: auto; }
        </style>
    </head>
    <body>
        <nav class="navbar">
            <div class="nav-container">
                <div class="brand"><i class="fas fa-shield-halved"></i> Security Panel</div>
                <a href="?logout=true" class="btn-logout"><i class="fas fa-power-off"></i> Salir</a>
            </div>
        </nav>

        <main class="main-container">
            <div class="page-header">
                <h2>Protección de Video</h2>
                <p>Genera enlaces seguros de alta gama para tus contenidos.</p>
            </div>

            <form id="genForm">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                
                <div class="card">
                    <div class="card-title"><i class="fas fa-film"></i> Configuración Principal</div>
                    <div class="form-grid">
                        <div>
                            <label>URL del Video (MP4)</label>
                            <div class="input-box">
                                <i class="fas fa-link"></i>
                                <input type="url" name="link" required placeholder="https://ejemplo.com/video.mp4">
                            </div>
                        </div>
                        <div>
                            <label>Imagen Poster (Opcional)</label>
                            <div class="input-box">
                                <i class="fas fa-image"></i>
                                <input type="url" name="poster" placeholder="https://ejemplo.com/portada.jpg">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-title"><i class="fas fa-closed-captioning"></i> Subtítulos (CC)</div>
                    <div id="subs-container">
                        <div class="form-grid" style="margin-bottom: 1rem;">
                            <div>
                                <label>URL del Archivo (SRT/VTT)</label>
                                <div class="input-box">
                                    <i class="fas fa-file-alt"></i>
                                    <input type="url" name="sub[0]" placeholder="https://...">
                                </div>
                            </div>
                            <div>
                                <label>Etiqueta de Idioma</label>
                                <div class="input-box">
                                    <i class="fas fa-language"></i>
                                    <input type="text" name="label[0]" value="Español">
                                </div>
                            </div>
                        </div>
                    </div>
                    <button type="button" id="btnAddSub" class="btn-add"><i class="fas fa-plus"></i> Agregar otro idioma</button>
                </div>

                <button type="submit" class="btn-submit" id="btnSubmit">
                    <i class="fas fa-lock"></i> GENERAR ENLACE SEGURO
                </button>
            </form>

            <div class="card results" id="resCard">
                <div class="card-title" style="color: var(--accent); border-bottom: none;">
                    <i class="fas fa-check-circle"></i> ¡Enlaces Generados Exitosamente!
                </div>
                
                <div style="margin-bottom: 2rem;">
                    <label>URL Directa del Reproductor:</label>
                    <textarea id="outUrl" readonly></textarea>
                    <div class="action-row">
                        <button class="btn-copy" onclick="copy('outUrl', this)"><i class="fas fa-copy"></i> Copiar</button>
                        <button class="btn-copy btn-test" id="btnTest"><i class="fas fa-play"></i> Probar</button>
                    </div>
                </div>

                <div>
                    <label>Código Iframe (Embed):</label>
                    <textarea id="outIframe" readonly></textarea>
                    <button class="btn-copy" onclick="copy('outIframe', this)"><i class="fas fa-code"></i> Copiar Código</button>
                </div>
            </div>
        </main>

        <footer>
            &copy; <?= date('Y') ?> MP4 Security System. Todos los derechos reservados.
        </footer>

        <script>
            let sc = 1;
            $('#btnAddSub').click(()=>{
                $('#subs-container').append(`
                    <div class="form-grid" style="margin-top:1rem; border-top:1px dashed #e2e8f0; padding-top:1rem;">
                        <div><div class="input-box"><i class="fas fa-file-alt"></i><input type="url" name="sub[${sc}]" placeholder="URL del subtítulo"></div></div>
                        <div><div class="input-box"><i class="fas fa-language"></i><input type="text" name="label[${sc}]" placeholder="Idioma"></div></div>
                    </div>
                `);
                sc++;
            });

            $('#genForm').submit(function(e){
                e.preventDefault();
                let btn = $('#btnSubmit');
                let old = btn.html();
                btn.html('<i class="fas fa-circle-notch fa-spin"></i> PROCESANDO...').prop('disabled',true);
                $('#resCard').slideUp();

                $.ajax({
                    url: 'action.php',
                    type: 'POST',
                    data: $(this).serialize(),
                    success: function(d) {
                        btn.html(old).prop('disabled',false);
                        try {
                            let data = (typeof d === 'string') ? d : (d.data || d);
                            if(typeof d === 'object' && d.error) throw new Error(d.error);

                            let base = "<?= $serverUrl ?>";
                            let link = `${base}/embed.php?data=${encodeURIComponent(data)}`;
                            let iframe = `<iframe src="${link}" width="100%" height="100%" frameborder="0" allowfullscreen></iframe>`;

                            $('#outUrl').val(link);
                            $('#outIframe').val(iframe);
                            $('#btnTest').data('url', link);
                            $('#resCard').slideDown();
                            $('html,body').animate({scrollTop: $('#resCard').offset().top - 50}, 500);

                        } catch(err) { alert('Error: ' + err.message); }
                    },
                    error: function(xhr) {
                        btn.html(old).prop('disabled',false);
                        let msg = "Error de conexión.";
                        try { let r = JSON.parse(xhr.responseText); if(r.error) msg = r.error; } catch(e){}
                        alert(msg);
                    }
                });
            });

            function copy(id, el) {
                document.getElementById(id).select();
                document.execCommand('copy');
                let t = el.innerHTML;
                el.innerHTML = '<i class="fas fa-check"></i> Copiado';
                el.style.background = '#10b981';
                setTimeout(()=>{ el.innerHTML = t; el.style.background = ''; }, 2000);
            }

            $('#btnTest').click(function(){
                window.open($(this).data('url'), '_blank', 'width=1000,height=600');
            });
        </script>
    </body>
    </html>
    <?php
}
?>
