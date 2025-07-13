<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MP4 Console</title>
    <style>
        body {
            margin: 0;
            background: #0d1117;
            color: #c9d1d9;
            font-family: "Courier New", monospace;
            overflow: hidden;
        }
        .header {
            background: #21262d;
            padding: 1rem 2rem;
            border-bottom: 1px solid #30363d;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .logo {
            font-size: 1.5rem;
            font-weight: bold;
            color: #58a6ff;
        }
        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #28a745;
            display: inline-block;
            margin-right: 0.5rem;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        .content {
            height: calc(100vh - 80px);
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            padding: 1rem;
        }
        .panel {
            background: #161b22;
            border: 1px solid #30363d;
            border-radius: 8px;
            padding: 1rem;
            overflow-y: auto;
        }
        .panel-title {
            color: #f0f6fc;
            font-weight: bold;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid #30363d;
        }
        .stat-card {
            background: #21262d;
            padding: 1rem;
            border-radius: 6px;
            text-align: center;
            margin-bottom: 1rem;
        }
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: #58a6ff;
        }
        .stat-label {
            font-size: 0.875rem;
            color: #8b949e;
            margin-top: 0.5rem;
        }
        .log-entry {
            padding: 0.5rem;
            margin-bottom: 0.5rem;
            border-radius: 4px;
            font-size: 0.875rem;
            border-left: 3px solid #58a6ff;
            background: rgba(88, 166, 255, 0.1);
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">MP4 Console v2.0</div>
        <div>
            <span class="status-dot"></span>
            <span>Sistema Activo</span>
            <span style="margin-left: 2rem;" id="time"></span>
        </div>
    </div>

    <div class="content">
        <div class="panel">
            <div class="panel-title">📊 Estadísticas del Sistema</div>
            <div class="stat-card">
                <div class="stat-value" id="totalRequests">0</div>
                <div class="stat-label">Solicitudes Totales</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="successRate">100%</div>
                <div class="stat-label">Tasa de Éxito</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="activeStreams">0</div>
                <div class="stat-label">Streams Activos</div>
            </div>
        </div>

        <div class="panel">
            <div class="panel-title">📋 Registro de Actividad</div>
            <div id="logContainer">
                <div class="log-entry">
                    <strong>[Sistema]</strong> MP4 Console iniciado correctamente
                </div>
                <div class="log-entry">
                    <strong>[Info]</strong> Cargando configuración...
                </div>
                <div class="log-entry">
                    <strong>[Success]</strong> Sistema listo para recibir solicitudes
                </div>
            </div>
        </div>
    </div>

    <script>
        function updateTime() {
            const now = new Date();
            document.getElementById("time").textContent = now.toLocaleTimeString();
        }

        function updateStats() {
            document.getElementById("totalRequests").textContent = Math.floor(Math.random() * 1000);
            document.getElementById("activeStreams").textContent = Math.floor(Math.random() * 10);
            document.getElementById("successRate").textContent = (95 + Math.floor(Math.random() * 5)) + "%";
        }

        function addLog(message) {
            const container = document.getElementById("logContainer");
            const entry = document.createElement("div");
            entry.className = "log-entry";
            entry.innerHTML = `<strong>[${new Date().toLocaleTimeString()}]</strong> ${message}`;
            container.insertBefore(entry, container.firstChild);
            
            // Mantener solo 20 logs
            const logs = container.querySelectorAll(".log-entry");
            if (logs.length > 20) {
                logs[logs.length - 1].remove();
            }
        }

        // Actualizar cada segundo
        setInterval(updateTime, 1000);
        setInterval(updateStats, 5000);

        // Simular actividad
        const activities = [
            "Nueva solicitud de encriptación recibida",
            "URL encriptada exitosamente", 
            "Stream iniciado para cliente",
            "Reproductor cargado correctamente",
            "Cache limpiado automáticamente"
        ];

        setInterval(() => {
            const activity = activities[Math.floor(Math.random() * activities.length)];
            addLog(activity);
        }, 3000 + Math.random() * 5000);

        updateTime();
        updateStats();
    </script>
</body>
</html>
