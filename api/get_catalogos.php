<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../includes/functions.php';

try {
    $conn = obtenerConexionBaseDatos();
    
    // Get client types
    $clientTypesQuery = "SELECT Codigo as codigo, Descripcion as descripcion, Tasa as tasa, Comision as comision 
                        FROM Catalogo_TipoCliente 
                        WHERE Activo = 1 
                        ORDER BY Tasa ASC";
    $clientTypesStmt = $conn->prepare($clientTypesQuery);
    $clientTypesStmt->execute();
    $clientTypes = $clientTypesStmt->fetchAll();
    
    // Get equipment types with insurance rates and GPS/Placas configuration
    $equipmentQuery = "SELECT Nombre as nombre, TarifaSeguro as tarifaSeguro, 
                              IncluirPlacas as incluirPlacas, IncluirGPS as incluirGPS 
                      FROM Catalogo_Equipos 
                      WHERE Activo = 1 
                      ORDER BY Id ASC";
    $equipmentStmt = $conn->prepare($equipmentQuery);
    $equipmentStmt->execute();
    $equipment = $equipmentStmt->fetchAll();
    
    // Get brands
    $brandsQuery = "SELECT Nombre as nombre 
                   FROM Catalogo_Marcas 
                   WHERE Activo = 1 
                   ORDER BY Nombre ASC";
    $brandsStmt = $conn->prepare($brandsQuery);
    $brandsStmt->execute();
    $brands = $brandsStmt->fetchAll();
    
    // Get terms (plazos)
    $termsQuery = "SELECT Valor as valor, Descripcion as descripcion, Meses as meses 
                  FROM Catalogo_Plazos 
                  WHERE Activo = 1 
                  ORDER BY Orden ASC";
    $termsStmt = $conn->prepare($termsQuery);
    $termsStmt->execute();
    $terms = $termsStmt->fetchAll();
    
    // Get models
    $modelsQuery = "SELECT Codigo as codigo, Descripcion as descripcion, Equipo as equipo 
                   FROM Catalogo_Modelos 
                   WHERE Activo = 1 
                   ORDER BY Equipo ASC, Descripcion ASC";
    $modelsStmt = $conn->prepare($modelsQuery);
    $modelsStmt->execute();
    $models = $modelsStmt->fetchAll();
    
    // Get currencies
    $currenciesQuery = "SELECT Codigo as codigo, Descripcion as descripcion, Simbolo as simbolo 
                       FROM Catalogo_Monedas 
                       WHERE Activo = 1 
                       ORDER BY Id ASC";
    $currenciesStmt = $conn->prepare($currenciesQuery);
    $currenciesStmt->execute();
    $currencies = $currenciesStmt->fetchAll();
    
    // Get additional costs (GPS and Placas)
    $additionalCostsQuery = "SELECT Codigo as codigo, Descripcion as descripcion, Costo as costo 
                            FROM Catalogo_CostosAdicionales 
                            WHERE Activo = 1 
                            ORDER BY Codigo ASC";
    $additionalCostsStmt = $conn->prepare($additionalCostsQuery);
    $additionalCostsStmt->execute();
    $additionalCosts = $additionalCostsStmt->fetchAll();
    
    // Prepare response data
    $data = [
        'clientTypes' => $clientTypes,
        'equipment' => $equipment,
        'brands' => $brands,
        'terms' => $terms,
        'models' => $models,
        'currencies' => $currencies,
        'additionalCosts' => $additionalCosts
    ];
    
    enviarRespuestaJson(API_SUCCESS, 'Catálogos cargados exitosamente', $data);
    
} catch (Exception $e) {
    registrarError('Error loading catalogs', [
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    
    enviarRespuestaJson(API_ERROR, 'Error al cargar los catálogos', null, 500);
}
?>