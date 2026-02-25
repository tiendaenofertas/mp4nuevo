<?php
// 1. ACTIVAR BUFFER DE SALIDA (Atrapa cualquier espacio o error accidental)
ob_start();

// Configuración estricta de errores para que no salgan en pantalla y rompan el JSON
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

try {
    // 2. CARGAR CONFIGURACIÓN
    $configFile = __DIR__ . '/config/config.php';
    if (!file_exists($configFile)) {
        throw new Exception("Error crítico: No se encuentra el archivo config/config.php");
    }
    require_once $configFile;

    // 3. VERIFICAR SESIÓN
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Verificar autenticación (compatible con ambos diseños)
    $is_authorized = isset($_SESSION['login']) || (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true);

    if (!$is_authorized) {
        throw new Exception("Sesión expirada. Por favor recarga la página e inicia sesión nuevamente.");
    }

    // 4. VALIDAR MÉTODO
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Método no permitido.");
    }

    // 5. VALIDAR DATOS DE ENTRADA
    $link = $_POST['link'] ?? '';
    
    // Sanitización básica si las funciones de config no cargaron
    if (function_exists('sanitizeInput')) {
        $link = sanitizeInput($link);
    } else {
        $link = htmlspecialchars(trim($link));
    }

    if (empty($link)) {
        throw new Exception("La URL del video es obligatoria.");
    }

    // Validar formato URL
    if (strpos($link, 'http') !== 0) {
        throw new Exception("La URL debe comenzar con http:// o https://");
    }

    // Procesar Poster
    $poster = $_POST['poster'] ?? '';
    if (function_exists('sanitizeInput')) {
        $poster = sanitizeInput($poster);
    }

    // Procesar Subtítulos
    $subtitles = [];
    $subs = $_POST['sub'] ?? [];
    $labels = $_POST['label'] ?? [];

    if (is_array($subs) && is_array($labels)) {
        foreach ($subs as $key => $url) {
            $url = trim((string)$url);
            if (empty($url)) continue;
            
            $label = $labels[$key] ?? "Idioma " . ($key + 1);
            if (function_exists('sanitizeInput')) {
                $label = sanitizeInput($label);
                $url = sanitizeInput($url);
            }
            $subtitles[$label] = $url;
        }
    }

    // 6. PREPARAR DATOS
    $videoData = [
        'link' => $link,
        'poster' => $poster,
        'sub' => $subtitles,
        'metadata' => [
            'created_at' => time(),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'
        ]
    ];

    // Convertir a JSON
    $jsonData = json_encode($videoData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($jsonData === false) {
        throw new Exception("Error al procesar los datos del video.");
    }

    // 7. ENCRIPTAR
    if (!function_exists('encodeSecure')) {
        throw new Exception("Error interno: Función de encriptación no encontrada.");
    }

    $encoded = encodeSecure($jsonData);
    if ($encoded === false) {
        throw new Exception("Fallo al generar el enlace seguro.");
    }

    // 8. SALIDA LIMPIA
    // Borramos cualquier texto basura que se haya generado antes
    ob_end_clean();
    
    // Enviamos la respuesta JSON correcta
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($encoded); // Enviamos solo el string encriptado, o un objeto si prefieres

} catch (Exception $e) {
    // En caso de error, limpiamos el buffer y enviamos el error en JSON
    ob_end_clean();
    http_response_code(400); // Bad Request
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => $e->getMessage()]);
}
?>
