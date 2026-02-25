<?php
declare(strict_types=1);

require_once __DIR__ . "/config/config.php";

// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 🔒 SEGURIDAD: Verificar que el usuario sea Admin
// Si no está logueado en el panel principal, denegar acceso inmediatamente.
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("HTTP/1.1 403 Forbidden");
    die("Acceso Denegado: Requiere autenticación administrativa.");
}

// Verificar fingerprint para evitar robo de sesión
$fingerprint = hash('sha256', getClientIP() . $_SERVER['HTTP_USER_AGENT']);
if (!isset($_SESSION['fingerprint']) || $_SESSION['fingerprint'] !== $fingerprint) {
    session_destroy();
    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MP4 Console - Secure</title>
    <style>
        body {
            margin: 0;
            background: #0d1117;
            color: #c9d1d9;
            font-family: "Courier New", monospace;
            overflow: hidden;
        }
        .header {
            background: #161b22;
            padding: 1rem 2rem;
            border-bottom: 1px solid #30363d;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .logo {
            font-size: 1.2rem;
            font-weight: bold;
            color: #58a6ff;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .status-badge {
            background: rgba(46, 160, 67, 0.15);
            color: #3fb950;
            border: 1px solid rgba(46, 160, 67, 0.4);
            border-radius: 20px;
            padding: 2px 10px;
            font-size: 0.8rem;
        }
        .content {
            height: calc(100vh - 70px);
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 1px;
            background: #30363d; /* Color del borde de rejilla */
        }
        .panel {
            background: #0d1117;
            padding: 1rem;
            overflow-y: auto;
        }
        .stat-item {
            margin-bottom: 1.5rem;
            border-bottom: 1px solid #21262d;
            padding-bottom: 1rem;
        }
        .stat-value {
            font-size: 1.8rem;
            font-weight: bold;
            color: #f0f6fc;
        }
        .stat-label {
            font-size: 0.8rem;
            color: #8b949e;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        #logContainer {
            font-family: 'Consolas', monospace;
            font-size: 0.9rem;
            line-height: 1.6;
        }
        .log-entry {
            margin-bottom: 4px;
            border-left: 2px solid transparent;
            padding-left: 8px;
        }
        .log-info { border-left-color: #58a6ff; color: #c9d1d9; }
        .log-success { border-left-color: #2ea043; color: #7ee787; }
        .log-warning { border-left-color: #d29922; color: #f2cc60; }
        .time { color: #8b949e; font-size: 0.8em; margin-right: 8px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">
            <span>🛡️ MP4 Security Console</span>
            <span class="status-badge">PROTECTED</span>
        </div>
        <div style="font-size: 0.9rem;">
            Admin IP: <?= htmlspecialchars(getClientIP()) ?> | 
            <span id="clock">--:--:--</span>
        </div>
    </div>

    <div class="content">
        <div class="panel">
            <div class="stat-item">
                <div class="stat-label">Solicitudes Hoy</div>
                <div class="stat-value" id="reqCount">0</div>
            </div>
            <div class="stat-item">
                <div class="stat-label">Streams Activos</div>
                <div class="stat-value" id="streamCount">0</div>
            </div>
            <div class="stat-item">
                <div class="stat-label">Estado del Sistema</div>
                <div class="stat-value" style="color: #3fb950; font-size: 1.2rem;">OPERATIVO</div>
            </div>
            <div style="margin-top: auto; font-size: 0.8rem; color: #484f58;">
                MP4 Security v<?= MP4_VERSION ?>
            </div>
        </div>

        <div class="panel" id="logPanel">
            <div id="logContainer">
                </div>
        </div>
    </div>

    <script>
        // Reloj
        setInterval(() => {
            const now = new Date();
            document.getElementById('clock').textContent = now.toLocaleTimeString();
        }, 1000);

        // Simulación de datos (Ya que no hay DB real conectada en este ejemplo)
        // En un entorno real, esto haría fetch a un endpoint de estadísticas seguro
        function addLog(msg, type = 'info') {
            const container = document.getElementById('logContainer');
            const div = document.createElement('div');
            div.className = `log-entry log-${type}`;
            
            const time = new Date().toLocaleTimeString();
            div.innerHTML = `<span class="time">[${time}]</span> ${msg}`;
            
            container.insertBefore(div, container.firstChild);
            
            if (container.children.length > 50) {
                container.lastChild.remove();
            }
        }

        // Datos dummy para "dar vida" a la consola visualmente
        let reqs = Math.floor(Math.random() * 500) + 100;
        let streams = Math.floor(Math.random() * 10) + 2;

        setInterval(() => {
            document.getElementById('reqCount').innerText = reqs;
            document.getElementById('streamCount').innerText = streams;
            
            // Simular variación
            if(Math.random() > 0.5) reqs++;
            if(Math.random() > 0.7) streams = Math.max(0, streams + (Math.random() > 0.5 ? 1 : -1));
        }, 2000);

        // Logs iniciales
        addLog("Sistema de monitoreo iniciado", "success");
        addLog("Conexión segura establecida", "info");
        addLog("Rate Limiting: ACTIVO", "success");
        addLog("Validación de Referer: ACTIVO", "success");

        // Simular actividad esporádica
        setInterval(() => {
            const events = [
                { msg: "Nueva solicitud de token autorizada", type: "info" },
                { msg: "Stream iniciado (IP: ***.***.***.12)", type: "success" },
                { msg: "Bloqueo preventivo: Rate limit excedido", type: "warning" },
                { msg: "Verificación de integridad completada", type: "info" }
            ];
            const ev = events[Math.floor(Math.random() * events.length)];
            addLog(ev.msg, ev.type);
        }, 4000 + Math.random() * 3000);
    </script>
</body>
</html>
