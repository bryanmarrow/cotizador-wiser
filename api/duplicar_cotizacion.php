<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../includes/functions.php';
require_once '../config/constants.php';

// Verificar autenticación y que sea admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    enviarRespuestaJson(API_ERROR, 'No autorizado', null, 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    enviarRespuestaJson(API_ERROR, 'Método no permitido', null, 405);
}

$cotizacionId = intval($_GET['id'] ?? 0);

if ($cotizacionId <= 0) {
    enviarRespuestaJson(API_ERROR, 'ID de cotización inválido', null, 400);
}

try {
    $conn = obtenerConexionBaseDatos();
    
    // Iniciar transacción
    $conn->beginTransaction();
    
    // Obtener datos de la cotización original
    $queryOriginal = "SELECT * FROM Cotizacion_Header WHERE Id = ?";
    $stmtOriginal = $conn->prepare($queryOriginal);
    $stmtOriginal->execute([$cotizacionId]);
    $originalHeader = $stmtOriginal->fetch(PDO::FETCH_ASSOC);
    
    if (!$originalHeader) {
        $conn->rollback();
        enviarRespuestaJson(API_ERROR, 'Cotización no encontrada', null, 404);
    }
    
    // Crear nueva cotización header
    $insertHeader = "INSERT INTO Cotizacion_Header (
        FechaCreacion, NombreCliente, TipoCliente, IdTipoCliente, UserId,
        Moneda, TipoCambio, PlazoMeses, PorcentajeResidual, Tasa, TipoTasa,
        Comision, SubTotal, IVA, TotalEquipo, ValorResidual, PagoMensual,
        TotalContrato, TotalUtilidad, Estado
    ) VALUES (
        NOW(), ?, ?, ?, ?,
        ?, ?, ?, ?, ?, ?,
        ?, ?, ?, ?, ?, ?,
        ?, ?, 'borrador'
    )";
    
    $stmtHeader = $conn->prepare($insertHeader);
    $stmtHeader->execute([
        $originalHeader['NombreCliente'],
        $originalHeader['TipoCliente'],
        $originalHeader['IdTipoCliente'],
        $_SESSION['user_id'], // Asignar al usuario actual
        $originalHeader['Moneda'],
        $originalHeader['TipoCambio'],
        $originalHeader['PlazoMeses'],
        $originalHeader['PorcentajeResidual'],
        $originalHeader['Tasa'],
        $originalHeader['TipoTasa'],
        $originalHeader['Comision'],
        $originalHeader['SubTotal'],
        $originalHeader['IVA'],
        $originalHeader['TotalEquipo'],
        $originalHeader['ValorResidual'],
        $originalHeader['PagoMensual'],
        $originalHeader['TotalContrato'],
        $originalHeader['TotalUtilidad']
    ]);
    
    $nuevoCotizacionId = $conn->lastInsertId();
    
    // Obtener detalles de la cotización original
    $queryDetalles = "SELECT * FROM Cotizacion_Detail WHERE IdHeader = ?";
    $stmtDetalles = $conn->prepare($queryDetalles);
    $stmtDetalles->execute([$cotizacionId]);
    $detallesOriginales = $stmtDetalles->fetchAll(PDO::FETCH_ASSOC);
    
    // Duplicar detalles
    if (!empty($detallesOriginales)) {
        $insertDetalle = "INSERT INTO Cotizacion_Detail (
            IdHeader, IdEquipo, Cantidad, PrecioUnitario, SubTotal, IVA, Total
        ) VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $stmtDetalle = $conn->prepare($insertDetalle);
        
        foreach ($detallesOriginales as $detalle) {
            $stmtDetalle->execute([
                $nuevoCotizacionId,
                $detalle['IdEquipo'],
                $detalle['Cantidad'],
                $detalle['PrecioUnitario'],
                $detalle['SubTotal'],
                $detalle['IVA'],
                $detalle['Total']
            ]);
        }
    }
    
    // Confirmar transacción
    $conn->commit();
    
    registrarError('Cotización duplicada exitosamente', [
        'user_id' => $_SESSION['user_id'],
        'action' => 'duplicate_quotation',
        'original_id' => $cotizacionId,
        'new_id' => $nuevoCotizacionId
    ]);
    
    enviarRespuestaJson(API_SUCCESS, 'Cotización duplicada exitosamente', [
        'nueva_cotizacion_id' => $nuevoCotizacionId
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    
    registrarError('Error duplicando cotización', [
        'error' => $e->getMessage(),
        'cotizacion_id' => $cotizacionId,
        'user_id' => $_SESSION['user_id']
    ]);
    
    enviarRespuestaJson(API_ERROR, 'Error interno del servidor', null, 500);
}
?>