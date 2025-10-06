<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    enviarRespuestaJson(API_ERROR, 'Método no permitido', null, 405);
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Debug: Log incoming data (temporarily enabled)
    error_log("Guardar Final - Input received: " . json_encode($input, JSON_PRETTY_PRINT));
    
    if (!$input) {
        enviarRespuestaJson(API_ERROR, 'Datos JSON inválidos', null, 400);
    }
    
    // Get values with defaults (similar to borrador)
    $clientType = sanitizarEntrada($input['clientType'] ?? '');
    $clientName = sanitizarEntrada($input['clientName'] ?? '');
    $rate = floatval($input['rate'] ?? 0);
    $currency = sanitizarEntrada($input['currency'] ?? 'MXN');
    $equipment = $input['equipment'] ?? [];
    $plazoGlobal = intval($input['plazoGlobal'] ?? 0);
    $residualGlobal = floatval($input['residualGlobal'] ?? 20);
    $comision = floatval($input['comision'] ?? 0);
    $anticipo = floatval($input['anticipo'] ?? 0);
    
    // Validate required fields for final save
    if (empty($clientType)) {
        enviarRespuestaJson(API_ERROR, 'Tipo de cliente es requerido', null, 400);
    }
    
    if (empty($clientName)) {
        enviarRespuestaJson(API_ERROR, 'Nombre del cliente es requerido', null, 400);
    }
    
    if ($rate <= 0) {
        enviarRespuestaJson(API_ERROR, 'Tasa debe ser mayor a cero', null, 400);
    }
    
    if (empty($equipment)) {
        enviarRespuestaJson(API_ERROR, 'Debe agregar al menos un equipo', null, 400);
    }
    
    $conn = obtenerConexionBaseDatos();
    $conn->beginTransaction();
    
    $currentQuoteId = isset($input['currentQuoteId']) ? intval($input['currentQuoteId']) : null;
    $totals = $input['totals'] ?? ['contract' => 0, 'utility' => 0];
    
    // Guardar configuración completa en JSON para poder recalcular
    $configuracion = json_encode([
        'plazoGlobal' => $plazoGlobal,
        'residualGlobal' => $residualGlobal, 
        'comision' => $comision,
        'totales' => $totals
    ], JSON_UNESCAPED_UNICODE);
    
    // Validate client type exists (Activo = 1 OR Activo = 2 para portal)
    $clientTypeQuery = "SELECT COUNT(*) FROM Catalogo_TipoCliente WHERE Codigo = ? AND Activo IN (1, 2)";
    $clientTypeStmt = $conn->prepare($clientTypeQuery);
    $clientTypeStmt->execute([$clientType]);
    
    if ($clientTypeStmt->fetchColumn() == 0) {
        enviarRespuestaJson(API_ERROR, 'Tipo de cliente inválido', null, 400);
    }
    
    $quoteId = $currentQuoteId;
    
    if ($currentQuoteId) {
        // Update existing quote to completed status with all parameters
        $updateHeaderQuery = "UPDATE Cotizacion_Header
                             SET TipoCliente = ?, NombreCliente = ?, Tasa = ?, Moneda = ?,
                                 TotalContrato = ?, TotalUtilidad = ?, Estado = ?,
                                 Plazo = ?, P_Residual = ?, Comision = ?, Anticipo = ?, UserId = ?, FechaModificacion = NOW()
                             WHERE Id = ?";
        $updateStmt = $conn->prepare($updateHeaderQuery);
        $updateStmt->execute([
            $clientType, $clientName, $rate, $currency,
            $totals['contract'], $totals['utility'], STATE_COMPLETED,
            $plazoGlobal, $residualGlobal, $comision, $anticipo, $_SESSION['user_id'] ?? null,
            $currentQuoteId
        ]);
        
        // Delete existing equipment details
        $deleteDetailsQuery = "DELETE FROM Cotizacion_Detail WHERE IdHeader = ?";
        $deleteStmt = $conn->prepare($deleteDetailsQuery);
        $deleteStmt->execute([$currentQuoteId]);
        
    } else {
        // Create new quote with completed status and all parameters
        $insertHeaderQuery = "INSERT INTO Cotizacion_Header
                             (TipoCliente, NombreCliente, Tasa, Moneda, TotalContrato, TotalUtilidad, Estado,
                              Plazo, P_Residual, Comision, Anticipo, UserId)
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $insertStmt = $conn->prepare($insertHeaderQuery);
        $insertStmt->execute([
            $clientType, $clientName, $rate, $currency,
            $totals['contract'], $totals['utility'], STATE_COMPLETED,
            $plazoGlobal, $residualGlobal, $comision, $anticipo, $_SESSION['user_id'] ?? null
        ]);

        $quoteId = $conn->lastInsertId();
    }
    
    // Insert equipment details with all calculated fields - UPDATED for new calculation system
    $insertDetailQuery = "INSERT INTO Cotizacion_Detail 
                         (IdHeader, Equipo, Marca, Costo, Plazo, Moneda, Modelo, P_Residual, Cantidad, 
                          CostoVenta, PagoEquipo, Seguro, Margen, TarifaSeguro, 
                          ValorResidual, IvaResidual, Residual1Pago, Residual3Pagos,
                          CostoPlacas, CostoGPS, PagoPlacas, PagoGPS, InteresPlacas, InteresGPS) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $detailStmt = $conn->prepare($insertDetailQuery);
    
    foreach ($equipment as $item) {
        // Basic validation (similar to borrador)
        if (empty($item['type']) || empty($item['brand'])) {
            throw new Exception('Tipo y marca de equipo son requeridos');
        }
        
        // Ensure numeric values with defaults
        $cost = floatval($item['cost'] ?? 0);
        $quantity = intval($item['quantity'] ?? 1);
        $term = intval($item['term'] ?? 36);
        
        if ($cost <= 0) {
            throw new Exception('Costo del equipo debe ser mayor a cero');
        }
        
        if ($quantity <= 0) {
            throw new Exception('Cantidad debe ser mayor a cero');
        }
        
        if ($term < 12 || $term > 84) {
            throw new Exception('Plazo debe estar entre 12 y 84 meses');
        }
        
        $calculations = $item['calculations'] ?? [];
        
        // Debug: log equipment data and calculations
        error_log("GUARDAR_FINAL DEBUG - Equipment Item: " . json_encode([
            'type' => $item['type'],
            'calculations_keys' => array_keys($calculations),
            'calculations' => $calculations
        ], JSON_PRETTY_PRINT));
        
        // Obtener tarifa de seguro del equipo desde el catálogo
        $tarifaSeguro = floatval($item['insuranceRate'] ?? 0.006);

        // Mapear campos del nuevo sistema de cálculos
        $costoGpsAgregado = $calculations['costoGpsAgregado'] ?? 0;
        $costoPlacasAgregado = $calculations['costoPlacasAgregado'] ?? 0;

        // Calcular pagos mensuales e intereses de GPS/Placas
        $COSTO_GPS_BASE = 3300;
        $COSTO_PLACAS_BASE = 4200;
        $factorInteres = 1.10;
        $anios = $term / 12.0;

        $pagoGPS = 0;
        $pagoPlacas = 0;
        $interesGPS = 0;
        $interesPlacas = 0;

        if ($costoGpsAgregado > 0) {
            // costoGpsAgregado ya tiene el interés aplicado
            $pagoGPS = round(($costoGpsAgregado / $term) * 100) / 100;
            $interesGPS = $costoGpsAgregado - $COSTO_GPS_BASE;
        }

        if ($costoPlacasAgregado > 0) {
            // costoPlacasAgregado ya tiene el interés aplicado
            $pagoPlacas = round(($costoPlacasAgregado / $term) * 100) / 100;
            $interesPlacas = $costoPlacasAgregado - $COSTO_PLACAS_BASE;
        }

        $detailStmt->execute([
            $quoteId,
            $item['type'],
            $item['brand'],
            $cost,
            $term,
            $currency,
            $item['modelDisplay'] ?? $item['model'] ?? '',
            $item['residual'] ?? $residualGlobal,
            $quantity,
            $calculations['saleCost'] ?? 0,
            $calculations['equipmentPayment'] ?? 0,
            $calculations['insurance'] ?? 0,
            $calculations['margin'] ?? 1,
            $tarifaSeguro,
            $calculations['residualAmount'] ?? 0,
            $calculations['residualIVA'] ?? 0,
            $calculations['residual1Payment'] ?? 0,
            $calculations['residual3Payments'] ?? 0,
            $COSTO_PLACAS_BASE, // CostoPlacas - costo base
            $COSTO_GPS_BASE,    // CostoGPS - costo base
            $pagoPlacas,        // PagoPlacas - pago mensual
            $pagoGPS,           // PagoGPS - pago mensual
            $interesPlacas,     // InteresPlacas - interés aplicado
            $interesGPS         // InteresGPS - interés aplicado
        ]);
    }

    // Calcular totales de residuales para el Header
    $totalResidual1Pago = 0;
    $totalResidual3Pagos = 0;

    foreach ($equipment as $item) {
        $calculations = $item['calculations'] ?? [];
        $quantity = intval($item['quantity'] ?? 1);

        $residual1Payment = floatval($calculations['residual1Payment'] ?? 0);
        $residual3Payments = floatval($calculations['residual3Payments'] ?? 0);

        $totalResidual1Pago += $residual1Payment * $quantity;
        $totalResidual3Pagos += $residual3Payments * $quantity;
    }

    // Actualizar totales de residuales en el Header
    $updateResidualsQuery = "UPDATE Cotizacion_Header
                             SET TotalResidual1Pago = ?, TotalResidual3Pagos = ?
                             WHERE Id = ?";
    $updateResidualsStmt = $conn->prepare($updateResidualsQuery);
    $updateResidualsStmt->execute([
        $totalResidual1Pago,
        $totalResidual3Pagos,
        $quoteId
    ]);

    $conn->commit();
    
    // Get complete quote data for response
    $quoteQuery = "SELECT h.*, COUNT(d.Id) as equipmentCount 
                   FROM Cotizacion_Header h 
                   LEFT JOIN Cotizacion_Detail d ON h.Id = d.IdHeader 
                   WHERE h.Id = ? 
                   GROUP BY h.Id";
    $quoteStmt = $conn->prepare($quoteQuery);
    $quoteStmt->execute([$quoteId]);
    $quoteData = $quoteStmt->fetch();
    
    enviarRespuestaJson(API_SUCCESS, 'Cotización guardada exitosamente', [
        'quoteId' => $quoteId,
        'quote' => $quoteData,
        'savedAt' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollBack();
    }
    
    // Log detailed error information
    $errorInfo = [
        'error' => $e->getMessage(),
        'input' => $input ?? null,
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ];
    
    registrarError('Error saving final quote', $errorInfo);
    
    // Send more specific error message based on the exception
    $userMessage = 'Error al guardar la cotización';
    
    if (strpos($e->getMessage(), 'Connection') !== false || strpos($e->getMessage(), 'connection') !== false) {
        $userMessage = 'Error de conexión a la base de datos';
    } elseif (strpos($e->getMessage(), 'Datos de equipo') !== false) {
        $userMessage = 'Error en los datos del equipo: ' . $e->getMessage();
    } elseif (strpos($e->getMessage(), 'Valores de equipo') !== false) {
        $userMessage = 'Valores de equipo fuera de rango válido';
    } elseif (strpos($e->getMessage(), 'Tipo de cliente') !== false) {
        $userMessage = 'Tipo de cliente inválido';
    }
    
    enviarRespuestaJson(API_ERROR, $userMessage . ' - Detalles: ' . $e->getMessage(), $errorInfo, 500);
}

