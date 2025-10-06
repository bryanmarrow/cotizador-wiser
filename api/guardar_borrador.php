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
    
    if (!$input) {
        enviarRespuestaJson(API_ERROR, 'Datos JSON inválidos', null, 400);
    }
    
    $conn = obtenerConexionBaseDatos();
    $conn->beginTransaction();
    
    $currentQuoteId = isset($input['currentQuoteId']) ? intval($input['currentQuoteId']) : null;
    $userId = isset($input['userId']) ? intval($input['userId']) : 1; // Default to 1 if not provided
    $clientType = sanitizarEntrada($input['tipoCliente'] ?? '');
    $clientName = sanitizarEntrada($input['nombreCliente'] ?? '');
    $rate = floatval($input['tasa'] ?? 0);
    $currency = sanitizarEntrada($input['moneda'] ?? 'MXN');
    $equipment = $input['equipos'] ?? [];
    $totals = $input['totales'] ?? ['contrato' => 0, 'utilidad' => 0];
    $anticipo = floatval($input['anticipo'] ?? 0);
    
    // If no client name and no equipment, nothing to save
    if (empty($clientName) && empty($equipment)) {
        enviarRespuestaJson(API_SUCCESS, 'Nada que guardar', ['quoteId' => $currentQuoteId]);
    }
    
    $quoteId = $currentQuoteId;
    
    if ($currentQuoteId) {
        // Update existing quote
        $updateHeaderQuery = "UPDATE Cotizacion_Header
                             SET UserId = ?, TipoCliente = ?, NombreCliente = ?, Tasa = ?, Moneda = ?,
                                 TotalContrato = ?, TotalUtilidad = ?, Anticipo = ?, FechaModificacion = NOW()
                             WHERE Id = ? AND Estado = ?";
        $updateStmt = $conn->prepare($updateHeaderQuery);
        $updateStmt->execute([
            $userId, $clientType, $clientName, $rate, $currency,
            $totals['contrato'], $totals['utilidad'], $anticipo,
            $currentQuoteId, STATE_DRAFT
        ]);
        
        // Delete existing equipment details
        $deleteDetailsQuery = "DELETE FROM Cotizacion_Detail WHERE IdHeader = ?";
        $deleteStmt = $conn->prepare($deleteDetailsQuery);
        $deleteStmt->execute([$currentQuoteId]);
        
    } else {
        // Create new quote
        $insertHeaderQuery = "INSERT INTO Cotizacion_Header
                             (UserId, TipoCliente, NombreCliente, Tasa, Moneda, TotalContrato, TotalUtilidad, Anticipo, Estado)
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $insertStmt = $conn->prepare($insertHeaderQuery);
        $insertStmt->execute([
            $userId, $clientType, $clientName, $rate, $currency,
            $totals['contrato'], $totals['utilidad'], $anticipo, STATE_DRAFT
        ]);

        $quoteId = $conn->lastInsertId();
    }
    
    // Insert equipment details
    if (!empty($equipment)) {
        $insertDetailQuery = "INSERT INTO Cotizacion_Detail 
                             (IdHeader, Equipo, Marca, Costo, Plazo, Moneda, Modelo, P_Residual, Cantidad, 
                              CostoVenta, PagoEquipo, Seguro, Margen, CostoPlacas, CostoGPS, PagoPlacas, PagoGPS, InteresPlacas, InteresGPS) 
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $detailStmt = $conn->prepare($insertDetailQuery);
        
        foreach ($equipment as $item) {
            $calculations = $item['calculations'] ?? [];
            
            // Mapear campos del nuevo sistema de cálculos
            $costoGpsAgregado = $calculations['costoGpsAgregado'] ?? 0;
            $costoPlacasAgregado = $calculations['costoPlacasAgregado'] ?? 0;
            
            $detailStmt->execute([
                $quoteId,
                $item['type'],
                $item['brand'],
                $item['cost'],
                $item['term'],
                $currency,
                $item['modelDisplay'] ?? $item['model'] ?? '',
                $item['residual'] ?? DEFAULT_RESIDUAL,
                $item['quantity'],
                $calculations['saleCost'] ?? 0,
                $calculations['equipmentPayment'] ?? 0,
                $calculations['insurance'] ?? 0,
                $calculations['margin'] ?? 1,
                $costoPlacasAgregado, // Nuevo sistema: costo de placas agregado al equipo
                $costoGpsAgregado,    // Nuevo sistema: costo de GPS agregado al equipo
                0, // PagoPlacas - ya no se calcula por separado en nuevo sistema
                0, // PagoGPS - ya no se calcula por separado en nuevo sistema
                0, // InteresPlacas - ya no se calcula por separado en nuevo sistema
                0  // InteresGPS - ya no se calcula por separado en nuevo sistema
            ]);
        }
    }
    
    $conn->commit();
    
    enviarRespuestaJson(API_SUCCESS, 'Borrador guardado exitosamente', [
        'quoteId' => $quoteId,
        'savedAt' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollBack();
    }
    
    registrarError('Error saving draft', [
        'error' => $e->getMessage(),
        'input' => $input ?? null,
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    
    enviarRespuestaJson(API_ERROR, 'Error al guardar el borrador', null, 500);
}
?>