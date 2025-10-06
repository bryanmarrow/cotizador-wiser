<?php
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

// Verificar permisos (solo admin y vendor pueden editar)
if ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'vendor') {
    enviarRespuestaJson(API_ERROR, 'No tienes permisos para acceder a los catálogos', null, 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    enviarRespuestaJson(API_ERROR, 'Método no permitido', null, 405);
}

try {
    $conn = obtenerConexionBaseDatos();
    
    // Obtener tipos de cliente (solo para validación de rangos de tasa)
    $queryTiposCliente = "SELECT Codigo, Descripcion, Tasa, Comision FROM Catalogo_TipoCliente WHERE Activo = 1 ORDER BY Codigo";
    $stmtTiposCliente = $conn->prepare($queryTiposCliente);
    $stmtTiposCliente->execute();
    $tiposCliente = $stmtTiposCliente->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener tipos de equipo con configuración GPS/Placas
    $queryEquipos = "SELECT Id, Nombre, TarifaSeguro, IncluirPlacas, IncluirGPS FROM Catalogo_Equipos WHERE Activo = 1 ORDER BY Nombre";
    $stmtEquipos = $conn->prepare($queryEquipos);
    $stmtEquipos->execute();
    $equipos = $stmtEquipos->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener marcas
    $queryMarcas = "SELECT Id, Nombre FROM Catalogo_Marcas WHERE Activo = 1 ORDER BY Nombre";
    $stmtMarcas = $conn->prepare($queryMarcas);
    $stmtMarcas->execute();
    $marcas = $stmtMarcas->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener modelos
    $queryModelos = "SELECT Id, Codigo, Descripcion, Equipo FROM Catalogo_Modelos WHERE Activo = 1 ORDER BY Equipo, Descripcion";
    $stmtModelos = $conn->prepare($queryModelos);
    $stmtModelos->execute();
    $modelos = $stmtModelos->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener plazos
    $queryPlazos = "SELECT Id, Valor, Descripcion, Meses FROM Catalogo_Plazos WHERE Activo = 1 ORDER BY Orden";
    $stmtPlazos = $conn->prepare($queryPlazos);
    $stmtPlazos->execute();
    $plazos = $stmtPlazos->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener monedas
    $queryMonedas = "SELECT Id, Codigo, Descripcion, Simbolo FROM Catalogo_Monedas WHERE Activo = 1 ORDER BY Codigo";
    $stmtMonedas = $conn->prepare($queryMonedas);
    $stmtMonedas->execute();
    $monedas = $stmtMonedas->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener costos adicionales (GPS y Placas)
    $queryCostosAdicionales = "SELECT Codigo, Descripcion, Costo FROM Catalogo_CostosAdicionales WHERE Activo = 1 ORDER BY Codigo";
    $stmtCostosAdicionales = $conn->prepare($queryCostosAdicionales);
    $stmtCostosAdicionales->execute();
    $costosAdicionales = $stmtCostosAdicionales->fetchAll(PDO::FETCH_ASSOC);
    
    // Formatear datos para compatibilidad con wizard.js
    $clientTypes = array_map(function($tipo) {
        return [
            'codigo' => $tipo['Codigo'],
            'descripcion' => $tipo['Descripcion'],
            'tasa' => floatval($tipo['Tasa']),
            'comision' => floatval($tipo['Comision'])
        ];
    }, $tiposCliente);
    
    $equipment = array_map(function($equipo) {
        return [
            'id' => intval($equipo['Id']),
            'nombre' => $equipo['Nombre'],
            'tarifaSeguro' => floatval($equipo['TarifaSeguro']),
            'incluirPlacas' => boolval($equipo['IncluirPlacas']),
            'incluirGPS' => boolval($equipo['IncluirGPS'])
        ];
    }, $equipos);
    
    $brands = array_map(function($marca) {
        return [
            'id' => intval($marca['Id']),
            'nombre' => $marca['Nombre']
        ];
    }, $marcas);
    
    $models = array_map(function($modelo) {
        return [
            'id' => intval($modelo['Id']),
            'codigo' => $modelo['Codigo'],
            'descripcion' => $modelo['Descripcion'],
            'equipo' => $modelo['Equipo']
        ];
    }, $modelos);
    
    $terms = array_map(function($plazo) {
        return [
            'id' => intval($plazo['Id']),
            'valor' => $plazo['Valor'],
            'descripcion' => $plazo['Descripcion'],
            'meses' => $plazo['Meses'] ? intval($plazo['Meses']) : null
        ];
    }, $plazos);
    
    $currencies = array_map(function($moneda) {
        return [
            'id' => intval($moneda['Id']),
            'codigo' => $moneda['Codigo'],
            'descripcion' => $moneda['Descripcion'],
            'simbolo' => $moneda['Simbolo']
        ];
    }, $monedas);
    
    $additionalCosts = array_map(function($costo) {
        return [
            'codigo' => $costo['Codigo'],
            'descripcion' => $costo['Descripcion'],
            'costo' => floatval($costo['Costo'])
        ];
    }, $costosAdicionales);
    
    // Restricciones de validación (según wizard.js)
    $restricciones = [
        'plazo' => ['min' => 12, 'max' => 36],
        'residual' => ['min' => 10, 'max' => 25],
        'cantidad' => ['min' => 1, 'max' => 10],
        'costo' => ['min' => 0.01]
    ];
    
    enviarRespuestaJson(API_SUCCESS, 'Catálogos obtenidos exitosamente', [
        'clientTypes' => $clientTypes,
        'equipment' => $equipment,
        'brands' => $brands,
        'models' => $models,
        'terms' => $terms,
        'currencies' => $currencies,
        'additionalCosts' => $additionalCosts,
        'restricciones' => $restricciones
    ]);
    
} catch (Exception $e) {
    registrarError('Error obteniendo catálogos para edición', [
        'error' => $e->getMessage(),
        'user_id' => $_SESSION['user_id'] ?? null
    ]);
    
    enviarRespuestaJson(API_ERROR, 'Error interno del servidor', null, 500);
}
?>