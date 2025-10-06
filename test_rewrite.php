<?php
/**
 * Archivo de prueba para verificar mod_rewrite y configuración del servidor
 */

echo "<h1>Diagnóstico de URL Rewrite</h1>";

echo "<h2>Información del Servidor</h2>";
echo "<p><strong>Servidor:</strong> " . $_SERVER['SERVER_SOFTWARE'] . "</p>";
echo "<p><strong>PHP Version:</strong> " . PHP_VERSION . "</p>";
echo "<p><strong>Document Root:</strong> " . $_SERVER['DOCUMENT_ROOT'] . "</p>";
echo "<p><strong>Request URI:</strong> " . $_SERVER['REQUEST_URI'] . "</p>";
echo "<p><strong>Script Name:</strong> " . $_SERVER['SCRIPT_NAME'] . "</p>";

echo "<h2>Módulos Apache</h2>";
if (function_exists('apache_get_modules')) {
    $modules = apache_get_modules();
    if (in_array('mod_rewrite', $modules)) {
        echo "<p>✅ <strong>mod_rewrite está habilitado</strong></p>";
    } else {
        echo "<p>❌ <strong>mod_rewrite NO está habilitado</strong></p>";
    }
    echo "<p>Módulos disponibles: " . implode(', ', $modules) . "</p>";
} else {
    echo "<p>⚠️ No se puede verificar módulos Apache (función apache_get_modules no disponible)</p>";
}

echo "<h2>Variables de Entorno</h2>";
echo "<p><strong>HTTP_HOST:</strong> " . ($_SERVER['HTTP_HOST'] ?? 'No definido') . "</p>";
echo "<p><strong>HTTPS:</strong> " . ($_SERVER['HTTPS'] ?? 'No definido') . "</p>";
echo "<p><strong>REQUEST_METHOD:</strong> " . $_SERVER['REQUEST_METHOD'] . "</p>";

echo "<h2>Pruebas de URL</h2>";
echo "<p>Prueba estas URLs:</p>";
echo "<ul>";
echo "<li><a href='cotizacion-publica.php?folio=WIS-2025-L2EN' target='_blank'>URL directa (debería funcionar)</a></li>";
echo "<li><a href='cotizacion?folio=WIS-2025-L2EN' target='_blank'>URL reescrita (puede fallar)</a></li>";
echo "<li><a href='cotizacion/' target='_blank'>URL reescrita sin parámetros</a></li>";
echo "</ul>";

echo "<h2>Archivo .htaccess</h2>";
$htaccessPath = __DIR__ . '/.htaccess';
if (file_exists($htaccessPath)) {
    echo "<p>✅ <strong>Archivo .htaccess existe</strong></p>";
    echo "<pre>" . htmlspecialchars(file_get_contents($htaccessPath)) . "</pre>";
} else {
    echo "<p>❌ <strong>Archivo .htaccess NO existe</strong></p>";
}

echo "<h2>Verificación de archivos</h2>";
$files = ['cotizacion-publica.php', 'index.php', 'cotizaciones.php'];
foreach ($files as $file) {
    if (file_exists($file)) {
        echo "<p>✅ $file existe</p>";
    } else {
        echo "<p>❌ $file NO existe</p>";
    }
}
?>