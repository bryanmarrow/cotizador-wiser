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
    $quoteId = intval($_GET['id'] ?? 0);
    
    if ($quoteId <= 0) {
        enviarRespuestaJson(API_ERROR, 'ID de cotización inválido', null, 400);
    }
    
    $conn = obtenerConexionBaseDatos();
    
    // Obtener datos completos de la cotización
    $query = "SELECT h.*, d.CostoPlacas, d.CostoGPS, d.PagoPlacas, d.PagoGPS, d.InteresPlacas, d.InteresGPS,
                     d.Equipo, d.Marca, d.Costo, d.Cantidad
              FROM Cotizacion_Header h 
              LEFT JOIN Cotizacion_Detail d ON h.Id = d.IdHeader 
              WHERE h.Id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->execute([$quoteId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$result) {
        enviarRespuestaJson(API_ERROR, 'Cotización no encontrada', null, 404);
    }
    
    enviarRespuestaJson(API_SUCCESS, 'Cotización encontrada', $result);
    
} catch (Exception $e) {
    registrarError('Error verificando cotización', [
        'error' => $e->getMessage(),
        'quoteId' => $quoteId ?? null
    ]);
    
    enviarRespuestaJson(API_ERROR, 'Error al verificar cotización: ' . $e->getMessage(), null, 500);
}
?>