function recalcularTotalesCotizacion($conn, $cotizacionId) {
    try {
        // Obtener datos completos del header incluyendo parámetros de cálculo
        $queryCotizacion = "SELECT h.Tasa, h.Comision, h.TipoCliente, h.Plazo, h.P_Residual 
                           FROM Cotizacion_Header h WHERE h.Id = ?";
        $stmtCotizacion = $conn->prepare($queryCotizacion);
        $stmtCotizacion->execute([$cotizacionId]);
        $datosHeader = $stmtCotizacion->fetch(PDO::FETCH_ASSOC);
        
        if (!$datosHeader) {
            throw new Exception("No se encontraron datos de la cotización $cotizacionId");
        }
        
        // Obtener equipos con todos los datos necesarios
        $queryEquipos = "SELECT d.Id, d.Costo, d.Cantidad, d.Plazo, d.P_Residual, d.Equipo,
                                COALESCE(e.TarifaSeguro, 0.006) as TarifaSeguroEquipo
                         FROM Cotizacion_Detail d
                         LEFT JOIN Catalogo_Equipos e ON d.Equipo = e.Nombre AND e.Activo = 1
                         WHERE d.IdHeader = ?";
        $stmtEquipos = $conn->prepare($queryEquipos);
        $stmtEquipos->execute([$cotizacionId]);
        $equipos = $stmtEquipos->fetchAll(PDO::FETCH_ASSOC);
        
        // Parámetros de cálculo
        $tasa = floatval($datosHeader['Tasa']);
        $comision = floatval($datosHeader['Comision']);
        
        // Variables para totales
        $totalContratoTotal = 0;
        $utilidadTotal = 0;
        $totalCostosCompra = 0;
        $totalCostosVenta = 0;
        $totalResidual1Pago = 0;
        $totalResidual3Pagos = 0;
        
        // Recalcular cada equipo con lógica completa del wizard.js
        foreach ($equipos as $equipo) {
            $costo = floatval($equipo['Costo']);
            $cantidad = intval($equipo['Cantidad']);
            $plazo = intval($equipo['Plazo']);
            $residual = floatval($equipo['P_Residual']);
            $tarifaSeguro = floatval($equipo['TarifaSeguroEquipo']);
            
            // Cálculos siguiendo la lógica exacta del wizard.js
            $margen = 1 + ($plazo * ($tasa / 100));
            $costoVenta = $margen * $costo; // Por unidad
            $seguro = $costo * $tarifaSeguro; // Por unidad
            
            // Residuales por unidad
            $valorResidual = $costoVenta * ($residual / 100);
            $ivaResidual = $valorResidual * 0.16;
            $residual1Pago = $valorResidual + $ivaResidual;
            $residual3Pagos = ($valorResidual + $ivaResidual) * 1.1 / 3;
            
            // ** CÁLCULOS PARA PLACAS Y GPS **
            $costosPlacas = 4200;
            $costosGPS = 3300;
            $factorInteres = 1.10;
            $anios = $plazo / 12.0;
            $costoPlacasConInteres = $costosPlacas * pow($factorInteres, $anios);
            $costoGPSConInteres = $costosGPS * pow($factorInteres, $anios);
            
            $pagoMensualPlacas = round(($costoPlacasConInteres / $plazo) * 100) / 100;
            $pagoMensualGPS = round(($costoGPSConInteres / $plazo) * 100) / 100;
            $pagoMensualPlacasGPS = $pagoMensualPlacas + $pagoMensualGPS;
            
            // Pago mensual por unidad
            $pagoMensualEquipo = ($costoVenta - $valorResidual) / $plazo;
            $subtotalEquipoSinExtras = $pagoMensualEquipo + $seguro;
            
            // NUEVO SUBTOTAL COMPLETO = Equipo + Seguro + Placas + GPS
            $subtotalCompleto = round(($subtotalEquipoSinExtras + $pagoMensualPlacasGPS) * 100) / 100;
            
            // IVA aplicado al subtotal completo
            $ivaCompleto = round($subtotalCompleto * 0.16 * 100) / 100;
            
            // Pago mensual total con placas y GPS
            $pagoMensualTotalConExtras = round(($subtotalCompleto + $ivaCompleto) * 100) / 100;
            
            // Para compatibilidad con código existente
            $pagoMensualTotal = $pagoMensualTotalConExtras;
            
            // Totales por equipo (multiplicado por cantidad)
            $costoVentaTotal = $costoVenta * $cantidad;
            $pagoEquipoTotal = $pagoMensualEquipo * $cantidad;
            $seguroTotal = $seguro * $cantidad;
            $residual1PagoTotal = $residual1Pago * $cantidad;
            $residual3PagosTotal = $residual3Pagos * $cantidad;
            
            // Total del contrato = pagos mensuales con placas y GPS × plazo + residual
            $contratoEquipo = ($pagoMensualTotalConExtras * $cantidad * $plazo) + $residual1PagoTotal;
            
            // Acumular totales generales
            $totalContratoTotal += $contratoEquipo;
            $totalCostosCompra += ($costo * $cantidad);
            $totalCostosVenta += $costoVentaTotal;
            $totalResidual1Pago += $residual1PagoTotal;
            $totalResidual3Pagos += $residual3PagosTotal;
            
            // Actualizar todos los campos calculados en Cotizacion_Detail
            $queryActualizarDetalle = "UPDATE Cotizacion_Detail SET 
                                         TarifaSeguro = ?,
                                         CostoVenta = ?, 
                                         PagoEquipo = ?, 
                                         Seguro = ?, 
                                         Margen = ?,
                                         ValorResidual = ?,
                                         IvaResidual = ?,
                                         Residual1Pago = ?,
                                         Residual3Pagos = ?
                                       WHERE Id = ?";
            $stmtActualizarDetalle = $conn->prepare($queryActualizarDetalle);
            $stmtActualizarDetalle->execute([
                $tarifaSeguro,
                $costoVentaTotal, 
                $pagoEquipoTotal, 
                $seguroTotal, 
                $margen,
                $valorResidual * $cantidad,
                $ivaResidual * $cantidad,
                $residual1PagoTotal,
                $residual3PagosTotal,
                $equipo['Id']
            ]);
        }
        
        // Calcular porcentaje de utilidad usando la fórmula: 1 - (costosCompra / costosVenta)
        $porcentajeUtilidad = $totalCostosVenta > 0 ? (1 - ($totalCostosCompra / $totalCostosVenta)) : 0;
        
        // Actualizar todos los totales en Cotizacion_Header incluyendo residuales
        $queryActualizarTotales = "UPDATE Cotizacion_Header SET 
                                    TotalContrato = ?, 
                                    TotalUtilidad = ?,
                                    TotalResidual1Pago = ?,
                                    TotalResidual3Pagos = ?
                                  WHERE Id = ?";
        
        $stmtActualizarTotales = $conn->prepare($queryActualizarTotales);
        $stmtActualizarTotales->execute([
            $totalContratoTotal,
            $porcentajeUtilidad,
            $totalResidual1Pago,
            $totalResidual3Pagos,
            $cotizacionId
        ]);
    } catch (Exception $e) {
        throw new Exception("Error en recalcularTotalesCotizacion: " . $e->getMessage());
    }
}
?>