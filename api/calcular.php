<?php
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
    
    // Validate required fields
    $requiredFields = ['cost', 'rate', 'term'];
    $missing = validateRequired($requiredFields, $input);
    
    if (!empty($missing)) {
        enviarRespuestaJson(API_ERROR, 'Campos requeridos faltantes: ' . implode(', ', $missing), null, 400);
    }
    
    // Sanitize and validate inputs
    $cost = floatval($input['cost']);
    $rate = floatval($input['rate']);
    $term = intval($input['term']);
    $quantity = isset($input['quantity']) ? intval($input['quantity']) : 1;
    $residual = isset($input['residual']) ? intval($input['residual']) : DEFAULT_RESIDUAL;
    $insuranceRate = isset($input['insuranceRate']) ? floatval($input['insuranceRate']) : 0.006;
    
    // Validate numeric ranges
    if (!validateNumeric($cost, MIN_COST, MAX_COST)) {
        enviarRespuestaJson(API_ERROR, 'Costo fuera del rango válido', null, 400);
    }
    
    if (!validateNumeric($quantity, MIN_QUANTITY, MAX_QUANTITY)) {
        enviarRespuestaJson(API_ERROR, 'Cantidad fuera del rango válido', null, 400);
    }
    
    if (!validateNumeric($term, MIN_TERM, MAX_TERM)) {
        enviarRespuestaJson(API_ERROR, 'Plazo fuera del rango válido', null, 400);
    }
    
    if ($rate <= 0 || $rate > 50) {
        enviarRespuestaJson(API_ERROR, 'Tasa fuera del rango válido', null, 400);
    }
    
    // Calculate values including plates and GPS
    $calculations = calcularValoresCotizacionCompleta($cost, $rate, $term, $residual, $insuranceRate);
    
    // Calculate totals for quantity including plates and GPS
    $totalCalculations = [
        'perUnit' => $calculations,
        'totals' => [
            'cost' => $cost * $quantity,
            'saleCost' => $calculations['saleCost'] * $quantity,
            'equipmentPayment' => $calculations['equipmentPayment'] * $quantity,
            'insurance' => $calculations['insurance'] * $quantity,
            'totalPayment' => $calculations['totalPayment'] * $quantity,
            'totalPaymentWithExtras' => $calculations['totalPagoMensual'] * $quantity,
            'placasPayment' => $calculations['placas']['pagoMensual'] * $quantity,
            'gpsPayment' => $calculations['gps']['pagoMensual'] * $quantity,
            'utility' => ($calculations['saleCost'] - $cost) * $quantity
        ],
        'inputs' => [
            'cost' => $cost,
            'rate' => $rate,
            'term' => $term,
            'quantity' => $quantity,
            'residual' => $residual,
            'insuranceRate' => $insuranceRate
        ]
    ];
    
    enviarRespuestaJson(API_SUCCESS, 'Cálculo realizado exitosamente', $totalCalculations);
    
} catch (Exception $e) {
    registrarError('Error calculating quote', [
        'error' => $e->getMessage(),
        'input' => $input ?? null,
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    
    enviarRespuestaJson(API_ERROR, 'Error al realizar el cálculo', null, 500);
}
?>