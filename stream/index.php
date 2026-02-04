<?php
/**
 * MP4 Security System - Directory Protection
 * Evita la exploración de archivos en la carpeta de streaming.
 */

header("HTTP/1.1 301 Moved Permanently");
header("Location: ../index.php");
exit;
