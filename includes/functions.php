<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';

function enviarRespuestaJson($estado, $mensaje, $datos = null, $codigo = 200) {
    http_response_code($codigo);
    header('Content-Type: application/json; charset=utf-8');
    
    $respuesta = [
        'status' => $estado,
        'message' => $mensaje,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    if ($datos !== null) {
        $respuesta['data'] = $datos;
    }
    
    echo json_encode($respuesta, JSON_UNESCAPED_UNICODE);
    exit;
}

function validarCamposRequeridos($campos, $datos) {
    $faltantes = [];
    foreach ($campos as $campo) {
        if (!isset($datos[$campo]) || trim($datos[$campo]) === '') {
            $faltantes[] = $campo;
        }
    }
    return $faltantes;
}

function sanitizarEntrada($entrada) {
    return htmlspecialchars(trim($entrada), ENT_QUOTES, 'UTF-8');
}

function validarNumerico($valor, $minimo = null, $maximo = null) {
    if (!is_numeric($valor)) {
        return false;
    }
    
    $numero = (float)$valor;
    
    if ($minimo !== null && $numero < $minimo) {
        return false;
    }
    
    if ($maximo !== null && $numero > $maximo) {
        return false;
    }
    
    return true;
}

function calcularValoresCotizacion($costo, $tasa, $plazo, $residual = DEFAULT_RESIDUAL, $tarifaSeguro = 0.006) {
    // Cálculo de margen: 1 + (plazo * (tasa/100))
    $margen = 1 + ($plazo * ($tasa / 100));
    
    // Costo de venta: margen * costo
    $costoVenta = $margen * $costo;
    
    // Pago del equipo: ((costoVenta - (costoVenta * residual%)) / plazo)
    $montoResidual = $costoVenta * ($residual / 100);
    $pagoEquipo = ($costoVenta - $montoResidual) / $plazo;
    
    // Seguro: costo * tarifa de seguro
    $seguro = $costo * $tarifaSeguro;
    
    return [
        'margin' => round($margen, 4),
        'saleCost' => round($costoVenta, 2),
        'equipmentPayment' => round($pagoEquipo, 2),
        'insurance' => round($seguro, 2),
        'residualAmount' => round($montoResidual, 2),
        'totalPayment' => round($pagoEquipo + $seguro, 2)
    ];
}

function formatearMoneda($monto, $moneda = DEFAULT_CURRENCY) {
    return $moneda . ' ' . number_format($monto, 2);
}

function registrarError($mensaje, $contexto = []) {
    $entradaLog = [
        'timestamp' => date('Y-m-d H:i:s'),
        'message' => $mensaje,
        'context' => $contexto
    ];
    
    error_log(json_encode($entradaLog));
}

function obtenerConexionBaseDatos() {
    try {
        $baseDatos = new Database();
        return $baseDatos->getConnection();
    } catch (Exception $e) {
        registrarError('Falló la conexión a la base de datos', ['error' => $e->getMessage()]);
        enviarRespuestaJson(API_ERROR, 'Falló la conexión a la base de datos', null, 500);
    }
}

function iniciarSesion() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function obtenerIdSesion() {
    iniciarSesion();
    return session_id();
}

function obtenerIdCotizacionActual() {
    iniciarSesion();
    return isset($_SESSION['current_quote_id']) ? (int)$_SESSION['current_quote_id'] : null;
}

function establecerIdCotizacionActual($idCotizacion) {
    iniciarSesion();
    $_SESSION['current_quote_id'] = $idCotizacion;
}

function limpiarCotizacionActual() {
    iniciarSesion();
    unset($_SESSION['current_quote_id']);
}

function esVendedor() {
    // Si hay sesión activa, verificar por rol del usuario
    if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['role'])) {
        return $_SESSION['role'] === 'vendor' || $_SESSION['role'] === 'admin';
    }
    
    // Fallback al sistema anterior con parámetro GET
    return isset($_GET['vendor']) && $_GET['vendor'] == '1';
}

