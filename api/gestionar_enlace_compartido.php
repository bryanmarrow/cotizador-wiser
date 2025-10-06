<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/shared_links.php';

// Verificar autenticación y permisos de admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    enviarRespuestaJson('error', 'No autorizado - Se requieren permisos de administrador', null, 401);
}

// Verificar método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    enviarRespuestaJson('error', 'Método no permitido', null, 405);
}

// Obtener datos de entrada
$input = json_decode(file_get_contents('php://input'), true);

// Validar campos requeridos
$camposRequeridos = ['cotizacion_id', 'accion'];
$camposFaltantes = validarCamposRequeridos($camposRequeridos, $input);
if (!empty($camposFaltantes)) {
    enviarRespuestaJson('error', 'Faltan campos requeridos: ' . implode(', ', $camposFaltantes), null, 400);
}

$cotizacionId = intval($input['cotizacion_id']);
$accion = sanitizarEntrada($input['accion']);
$parametros = $input['parametros'] ?? [];

// Validar acción
$accionesValidas = ['activar', 'desactivar', 'deshabilitar_permanente', 'extender_expiracion', 'cambiar_expiracion'];
if (!in_array($accion, $accionesValidas)) {
    enviarRespuestaJson('error', 'Acción no válida', null, 400);
}

// Inicializar conexión a la base de datos
$database = new Database();
$pdo = $database->getConnection();

try {
    // Verificar que la cotización existe
    $stmt = $pdo->prepare("SELECT Id, NombreCliente FROM Cotizacion_Header WHERE Id = ?");
    $stmt->execute([$cotizacionId]);
    $cotizacion = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$cotizacion) {
        enviarRespuestaJson('error', 'Cotización no encontrada', null, 404);
    }
    
    // Validaciones específicas por acción
    switch ($accion) {
        case 'extender_expiracion':
            if (!isset($parametros['horas']) || !validarNumerico($parametros['horas'], 1, 8760)) { // máximo 1 año
                enviarRespuestaJson('error', 'Debe especificar horas válidas (1-8760)', null, 400);
            }
            break;
            
        case 'cambiar_expiracion':
            if (!isset($parametros['fecha_expiracion'])) {
                enviarRespuestaJson('error', 'Debe especificar nueva fecha de expiración', null, 400);
            }
            // Validar formato de fecha
            $fechaExpiracion = DateTime::createFromFormat('Y-m-d H:i:s', $parametros['fecha_expiracion']);
            if (!$fechaExpiracion) {
                enviarRespuestaJson('error', 'Formato de fecha inválido (YYYY-MM-DD HH:MM:SS)', null, 400);
            }
            break;
    }
    
    // Ejecutar acción
    $resultado = gestionarVisibilidadEnlace($cotizacionId, $accion, $parametros);
    
    if ($resultado['success']) {
        // Obtener información actualizada del enlace
        $infoEnlace = obtenerInfoEnlaceCompartido($cotizacionId);
        
        $respuesta = [
            'cotizacion_id' => $cotizacionId,
            'cliente' => $cotizacion['NombreCliente'],
            'accion_realizada' => $accion,
            'enlace_info' => $infoEnlace
        ];
        
        // Log de la acción administrativa
        error_log("Admin {$_SESSION['username']} ejecutó acción '{$accion}' en enlace de cotización {$cotizacionId}");
        
        enviarRespuestaJson('success', $resultado['message'], $respuesta);
    } else {
        enviarRespuestaJson('error', 'No se pudo completar la acción', null, 500);
    }
    
} catch (Exception $e) {
    error_log("Error en gestionar_enlace_compartido.php: " . $e->getMessage());
    enviarRespuestaJson('error', 'Error interno del servidor', null, 500);
}
?>