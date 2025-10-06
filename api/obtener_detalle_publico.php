<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/shared_links.php';

// Obtener y validar el folio
$folio = $_GET['folio'] ?? '';
if (empty($folio)) {
    enviarRespuestaJson('error', 'Folio no proporcionado', null, 400);
}

$folio = sanitizarEntrada($folio);

// 1. Validar el enlace
$validacion = validarEnlaceCompartido($folio);
if (!$validacion['valido']) {
    $mensajeError = match($validacion['motivo']) {
        'enlace_invalido' => 'El enlace de la cotización no es válido.',
        'enlace_deshabilitado' => 'Este enlace ha sido deshabilitado.',
        'enlace_inactivo' => 'Este enlace no se encuentra activo.', 
        'enlace_expirado' => 'El enlace de la cotización ha expirado.',
        default => 'No se puede acceder a la cotización.'
    };
    enviarRespuestaJson('error', $mensajeError, null, 403);
}

$cotizacionId = $validacion['cotizacion_id'];

// 2. Obtener los datos de la cotización (versión pública)
try {
    $pdo = (new Database())->getConnection();

    // Obtener el header de la cotización
    $stmt = $pdo->prepare("
        SELECT 
            ch.Id as id,
            ch.Folio as folio,
            ch.NombreCliente as cliente,
            ch.TipoCliente as tipoCliente,
            ch.Moneda as moneda,
            ch.Plazo as plazo,
            ch.P_Residual as porcentajeResidual,
            ch.FechaCreacion as fechaCreacion,
            ch.Estado as estado,
            ch.TotalContrato as totalContrato,
            ch.TotalResidual1Pago as totalResidual1Pago,
            ch.TotalResidual3Pagos as totalResidual3Pagos,
            u.full_name as vendedor
        FROM Cotizacion_Header ch
        LEFT JOIN users u ON ch.UserId = u.id
        WHERE ch.Id = ?
    ");
    $stmt->execute([$cotizacionId]);
    $cotizacion = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cotizacion) {
        enviarRespuestaJson('error', 'Cotización no encontrada.', null, 404);
    }

    // Formatear fecha
    $cotizacion['fechaFormateada'] = date('d/m/Y', strtotime($cotizacion['fechaCreacion']));

    // Obtener los equipos de la cotización
    $stmt = $pdo->prepare("
        SELECT
            cd.Id as id,
            cd.Equipo as nombre,
            cd.Marca as marca,
            cd.Modelo as modelo,
            cd.Cantidad as cantidad,
            cd.Costo as precio,
            cd.PagoEquipo as pagoEquipo,
            cd.Seguro as seguro,
            cd.ValorResidual as valorResidual,
            cd.IvaResidual as ivaResidual,
            cd.Residual1Pago as residual1Pago,
            cd.Residual3Pagos as residual3Pagos,
            cd.PagoGPS as pagoGPS,
            cd.PagoPlacas as pagoPlacas,
            cd.CostoGPS as costoGPS,
            cd.CostoPlacas as costoPlacas,
            cd.InteresGPS as interesGPS,
            cd.InteresPlacas as interesPlacas
        FROM Cotizacion_Detail cd
        WHERE cd.IdHeader = ?
        ORDER BY cd.Id ASC
    ");
    $stmt->execute([$cotizacionId]);
    $equipos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $respuesta = [
        'cotizacion' => $cotizacion,
        'equipos' => $equipos
    ];

    enviarRespuestaJson('success', 'Detalle de cotización obtenido.', $respuesta);

} catch (Exception $e) {
    error_log("Error en obtener_detalle_publico.php: " . $e->getMessage());
    enviarRespuestaJson('error', 'Error interno del servidor al cargar la cotización.', null, 500);
}
