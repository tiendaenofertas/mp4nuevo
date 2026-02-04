<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel MP4 - Acceso Seguro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --glass-bg: rgba(255, 255, 255, 0.95);
        }
        body {
            background: var(--primary-gradient);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Inter', -apple-system, sans-serif;
            margin: 0;
            padding: 15px;
        }
        .login-container {
            background: var(--glass-bg);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            width: 100%;
            max-width: 420px;
            animation: fadeIn 0.5s ease-in-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .login-header {
            background: var(--primary-gradient);
            color: white;
            padding: 2.5rem 1.5rem;
            text-align: center;
        }
        .login-header i {
            filter: drop-shadow(0 4px 6px rgba(0,0,0,0.2));
            margin-bottom: 1rem;
        }
        .login-header h2 {
            font-weight: 700;
            letter-spacing: -0.5px;
            margin-bottom: 0.25rem;
        }
        .login-header p {
            font-size: 0.9rem;
            opacity: 0.9;
            margin-bottom: 0;
        }
        .form-control {
            border-radius: 10px;
            padding: 0.75rem 1rem;
            border: 2px solid #eee;
            transition: all 0.3s ease;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.25rem rgba(102, 126, 234, 0.1);
        }
        .input-group-text {
            background: transparent;
            border: 2px solid #eee;
            border-right: none;
            border-radius: 10px 0 0 10px;
            color: #764ba2;
        }
        .input-group .form-control {
            border-left: none;
            border-radius: 0 10px 10px 0;
        }
        .btn-login {
            background: var(--primary-gradient);
            border: none;
            border-radius: 10px;
            padding: 0.8rem;
            font-weight: 600;
            letter-spacing: 0.5px;
            transition: transform 0.2s, box-shadow 0.2s;
            color: white;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
            color: white;
        }
        .error-msg {
            font-size: 0.85rem;
            border-radius: 10px;
            border: none;
            background: #fff5f5;
            color: #c53030;
            border-left: 4px solid #f56565;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <i class="fas fa-shield-halved fa-3x"></i>
            <h2>Acceso al Panel</h2>
            <p>MP4 Security Engine v<?= htmlspecialchars(MP4_VERSION) ?></p>
        </div>
        
        <div class="p-4 p-md-5">
            <?php if (isset($showError) && $showError): ?>
            <div class="alert error-msg d-flex align-items-center mb-4" role="alert">
                <i class="fas fa-circle-xmark me-2"></i>
                <div>Credenciales incorrectas o sesión expirada.</div>
            </div>
            <?php endif; ?>
            
            <form action="<?= htmlspecialchars($self) ?>" method="POST" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                
                <div class="mb-3">
                    <label for="username" class="form-label fw-semibold small text-uppercase text-muted">Usuario</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-user-circle"></i></span>
                        <input type="text" class="form-control" id="username" name="username" placeholder="Ingresa tu usuario" required autofocus>
                    </div>
                </div>
                
                <div class="mb-4">
                    <label for="password" class="form-label fw-semibold small text-uppercase text-muted">Contraseña</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-key"></i></span>
                        <input type="password" class="form-control" id="password" name="password" placeholder="••••••••" required>
                    </div>
                </div>
                
                <button type="submit" name="submit" class="btn btn-login w-100 mb-3">
                    <i class="fas fa-unlock-alt me-2"></i>Entrar al Sistema
                </button>
            </form>
            
            <div class="text-center mt-4">
                <small class="text-muted">
                    Conexión cifrada: <strong>AES-256-CBC</strong>
                </small>
            </div>
        </div>
    </div>

    <script>
        document.querySelector('form').addEventListener('submit', function() {
            const btn = this.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Validando...';
        });
    </script>
</body>
</html>
