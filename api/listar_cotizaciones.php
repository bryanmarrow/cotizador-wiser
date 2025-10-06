<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../includes/functions.php';

// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    enviarRespuestaJson(API_ERROR, 'No autorizado', null, 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    enviarRespuestaJson(API_ERROR, 'Método no permitido', null, 405);
}

try {
    $conn = obtenerConexionBaseDatos();
    
    // Parámetros de filtrado y paginación
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = max(1, min(100, intval($_GET['limit'] ?? 10)));
    $offset = ($page - 1) * $limit;
    
    // Filtros
    $filtroCliente = sanitizarEntrada($_GET['cliente'] ?? '');
    $filtroFechaDesde = sanitizarEntrada($_GET['fecha_desde'] ?? '');
    $filtroFechaHasta = sanitizarEntrada($_GET['fecha_hasta'] ?? '');
    $filtroUsuario = sanitizarEntrada($_GET['usuario'] ?? '');
    $filtroEstado = sanitizarEntrada($_GET['estado'] ?? '');
    
    // Ordenamiento
    $sortField = sanitizarEntrada($_GET['sort'] ?? 'Id');
    $sortDirection = strtoupper(sanitizarEntrada($_GET['direction'] ?? 'DESC'));
    
    // Validar campo de ordenamiento
    $allowedSortFields = ['Id', 'FechaCreacion', 'NombreCliente', 'UserId', 'Estado', 'TotalContrato'];
    if (!in_array($sortField, $allowedSortFields)) {
        $sortField = 'Id';
    }
    
    // Validar dirección
    if (!in_array($sortDirection, ['ASC', 'DESC'])) {
        $sortDirection = 'DESC';
    }
    
    // Construir WHERE clause
    $whereClause = "WHERE 1=1";
    $params = [];
    
    // Restricción por rol
    if ($_SESSION['role'] === 'vendor') {
        $whereClause .= " AND h.UserId = ?";
        $params[] = $_SESSION['user_id'];
    } elseif ($_SESSION['role'] === 'client') {
        $whereClause .= " AND h.UserId = ?";
        $params[] = $_SESSION['user_id'];
    }
    
    // Filtro por cliente
    if (!empty($filtroCliente)) {
        $whereClause .= " AND h.NombreCliente LIKE ?";
        $params[] = '%' . $filtroCliente . '%';
    }
    
    // Filtro por fecha desde
    if (!empty($filtroFechaDesde)) {
        $whereClause .= " AND DATE(h.FechaCreacion) >= ?";
        $params[] = $filtroFechaDesde;
    }
    
    // Filtro por fecha hasta
    if (!empty($filtroFechaHasta)) {
        $whereClause .= " AND DATE(h.FechaCreacion) <= ?";
        $params[] = $filtroFechaHasta;
    }
    
    // Filtro por usuario (solo para admin)
    if (!empty($filtroUsuario) && $_SESSION['role'] === 'admin') {
        $whereClause .= " AND h.UserId = ?";
        $params[] = intval($filtroUsuario);
    }
    
    // Filtro por estado
    if (!empty($filtroEstado)) {
        $whereClause .= " AND h.Estado = ?";
        $params[] = $filtroEstado;
    }
    
    // Consulta para contar total de registros
    $countQuery = "SELECT COUNT(*) as total 
                   FROM Cotizacion_Header h 
                   LEFT JOIN users u ON h.UserId = u.id 
                   $whereClause";
    
    $countStmt = $conn->prepare($countQuery);
    $countStmt->execute($params);
    $totalRecords = $countStmt->fetchColumn();
    
    // Consulta principal con paginación
    $query = "SELECT h.*, u.full_name as vendedor_nombre,
                     COUNT(d.Id) as cantidad_equipos
              FROM Cotizacion_Header h
              LEFT JOIN users u ON h.UserId = u.id
              LEFT JOIN Cotizacion_Detail d ON h.Id = d.IdHeader
              $whereClause
              GROUP BY h.Id
              ORDER BY h.$sortField $sortDirection
              LIMIT $limit OFFSET $offset";
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $cotizaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formatear datos
    $cotizacionesFormateadas = array_map(function($cotizacion) {
        return [
            'id' => $cotizacion['Id'],
            'fecha' => $cotizacion['FechaCreacion'],
            'fechaFormateada' => date('d/m/Y H:i', strtotime($cotizacion['FechaCreacion'])),
            'cliente' => $cotizacion['NombreCliente'],
            'tipoCliente' => $cotizacion['TipoCliente'],
            'vendedor' => $cotizacion['vendedor_nombre'] ?? 'Usuario eliminado',
            'vendedorId' => $cotizacion['UserId'],
            'estado' => $cotizacion['Estado'],
            'estadoDisplay' => ucfirst($cotizacion['Estado']),
            'total' => floatval($cotizacion['TotalContrato'] ?? 0),
            'totalFormateado' => '$' . number_format($cotizacion['TotalContrato'] ?? 0, 2),
            'utilidad' => floatval($cotizacion['TotalUtilidad'] ?? 0),
            'cantidadEquipos' => intval($cotizacion['cantidad_equipos']),
            'moneda' => $cotizacion['Moneda'] ?? 'MXN',
            'tasa' => floatval($cotizacion['Tasa'] ?? 0)
        ];
    }, $cotizaciones);
    
    // Calcular paginación
    $totalPages = ceil($totalRecords / $limit);
    $hasNext = $page < $totalPages;
    $hasPrev = $page > 1;
    
    enviarRespuestaJson(API_SUCCESS, 'Cotizaciones obtenidas exitosamente', [
        'cotizaciones' => $cotizacionesFormateadas,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_records' => $totalRecords,
            'per_page' => $limit,
            'has_next' => $hasNext,
            'has_prev' => $hasPrev,
            'from' => $offset + 1,
            'to' => min($offset + $limit, $totalRecords)
        ],
        'filters' => [
            'cliente' => $filtroCliente,
            'fecha_desde' => $filtroFechaDesde,
            'fecha_hasta' => $filtroFechaHasta,
            'usuario' => $filtroUsuario,
            'estado' => $filtroEstado,
            'sort' => $sortField,
            'direction' => $sortDirection
        ]
    ]);
    
} catch (Exception $e) {
    registrarError('Error listando cotizaciones', [
        'error' => $e->getMessage(),
        'user_id' => $_SESSION['user_id'] ?? null,
        'filters' => $_GET
    ]);
    
    enviarRespuestaJson(API_ERROR, 'Error interno del servidor', null, 500);
}
?>