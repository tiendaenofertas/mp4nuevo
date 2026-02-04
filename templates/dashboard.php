<?php
// Este archivo es incluido por index.php y hereda sus variables ($csrfToken, $domainServer)
if (!isset($csrfToken)) {
    die("Acceso denegado: Error de contexto de seguridad.");
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MP4 Security Panel - Dashboard</title>
    <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken) ?>">
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <style>
        :root {
            --primary: #667eea;
            --secondary: #764ba2;
            --accent: #e50914;
            --bg-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: #f8f9fa;
            min-height: 100vh;
        }
        
        .nav-custom {
            background: #fff;
            box-shadow: 0 2px 15px rgba(0,0,0,0.05);
            padding: 1rem 2rem;
        }
        
        .panel-card {
            background: white;
            border-radius: 20px;
            border: none;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            margin-top: 2rem;
            padding: 2.5rem;
        }
        
        .section-title {
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .form-label {
            font-weight: 600;
            font-size: 0.85rem;
            color: #4a5568;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .form-control {
            border-radius: 12px;
            padding: 0.8rem 1rem;
            border: 2px solid #edf2f7;
            transition: all 0.2s;
        }
        
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }
        
        .btn-generate {
            background: var(--bg-gradient);
            color: white;
            border: none;
            border-radius: 12px;
            padding: 1rem;
            font-weight: 700;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .btn-generate:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
            color: white;
        }
        
        .result-box {
            background: #f1f5f9;
            border-radius: 12px;
            padding: 1.5rem;
            display: none;
            border-left: 5px solid var(--primary);
            margin-top: 2rem;
        }
        
        .sub-entry {
            background: #f8fafc;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
            border: 1px dashed #cbd5e0;
        }

        .status-badge {
            font-size: 0.75rem;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            background: #e6fffa;
            color: #2c7a7b;
            font-weight: 600;
        }
    </style>
</head>
<body>

<nav class="nav-custom d-flex justify-content-between align-items-center">
    <div class="d-flex align-items-center gap-2">
        <i class="fas fa-shield-alt text-primary fa-lg"></i>
        <span class="fw-bold fs-5">MP4 Security Console</span>
        <span class="status-badge ms-3"><i class="fas fa-circle me-1 small"></i> Sistema Protegido</span>
    </div>
    <div class="d-flex gap-3">
        <a href="console.php" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
            <i class="fas fa-terminal me-1"></i> Consola
        </a>
        <a href="?logout=true" class="btn btn-danger btn-sm rounded-pill px-3">
            <i class="fas fa-sign-out-alt me-1"></i> Salir
        </a>
    </div>
</nav>

<div class="container pb-5">
    <div class="panel-card">
        <h2 class="section-title">
            <i class="fas fa-plus-circle text-primary"></i>
            Generar Nuevo Enlace Protegido
        </h2>
        
        <form id="generatorForm">
            <div class="row g-4">
                <div class="col-md-8">
                    <label class="form-label">URL del Video (MP4)</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-0"><i class="fas fa-link"></i></span>
                        <input type="url" name="link" class="form-control" placeholder="https://ejemplo.com/video.mp4" required>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <label class="form-label">Imagen de Portada (Poster)</label>
                    <input type="url" name="poster" class="form-control" placeholder="URL opcional">
                </div>

                <div class="col-12 mt-4">
                    <h5 class="form-label mb-3"><i class="fas fa-closed-captioning me-2"></i>Subtítulos</h5>
                    <div id="subContainer">
                        <div class="sub-entry">
                            <div class="row g-2">
                                <div class="col-4">
                                    <input type="text" name="label[]" class="form-control form-control-sm" placeholder="Idioma (Ej: Español)">
                                </div>
                                <div class="col-8">
                                    <input type="url" name="sub[]" class="form-control form-control-sm" placeholder="URL del archivo .srt o .vtt">
                                </div>
                            </div>
                        </div>
                    </div>
                    <button type="button" onclick="addSub()" class="btn btn-link btn-sm p-0 text-decoration-none">
                        <i class="fas fa-plus me-1"></i> Agregar otra pista
                    </button>
                </div>

                <div class="col-12 mt-5">
                    <button type="submit" class="btn btn-generate w-100 py-3">
                        <i class="fas fa-lock me-2"></i> ENCRIPTAR Y GENERAR ENLACE
                    </button>
                </div>
            </div>
        </form>

        <div id="resultBox" class="result-box">
            <label class="form-label text-primary">Enlace Protegido Generado:</label>
            <div class="input-group mb-3">
                <input type="text" id="finalLink" class="form-control bg-white" readonly>
                <button class="btn btn-primary" onclick="copyLink()">
                    <i class="fas fa-copy"></i>
                </button>
            </div>
            <div class="d-flex gap-2">
                <a href="#" id="previewBtn" target="_blank" class="btn btn-sm btn-outline-dark">
                    <i class="fas fa-play me-1"></i> Previsualizar
                </a>
                <small class="text-muted ms-auto">Cifrado compatible con enlaces antiguos.</small>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script>
    const baseUrl = <?= json_encode($domainServer) ?>;

    function addSub() {
        const html = `
            <div class="sub-entry animate__animated animate__fadeIn">
                <div class="row g-2">
                    <div class="col-4"><input type="text" name="label[]" class="form-control form-control-sm" placeholder="Idioma"></div>
                    <div class="col-7"><input type="url" name="sub[]" class="form-control form-control-sm" placeholder="URL del subtítulo"></div>
                    <div class="col-1 d-flex align-items-center">
                        <button type="button" onclick="$(this).closest('.sub-entry').remove()" class="btn btn-sm text-danger"><i class="fas fa-trash"></i></button>
                    </div>
                </div>
            </div>`;
        $('#subContainer').append(html);
    }

    $('#generatorForm').on('submit', function(e) {
        e.preventDefault();
        const btn = $(this).find('button[type="submit"]');
        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Procesando seguridad...');

        $.ajax({
            url: 'action.php',
            method: 'POST',
            data: $(this).serialize() + '&csrf_token=' + $('meta[name="csrf-token"]').attr('content'),
            success: function(response) {
                if(response.status === 'success') {
                    const fullLink = `${baseUrl}/embed.php?data=${response.token}`;
                    $('#finalLink').val(fullLink);
                    $('#previewBtn').attr('href', fullLink);
                    $('#resultBox').slideDown();
                    
                    // Scroll suave al resultado
                    $('html, body').animate({ scrollTop: $("#resultBox").offset().top - 100 }, 500);
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function() {
                alert('Error crítico de comunicación con el servidor.');
            },
            complete: function() {
                btn.prop('disabled', false).html('<i class="fas fa-lock me-2"></i> ENCRIPTAR Y GENERAR ENLACE');
            }
        });
    });

    function copyLink() {
        const copyText = document.getElementById("finalLink");
        copyText.select();
        document.execCommand("copy");
        
        const copyBtn = $('.btn-primary');
        copyBtn.html('<i class="fas fa-check"></i>').addClass('btn-success');
        setTimeout(() => {
            copyBtn.html('<i class="fas fa-copy"></i>').removeClass('btn-success');
        }, 2000);
    }
</script>

</body>
</html>
