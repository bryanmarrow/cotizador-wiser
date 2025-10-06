<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    enviarRespuestaJson(API_ERROR, 'Método no permitido', null, 405);
}

try {
    $conn = obtenerConexionBaseDatos();
    
    // Buscar el tipo de cliente predefinido para portal (Codigo = 'X')
    $query = "SELECT Id, Codigo, Descripcion, Tasa, Comision 
              FROM Catalogo_TipoCliente 
              WHERE Codigo = 'X' AND Activo IN (1, 2)
              LIMIT 1";
    
    $stmt = $conn->prepare($query);
    $stmt->execute();
    
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($resultado) {
        enviarRespuestaJson(API_SUCCESS, 'Tipo de cliente portal encontrado', $resultado);
    } else {
        enviarRespuestaJson(API_ERROR, 'No se encontró tipo de cliente predefinido para portal', null, 404);
    }
    
} catch (Exception $e) {
    registrarError('Error obteniendo tipo cliente portal', ['error' => $e->getMessage()]);
    enviarRespuestaJson(API_ERROR, 'Error interno del servidor', null, 500);
}
?>