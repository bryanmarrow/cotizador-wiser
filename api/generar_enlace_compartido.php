<?php

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/shared_links.php';

// Inicializar conexión a la base de datos
$database = new Database();
$pdo = $database->getConnection();

// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    enviarRespuestaJson('error', 'No autorizado', null, 401);
}

// Verificar método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    enviarRespuestaJson('error', 'Método no permitido', null, 405);
}

// Obtener datos de entrada
$input = json_decode(file_get_contents('php://input'), true);

// Validar campos requeridos
$camposRequeridos = ['cotizacion_id'];
$camposFaltantes = validarCamposRequeridos($camposRequeridos, $input);
if (!empty($camposFaltantes)) {
    enviarRespuestaJson('error', 'Faltan campos requeridos: ' . implode(', ', $camposFaltantes), null, 400);
}

$cotizacionId = intval($input['cotizacion_id']);
$regenerar = isset($input['regenerar']) ? (bool)$input['regenerar'] : false;

try {
    // Verificar que la cotización existe y pertenece al usuario o es admin
    $stmt = $pdo->prepare("
        SELECT ch.Id, ch.UserId, ch.NombreCliente, ch.Estado 
        FROM Cotizacion_Header ch
        WHERE ch.Id = ?
    ");
    $stmt->execute([$cotizacionId]);
    $cotizacion = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$cotizacion) {
        enviarRespuestaJson('error', 'Cotización no encontrada', null, 404);
    }
    
    // Verificar permisos (propietario, admin, o cliente)
    $esAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
    $esCliente = isset($_SESSION['role']) && $_SESSION['role'] === 'cliente';
    $esPropietario = $cotizacion['UserId'] == $_SESSION['user_id'];
    
    if (!$esAdmin && !$esCliente && !$esPropietario) {
        enviarRespuestaJson('error', 'No tienes permisos para generar enlace de esta cotización', null, 403);
    }
    
    // Verificar que la cotización esté completada o guardada
    $estadosPermitidos = ['completada', 'guardada', 'impresa'];
    if (!in_array($cotizacion['Estado'], $estadosPermitidos)) {
        enviarRespuestaJson('error', 'Solo se pueden generar enlaces para cotizaciones completadas, guardadas o impresas', null, 400);
    }
    
    // Crear enlace compartido
    $resultado = crearEnlaceCompartido($cotizacionId, $regenerar);
    
    if ($resultado['success']) {
        // Generar QR
        $qrResult = generarQR($resultado['folio']);
        
        $respuesta = [
            'folio' => $resultado['folio'],
            'regenerado' => $resultado['regenerado'],
            'fecha_expiracion' => $resultado['fecha_expiracion'] ?? null,
            'url_publica' => obtenerUrlPublica($resultado['folio']),
            'qr_disponible' => $qrResult['success'],
            'qr_url' => $qrResult['qr_url'] ?? null,
            'cliente' => $cotizacion['NombreCliente']
        ];
        
        enviarRespuestaJson('success', $resultado['message'], $respuesta);
    } else {
        enviarRespuestaJson('error', 'No se pudo generar el enlace compartido', null, 500);
    }
    
} catch (Exception $e) {
    error_log("Error en generar_enlace_compartido.php: " . $e->getMessage());
    enviarRespuestaJson('error', 'Error interno del servidor', null, 500);
}
?>