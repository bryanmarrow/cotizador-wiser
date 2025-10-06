<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../includes/functions.php';

// Verificar autenticación y que sea admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    enviarRespuestaJson(API_ERROR, 'No autorizado', null, 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    enviarRespuestaJson(API_ERROR, 'Método no permitido', null, 405);
}

try {
    $conn = obtenerConexionBaseDatos();
    
    $query = "SELECT id, full_name, username, role 
              FROM users 
              WHERE active = 1 
              ORDER BY full_name ASC";
    
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formatear datos
    $usuariosFormateados = array_map(function($usuario) {
        return [
            'id' => intval($usuario['id']),
            'nombre' => $usuario['full_name'],
            'username' => $usuario['username'],
            'rol' => $usuario['role'],
            'rolDisplay' => ucfirst($usuario['role'])
        ];
    }, $usuarios);
    
    enviarRespuestaJson(API_SUCCESS, 'Usuarios obtenidos exitosamente', $usuariosFormateados);
    
} catch (Exception $e) {
    registrarError('Error obteniendo usuarios', [
        'error' => $e->getMessage(),
        'user_id' => $_SESSION['user_id']
    ]);
    
    enviarRespuestaJson(API_ERROR, 'Error interno del servidor', null, 500);
}
?>