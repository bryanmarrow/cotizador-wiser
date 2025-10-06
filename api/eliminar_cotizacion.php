<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: DELETE');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../includes/functions.php';
require_once '../config/constants.php';

// Verificar autenticación y que sea admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    enviarRespuestaJson(API_ERROR, 'No autorizado', null, 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    enviarRespuestaJson(API_ERROR, 'Método no permitido', null, 405);
}

$cotizacionId = intval($_GET['id'] ?? 0);

if ($cotizacionId <= 0) {
    enviarRespuestaJson(API_ERROR, 'ID de cotización inválido', null, 400);
}

try {
    $conn = obtenerConexionBaseDatos();
    
    // Verificar que la cotización existe
    $queryVerificar = "SELECT Id, NombreCliente, Estado FROM Cotizacion_Header WHERE Id = ?";
    $stmtVerificar = $conn->prepare($queryVerificar);
    $stmtVerificar->execute([$cotizacionId]);
    $cotizacion = $stmtVerificar->fetch(PDO::FETCH_ASSOC);
    
    if (!$cotizacion) {
        enviarRespuestaJson(API_ERROR, 'Cotización no encontrada', null, 404);
    }
    
    // Iniciar transacción
    $conn->beginTransaction();
    
    // Eliminar detalles primero (por restricciones de clave foránea)
    $queryEliminarDetalles = "DELETE FROM Cotizacion_Detail WHERE IdHeader = ?";
    $stmtEliminarDetalles = $conn->prepare($queryEliminarDetalles);
    $stmtEliminarDetalles->execute([$cotizacionId]);
    
    // Eliminar header
    $queryEliminarHeader = "DELETE FROM Cotizacion_Header WHERE Id = ?";
    $stmtEliminarHeader = $conn->prepare($queryEliminarHeader);
    $stmtEliminarHeader->execute([$cotizacionId]);
    
    // Confirmar transacción
    $conn->commit();
    
    registrarError('Cotización eliminada exitosamente', [
        'user_id' => $_SESSION['user_id'],
        'action' => 'delete_quotation',
        'cotizacion_id' => $cotizacionId,
        'cliente' => $cotizacion['NombreCliente'],
        'estado_anterior' => $cotizacion['Estado']
    ]);
    
    enviarRespuestaJson(API_SUCCESS, 'Cotización eliminada exitosamente', null);
    
} catch (Exception $e) {
    $conn->rollback();
    
    registrarError('Error eliminando cotización', [
        'error' => $e->getMessage(),
        'cotizacion_id' => $cotizacionId,
        'user_id' => $_SESSION['user_id']
    ]);
    
    enviarRespuestaJson(API_ERROR, 'Error interno del servidor', null, 500);
}
?>