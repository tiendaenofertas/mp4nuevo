<?php
declare(strict_types=1);

/**
 * MP4 Security System - Consola de Monitoreo Corregida
 * Implementa control de acceso y seguridad perimetral.
 */

require_once __DIR__ . "/config/config.php";

// 1. PROTECCIÓN DE ACCESO: Solo el administrador puede entrar
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Reutilizamos la validación de hash de index.php para coherencia
const SECRET_KEY_1 = "xzorra_key_2025";
const PASSWORD = "mp4secure2025";
const SECRET_KEY_2 = "secure_panel_key";
$authHash = hash('sha256', SECRET_KEY_1 . PASSWORD . SECRET_KEY_2);

if (!isset($_SESSION["login"]) || $_SESSION["login"] !== $authHash) {
    logSecurityEvent("Intento de acceso no autorizado a console.php");
    header("Location: index.php");
    exit;
}

// 2. ACTIVAR CABECERAS DE SEGURIDAD
setSecurityHeaders();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MP4 Console - Monitoreo Activo</title>
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
        <div class="logo">MP4 Console v<?= htmlspecialchars(MP4_VERSION) ?></div>
        <div>
            <span class="status-dot"></span>
            <span>Sistema Activo</span>
            <span style="margin-left: 2rem;" id="time"></span>
        </div>
    </div>

    <div class="content">
        <div class="panel">
            <div class="panel-title">📊 Estadísticas del Sistema (Simulado)</div>
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
                    <strong>[Info]</strong> Cargando configuración segura...
                </div>
                <div class="log-entry">
                    <strong>[Success]</strong> Autenticación validada: Panel listo
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
            
            const logs = container.querySelectorAll(".log-entry");
            if (logs.length > 20) {
                logs[logs.length - 1].remove();
            }
        }

        setInterval(updateTime, 1000);
        setInterval(updateStats, 5000);

        const activities = [
            "Nueva solicitud de encriptación recibida",
            "URL encriptada exitosamente", 
            "Stream iniciado para cliente",
            "Reproductor cargado correctamente",
            "Filtro de IP aplicado",
            "Validación de token exitosa"
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