function puedeVerInformacionSensible() {
    return esVendedor();
}

/**
 * Obtiene el costo actual de un concepto adicional desde la base de datos
 * @param string $codigo Código del concepto (PLACAS, GPS)
 * @return float Costo del concepto
 */
function obtenerCostoAdicional($codigo) {
    try {
        $database = new Database();
        $conn = $database->getConnection();
        
        $stmt = $conn->prepare("SELECT Costo FROM Catalogo_CostosAdicionales WHERE Codigo = ? AND Activo = 1");
        $stmt->execute([$codigo]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? (float)$result['Costo'] : 0.00;
    } catch (Exception $e) {
        registrarError('Error obteniendo costo adicional', [
            'codigo' => $codigo,
            'error' => $e->getMessage()
        ]);
        return 0.00;
    }
}

/**
 * Calcula el pago diferido con interés para placas y GPS
 * @param float $costoBase Costo base del concepto
 * @param int $plazoMeses Plazo en meses
 * @param float $factorInteres Factor de interés (ej: 1.10 para 10% anual)
 * @return array Array con pagoMensual e interesTotal
 */
function calcularPagoDiferido($costoBase, $plazoMeses, $factorInteres = 1.10) {
    if ($costoBase <= 0 || $plazoMeses <= 0) {
        return [
            'pagoMensual' => 0.00,
            'interesTotal' => 0.00,
            'costoConInteres' => $costoBase
        ];
    }
    
    // Aplicar factor de interés basado en el plazo en años
    $anios = $plazoMeses / 12.0;
    $costoConInteres = $costoBase * pow($factorInteres, $anios);
    
    // Calcular pago mensual
    $pagoMensual = $costoConInteres / $plazoMeses;
    
    // Calcular interés total
    $interesTotal = $costoConInteres - $costoBase;
    
    return [
        'pagoMensual' => round($pagoMensual, 2),
        'interesTotal' => round($interesTotal, 2),
        'costoConInteres' => round($costoConInteres, 2)
    ];
}

/**
 * Calcula los valores completos de cotización incluyendo placas y GPS
 * @param float $costo Costo del equipo
 * @param float $tasa Tasa del cliente
 * @param int $plazo Plazo en meses
 * @param int $residual Porcentaje residual
 * @param float $tarifaSeguro Tarifa de seguro
 * @return array Cálculos completos incluyendo placas y GPS
 */
function calcularValoresCotizacionCompleta($costo, $tasa, $plazo, $residual = DEFAULT_RESIDUAL, $tarifaSeguro = 0.006) {
    // Cálculos básicos del equipo
    $calculosEquipo = calcularValoresCotizacion($costo, $tasa, $plazo, $residual, $tarifaSeguro);
    
    // Obtener costos de placas y GPS
    $costoPlacas = obtenerCostoAdicional('PLACAS');
    $costoGPS = obtenerCostoAdicional('GPS');
    
    // Calcular pagos diferidos para placas y GPS
    $calculosPlacas = calcularPagoDiferido($costoPlacas, $plazo);
    $calculosGPS = calcularPagoDiferido($costoGPS, $plazo);
    
    // Combinar todos los cálculos
    return array_merge($calculosEquipo, [
        'placas' => [
            'costo' => $costoPlacas,
            'pagoMensual' => $calculosPlacas['pagoMensual'],
            'interesTotal' => $calculosPlacas['interesTotal'],
            'costoConInteres' => $calculosPlacas['costoConInteres']
        ],
        'gps' => [
            'costo' => $costoGPS,
            'pagoMensual' => $calculosGPS['pagoMensual'],
            'interesTotal' => $calculosGPS['interesTotal'],
            'costoConInteres' => $calculosGPS['costoConInteres']
        ],
        'totalPagoMensual' => round(
            $calculosEquipo['totalPayment'] + 
            $calculosPlacas['pagoMensual'] + 
            $calculosGPS['pagoMensual'], 
            2
        )
    ]);
}
?>