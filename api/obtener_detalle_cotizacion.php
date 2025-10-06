<?php

// ini_set('display_errors', '1');
// ini_set('display_startup_errors', '1');
// error_reporting(E_ALL);

session_start();
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../includes/functions.php';
require_once '../config/constants.php';

// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    enviarRespuestaJson(API_ERROR, 'No autorizado', null, 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    enviarRespuestaJson(API_ERROR, 'Método no permitido', null, 405);
}

$cotizacionId = intval($_GET['id'] ?? 0);

if ($cotizacionId <= 0) {
    enviarRespuestaJson(API_ERROR, 'ID de cotización inválido', null, 400);
}

try {
    $conn = obtenerConexionBaseDatos();
    
    // Obtener información de la cotización
    $queryHeader = "SELECT 
                        h.*, 
                        u.full_name as vendedor_nombre, 
                        tc.Descripcion as tipo_cliente_nombre,
                        tc.Comision as comision_cliente
                    FROM Cotizacion_Header h
                    LEFT JOIN users u ON h.UserId = u.id
                    LEFT JOIN Catalogo_TipoCliente tc ON h.TipoCliente = tc.Codigo
                    WHERE h.Id = ?";
    
    $stmtHeader = $conn->prepare($queryHeader);
    $stmtHeader->execute([$cotizacionId]);
    $headerData = $stmtHeader->fetch(PDO::FETCH_ASSOC);
    
    if (!$headerData) {
        enviarRespuestaJson(API_ERROR, 'Cotización no encontrada', null, 404);
    }
    
    // Verificar permisos de acceso
    if ($_SESSION['role'] === 'vendor' && $headerData['UserId'] != $_SESSION['user_id']) {
        enviarRespuestaJson(API_ERROR, 'No tienes permiso para ver esta cotización', null, 403);
    } elseif ($_SESSION['role'] === 'client' && $headerData['UserId'] != $_SESSION['user_id']) {
        enviarRespuestaJson(API_ERROR, 'No tienes permiso para ver esta cotización', null, 403);
    }
    
    // Obtener equipos de la cotización incluyendo campos de placas y GPS y configuración del catálogo
    $queryDetails = "SELECT d.*, 
                            d.CostoPlacas, d.CostoGPS, 
                            d.PagoPlacas, d.PagoGPS,
                            d.InteresPlacas, d.InteresGPS,
                            e.IncluirPlacas, e.IncluirGPS
                     FROM Cotizacion_Detail d
                     LEFT JOIN Catalogo_Equipos e ON d.Equipo = e.Nombre AND e.Activo = 1
                     WHERE d.IdHeader = ?
                     ORDER BY d.Id ASC";
    
    $stmtDetails = $conn->prepare($queryDetails);
    $stmtDetails->execute([$cotizacionId]);
    $detailsData = $stmtDetails->fetchAll(PDO::FETCH_ASSOC);
    
    // Calcular totales usando la lógica del calculations.js
    $tasa = floatval($headerData['Tasa'] ?? 0);
    $comision = floatval($headerData['Comision'] ?? 0);
    
    $subtotalTotal = 0;
    $ivaTotal = 0;
    $pagoMensualTotal = 0;
    $totalEquipoTotal = 0;
    $valorResidualTotal = 0;
    $totalResidual1Pago = 0;
    $totalResidual3Pagos = 0;
    
    foreach ($detailsData as $equipo) {
        // Usar valores ya calculados y guardados en Cotizacion_Detail
        $cantidad = intval($equipo['Cantidad']);
        
        // Acumular totales desde los campos guardados en la base de datos
        $subtotalTotal += floatval($equipo['Subtotal'] ?? 0);
        $ivaTotal += floatval($equipo['Iva'] ?? 0);
        $pagoMensualTotal += floatval($equipo['PagoMensual'] ?? 0);
        $totalEquipoTotal += floatval($equipo['Costo'] ?? 0) * $cantidad;
        $valorResidualTotal += floatval($equipo['ValorResidual'] ?? 0);
        
        // Acumular residuales desde Cotizacion_Detail
        $totalResidual1Pago += floatval($equipo['Residual1Pago'] ?? 0);
        $totalResidual3Pagos += floatval($equipo['Residual3Pagos'] ?? 0);
    }
    
    // Formatear datos de la cotización
    $cotizacion = [
        'id' => $headerData['Id'],
        'fecha' => $headerData['FechaCreacion'],
        'fechaFormateada' => date('d/m/Y H:i', strtotime($headerData['FechaCreacion'])),
        'cliente' => $headerData['NombreCliente'] ?? 'Sin cliente',
        'tipoCliente' => $headerData['tipo_cliente_nombre'] ?? $headerData['TipoCliente'] ?? 'No especificado',
        'vendedor' => $headerData['vendedor_nombre'] ?? 'Usuario eliminado',
        'vendedorId' => $headerData['UserId'],
        'estado' => $headerData['Estado'] ?? 'borrador',
        'moneda' => $headerData['Moneda'] ?? 'MXN',
        'plazo' => intval($headerData['Plazo'] ?? 0),
        'porcentajeResidual' => floatval($headerData['P_Residual'] ?? 20),
        'tasa' => floatval($headerData['Tasa'] ?? 0),
        'tipoTasa' => $headerData['TipoTasa'] ?? 'Fija',
        'subtotal' => $subtotalTotal,
        'iva' => $ivaTotal,
        'totalEquipo' => $totalEquipoTotal,
        'valorResidual' => $valorResidualTotal,
        'pagoMensual' => $pagoMensualTotal,
        'totalContrato' => floatval($headerData['TotalContrato'] ?? 0),
        'utilidad' => floatval($headerData['TotalUtilidad'] ?? 0),
        'totalResidual1Pago' => $totalResidual1Pago,
        'totalResidual3Pagos' => $totalResidual3Pagos,
        'comision' => floatval($headerData['Comision'] ?? 0)
    ];
    
    // Formatear datos de los equipos
    $equipos = array_map(function($equipo) {
        return [
            'id' => $equipo['Id'],
            'nombre' => $equipo['Equipo'] ?? 'Equipo no especificado',
            'marca' => $equipo['Marca'] ?? '',
            'modelo' => $equipo['Modelo'] ?? '',
            'cantidad' => intval($equipo['Cantidad']),
            'precio' => floatval($equipo['Costo']), // Costo es el precio del equipo
            'subtotal' => floatval($equipo['CostoVenta']) ?? (floatval($equipo['Costo']) * intval($equipo['Cantidad'])),
            'iva' => floatval($equipo['CostoVenta']) * 0.16, // IVA calculado
            'total' => floatval($equipo['CostoVenta']) * 1.16, // Total con IVA
            // Datos de residuales por equipo
            'valorResidual' => floatval($equipo['ValorResidual'] ?? 0),
            'ivaResidual' => floatval($equipo['IvaResidual'] ?? 0),
            'residual1Pago' => floatval($equipo['Residual1Pago'] ?? 0),
            'residual3Pagos' => floatval($equipo['Residual3Pagos'] ?? 0),
            // Otros datos calculados
            'margen' => floatval($equipo['Margen'] ?? 0),
            'seguro' => floatval($equipo['Seguro'] ?? 0),
            'pagoEquipo' => floatval($equipo['PagoEquipo'] ?? 0),
            'tarifaSeguro' => floatval($equipo['TarifaSeguro'] ?? 0.006),
            // Datos de placas y GPS
            'placasCost' => floatval($equipo['CostoPlacas'] ?? 0),
            'gpsCost' => floatval($equipo['CostoGPS'] ?? 0),
            'placasMonthlyPayment' => floatval($equipo['PagoPlacas'] ?? 0),
            'gpsMonthlyPayment' => floatval($equipo['PagoGPS'] ?? 0),
            'placasInterest' => floatval($equipo['InteresPlacas'] ?? 0),
            'gpsInterest' => floatval($equipo['InteresGPS'] ?? 0),
            'placasGpsMonthlyPayment' => floatval($equipo['PagoPlacas'] ?? 0) + floatval($equipo['PagoGPS'] ?? 0),
            
            // NUEVO: Configuración GPS/Placas del catálogo para mostrar condicionalmente
            'includeGPS' => boolval($equipo['IncluirGPS'] ?? false),
            'includePlacas' => boolval($equipo['IncluirPlacas'] ?? false),
            'costoGpsAgregado' => floatval($equipo['CostoGPS'] ?? 0),
            'costoPlacasAgregado' => floatval($equipo['CostoPlacas'] ?? 0)
        ];
    }, $detailsData);
    
    enviarRespuestaJson(API_SUCCESS, 'Detalle obtenido exitosamente', [
        'cotizacion' => $cotizacion,
        'equipos' => $equipos
    ]);
    
} catch (Exception $e) {
    registrarError('Error obteniendo detalle de cotización', [
        'error' => $e->getMessage(),
        'cotizacion_id' => $cotizacionId,
        'user_id' => $_SESSION['user_id'] ?? null
    ]);
    
    enviarRespuestaJson(API_ERROR, 'Error interno del servidor', null, 500);
}
?>