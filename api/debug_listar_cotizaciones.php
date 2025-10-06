<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../includes/functions.php';

// DEBUG: Bypass authentication for testing
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
    $_SESSION['username'] = 'admin';
    $_SESSION['full_name'] = 'Debug User';
    $_SESSION['email'] = 'debug@test.com';
    $_SESSION['role'] = 'admin';
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
    $filtroVendedor = sanitizarEntrada($_GET['vendedor'] ?? '');
    $filtroEstado = sanitizarEntrada($_GET['estado'] ?? '');
    
    // Construir la consulta base
    $sql = "SELECT c.*, u.nombre_completo as vendedor_nombre 
            FROM Cotizacion c 
            LEFT JOIN Usuario u ON c.usuario_creacion = u.id_usuario
            WHERE 1=1";
    
    $params = [];
    $types = '';
    
    // Aplicar filtros
    if (!empty($filtroCliente)) {
        $sql .= " AND c.cliente LIKE ?";
        $params[] = "%{$filtroCliente}%";
        $types .= 's';
    }
    
    if (!empty($filtroFechaDesde)) {
        $sql .= " AND DATE(c.fecha_creacion) >= ?";
        $params[] = $filtroFechaDesde;
        $types .= 's';
    }
    
    if (!empty($filtroFechaHasta)) {
        $sql .= " AND DATE(c.fecha_creacion) <= ?";
        $params[] = $filtroFechaHasta;
        $types .= 's';
    }
    
    if (!empty($filtroVendedor)) {
        $sql .= " AND u.nombre_completo LIKE ?";
        $params[] = "%{$filtroVendedor}%";
        $types .= 's';
    }
    
    if (!empty($filtroEstado)) {
        $sql .= " AND c.estado = ?";
        $params[] = $filtroEstado;
        $types .= 's';
    }
    
    // Contar total de registros
    $sqlCount = str_replace('SELECT c.*, u.nombre_completo as vendedor_nombre', 'SELECT COUNT(*)', $sql);
    
    if (!empty($params)) {
        $stmtCount = $conn->prepare($sqlCount);
        $stmtCount->bind_param($types, ...$params);
        $stmtCount->execute();
        $totalRegistros = $stmtCount->get_result()->fetch_row()[0];
        $stmtCount->close();
    } else {
        $totalRegistros = $conn->query($sqlCount)->fetch_row()[0];
    }
    
    // Aplicar ordenamiento y paginación
    $sortBy = sanitizarEntrada($_GET['sort_by'] ?? 'Id');
    $sortDir = strtoupper(sanitizarEntrada($_GET['sort_dir'] ?? 'DESC'));
    
    if (!in_array($sortDir, ['ASC', 'DESC'])) {
        $sortDir = 'DESC';
    }
    
    $campoOrdenamiento = 'c.id_cotizacion';
    switch ($sortBy) {
        case 'fecha':
            $campoOrdenamiento = 'c.fecha_creacion';
            break;
        case 'cliente':
            $campoOrdenamiento = 'c.cliente';
            break;
        case 'estado':
            $campoOrdenamiento = 'c.estado';
            break;
        case 'total':
            $campoOrdenamiento = 'c.monto_total';
            break;
    }
    
    $sql .= " ORDER BY {$campoOrdenamiento} {$sortDir} LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    $types .= 'ii';
    
    // Ejecutar consulta principal
    if (!empty($params)) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $resultado = $stmt->get_result();
    } else {
        $resultado = $conn->query($sql);
    }
    
    $cotizaciones = [];
    
    while ($fila = $resultado->fetch_assoc()) {
        // Formatear fecha
        $fechaCreacion = new DateTime($fila['fecha_creacion']);
        $fechaFormateada = $fechaCreacion->format('d/m/Y H:i');
        
        // Obtener cantidad de equipos
        $sqlEquipos = "SELECT COUNT(*) as cantidad FROM Cotizacion_Detail WHERE id_cotizacion = ?";
        $stmtEquipos = $conn->prepare($sqlEquipos);
        $stmtEquipos->bind_param('i', $fila['id_cotizacion']);
        $stmtEquipos->execute();
        $cantidadEquipos = $stmtEquipos->get_result()->fetch_assoc()['cantidad'];
        $stmtEquipos->close();
        
        $cotizaciones[] = [
            'id' => 'COT-' . str_pad($fila['id_cotizacion'], 4, '0', STR_PAD_LEFT),
            'fechaFormateada' => $fechaFormateada,
            'estado' => $fila['estado'] ?: 'borrador',
            'cliente' => $fila['cliente'] ?: 'Sin cliente',
            'vendedor' => $fila['vendedor_nombre'] ?: 'Sin vendedor',
            'totalFormateado' => '$' . number_format($fila['monto_total'], 2, '.', ','),
            'cantidadEquipos' => (int)$cantidadEquipos,
            'fechaModificacion' => $fila['fecha_modificacion'],
            'idCotizacion' => $fila['id_cotizacion'],
            'montoTotal' => $fila['monto_total']
        ];
    }
    
    if (isset($stmt)) {
        $stmt->close();
    }
    
    $totalPaginas = ceil($totalRegistros / $limit);
    
    enviarRespuestaJson(API_SUCCESS, 'Cotizaciones obtenidas correctamente', [
        'cotizaciones' => $cotizaciones,
        'paginacion' => [
            'pagina_actual' => $page,
            'total_paginas' => $totalPaginas,
            'total_registros' => $totalRegistros,
            'registros_por_pagina' => $limit
        ]
    ]);
    
} catch (Exception $e) {
    error_log('Error al obtener cotizaciones: ' . $e->getMessage());
    enviarRespuestaJson(API_ERROR, 'Error interno del servidor: ' . $e->getMessage());
}
?>