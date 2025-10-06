<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

// Initialize PDO connection
$database = new Database();
$pdo = $database->getConnection();

/**
 * Genera un código alfanumérico único para folios
 */
function generarCodigoAlfanumerico($longitud = 4) {
    $caracteres = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $codigo = '';
    for ($i = 0; $i < $longitud; $i++) {
        $codigo .= $caracteres[random_int(0, strlen($caracteres) - 1)];
    }
    return $codigo;
}

/**
 * Genera un folio único para una cotización
 */
function generarFolioUnico($cotizacionId = null) {
    global $pdo;
    
    try {
        // Obtener configuración
        $stmt = $pdo->prepare("SELECT ClaveSetting, Valor FROM Configuracion_Enlaces WHERE ClaveSetting IN ('prefijo_folio', 'longitud_codigo')");
        $stmt->execute();
        $config = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        $prefijo = $config['prefijo_folio'] ?? 'WIS';
        $longitud = intval($config['longitud_codigo'] ?? 4);
        $año = date('Y');
        
        $intentos = 0;
        $maxIntentos = 50;
        
        do {
            $codigo = generarCodigoAlfanumerico($longitud);
            $folio = "{$prefijo}-{$año}-{$codigo}";
            
            // Verificar que no existe
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM Cotizacion_Header WHERE Folio = ?");
            $stmt->execute([$folio]);
            $existe = $stmt->fetchColumn() > 0;
            
            $intentos++;
        } while ($existe && $intentos < $maxIntentos);
        
        if ($existe) {
            throw new Exception('No se pudo generar un folio único después de ' . $maxIntentos . ' intentos');
        }
        
        return $folio;
        
    } catch (Exception $e) {
        error_log("Error al generar folio único: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Crea o actualiza el enlace compartido para una cotización
 */
function crearEnlaceCompartido($cotizacionId, $regenerar = false) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // Verificar que la cotización existe
        $stmt = $pdo->prepare("SELECT Id, Folio, EnlaceActivo FROM Cotizacion_Header WHERE Id = ?");
        $stmt->execute([$cotizacionId]);
        $cotizacion = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$cotizacion) {
            throw new Exception('Cotización no encontrada');
        }
        
        // Si ya tiene folio y no se requiere regenerar
        if ($cotizacion['Folio'] && !$regenerar) {
            $pdo->rollback();
            return [
                'success' => true,
                'folio' => $cotizacion['Folio'],
                'regenerado' => false,
                'message' => 'Enlace ya existe'
            ];
        }
        
        // Obtener duración por defecto
        $stmt = $pdo->prepare("SELECT Valor FROM Configuracion_Enlaces WHERE ClaveSetting = 'duracion_default_horas'");
        $stmt->execute();
        $duracionHoras = intval($stmt->fetchColumn() ?? 24);
        
        // Generar nuevo folio
        $nuevoFolio = generarFolioUnico($cotizacionId);
        $fechaCreacion = date('Y-m-d H:i:s');
        $fechaExpiracion = date('Y-m-d H:i:s', strtotime("+{$duracionHoras} hours"));
        
        // Actualizar la cotización
        $stmt = $pdo->prepare("
            UPDATE Cotizacion_Header 
            SET Folio = ?, 
                FolioCreado = ?, 
                EnlaceActivo = 1,
                FechaExpiracion = ?,
                TiempoExpiracionHoras = ?,
                DeshabilitadoPermanente = 0
            WHERE Id = ?
        ");
        
        $stmt->execute([
            $nuevoFolio,
            $fechaCreacion,
            $fechaExpiracion,
            $duracionHoras,
            $cotizacionId
        ]);
        
        $pdo->commit();
        
        return [
            'success' => true,
            'folio' => $nuevoFolio,
            'regenerado' => true,
            'fecha_expiracion' => $fechaExpiracion,
            'message' => 'Enlace compartido creado exitosamente'
        ];
        
    } catch (Exception $e) {
        $pdo->rollback();
        error_log("Error al crear enlace compartido: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Valida si un enlace compartido es válido y accesible
 */
function validarEnlaceCompartido($folio) {
    global $pdo;

    // Inicializar PDO si no está disponible
    if (!isset($pdo)) {
        $database = new Database();
        $pdo = $database->getConnection();
    }

    try {
        $stmt = $pdo->prepare("
            SELECT Id, EnlaceActivo, FechaExpiracion, DeshabilitadoPermanente, NombreCliente
            FROM Cotizacion_Header 
            WHERE Folio = ?
        ");
        $stmt->execute([$folio]);
        $cotizacion = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$cotizacion) {
            return ['valido' => false, 'motivo' => 'enlace_invalido'];
        }
        
        // Verificar si está deshabilitado permanentemente
        if ($cotizacion['DeshabilitadoPermanente']) {
            return ['valido' => false, 'motivo' => 'enlace_deshabilitado'];
        }
        
        // Verificar si está activo
        if (!$cotizacion['EnlaceActivo']) {
            return ['valido' => false, 'motivo' => 'enlace_inactivo'];
        }
        
        // Verificar expiración
        $ahora = new DateTime();
        $fechaExpiracion = new DateTime($cotizacion['FechaExpiracion']);
        
        if ($ahora > $fechaExpiracion) {
            return ['valido' => false, 'motivo' => 'enlace_expirado', 'fecha_expiracion' => $cotizacion['FechaExpiracion']];
        }
        
        return [
            'valido' => true,
            'cotizacion_id' => $cotizacion['Id'],
            'cliente' => $cotizacion['NombreCliente']
        ];
        
    } catch (Exception $e) {
        error_log("Error al validar enlace compartido: " . $e->getMessage());
        return ['valido' => false, 'motivo' => 'error_sistema'];
    }
}

/**
 * Registra un acceso al enlace compartido
 */
function registrarAccesoEnlace($folio, $exitoso = true, $cotizacionId = null) {
    global $pdo;
    
    try {
        if (!$cotizacionId) {
            $stmt = $pdo->prepare("SELECT Id FROM Cotizacion_Header WHERE Folio = ?");
            $stmt->execute([$folio]);
            $cotizacionId = $stmt->fetchColumn();
        }
        
        if ($cotizacionId) {
            $stmt = $pdo->prepare("
                INSERT INTO Enlaces_Compartidos_Log 
                (CotizacionId, Folio, IpAcceso, UserAgent, Exitoso) 
                VALUES (?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $cotizacionId,
                $folio,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null,
                $exitoso ? 1 : 0
            ]);
        }
    } catch (Exception $e) {
        error_log("Error al registrar acceso a enlace: " . $e->getMessage());
    }
}

/**
 * Gestiona la visibilidad del enlace compartido (para administradores)
 */
function gestionarVisibilidadEnlace($cotizacionId, $accion, $parametros = []) {
    global $pdo;

    // Inicializar PDO si no está disponible
    if (!isset($pdo)) {
        $database = new Database();
        $pdo = $database->getConnection();
    }

    try {
        $pdo->beginTransaction();
        
        switch ($accion) {
            case 'activar':
                // Al reactivar, también extender la expiración por 24 horas desde ahora
                $nuevaExpiracion = date('Y-m-d H:i:s', strtotime('+24 hours'));
                $stmt = $pdo->prepare("
                    UPDATE Cotizacion_Header
                    SET EnlaceActivo = 1,
                        DeshabilitadoPermanente = 0,
                        FechaExpiracion = ?,
                        TiempoExpiracionHoras = 24
                    WHERE Id = ?
                ");
                $stmt->execute([$nuevaExpiracion, $cotizacionId]);
                break;
                
            case 'desactivar':
                $stmt = $pdo->prepare("UPDATE Cotizacion_Header SET EnlaceActivo = 0 WHERE Id = ?");
                $stmt->execute([$cotizacionId]);
                break;
                
            case 'deshabilitar_permanente':
                $stmt = $pdo->prepare("UPDATE Cotizacion_Header SET EnlaceActivo = 0, DeshabilitadoPermanente = 1 WHERE Id = ?");
                $stmt->execute([$cotizacionId]);
                break;
                
            case 'extender_expiracion':
                $horasAdicionales = intval($parametros['horas'] ?? 24);
                $stmt = $pdo->prepare("
                    UPDATE Cotizacion_Header 
                    SET FechaExpiracion = DATE_ADD(FechaExpiracion, INTERVAL ? HOUR),
                        TiempoExpiracionHoras = TiempoExpiracionHoras + ?
                    WHERE Id = ?
                ");
                $stmt->execute([$horasAdicionales, $horasAdicionales, $cotizacionId]);
                break;
                
            case 'cambiar_expiracion':
                $nuevaFechaExpiracion = $parametros['fecha_expiracion'];
                $stmt = $pdo->prepare("UPDATE Cotizacion_Header SET FechaExpiracion = ? WHERE Id = ?");
                $stmt->execute([$nuevaFechaExpiracion, $cotizacionId]);
                break;
                
            default:
                throw new Exception('Acción no válida');
        }
        
        $pdo->commit();
        return ['success' => true, 'message' => 'Acción realizada exitosamente'];
        
    } catch (Exception $e) {
        $pdo->rollback();
        error_log("Error al gestionar visibilidad de enlace: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Obtiene información del enlace compartido para una cotización
 */
function obtenerInfoEnlaceCompartido($cotizacionId) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT Folio, FolioCreado, EnlaceActivo, FechaExpiracion, 
                   TiempoExpiracionHoras, DeshabilitadoPermanente
            FROM Cotizacion_Header 
            WHERE Id = ?
        ");
        $stmt->execute([$cotizacionId]);
        $info = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($info && $info['Folio']) {
            // Calcular estado
            $ahora = new DateTime();
            $fechaExpiracion = new DateTime($info['FechaExpiracion']);
            $expirado = $ahora > $fechaExpiracion;
            
            $info['expirado'] = $expirado;
            $info['tiempo_restante'] = $expirado ? 0 : $fechaExpiracion->getTimestamp() - $ahora->getTimestamp();
            $info['url_publica'] = obtenerUrlPublica($info['Folio']);
        }
        
        return $info;
        
    } catch (Exception $e) {
        error_log("Error al obtener info de enlace compartido: " . $e->getMessage());
        return null;
    }
}

/**
 * Genera la URL pública para un folio
 */
function obtenerUrlPublica($folio) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    // Si el host no incluye puerto y estamos en localhost, usar puerto estándar
    if ($host === 'localhost' || $host === '127.0.0.1') {
        $host = 'localhost'; // Asegurar puerto 80 estándar
    }

    return $protocol . $host . '/cotizacion?folio=' . urlencode($folio);
}

/**
 * Genera código QR para un folio
 */
function generarQR($folio, $tamaño = null) {
    try {
        // Obtener tamaño de configuración si no se especifica
        if (!$tamaño) {
            global $pdo;
            $stmt = $pdo->prepare("SELECT Valor FROM Configuracion_Enlaces WHERE ClaveSetting = 'qr_size'");
            $stmt->execute();
            $tamaño = intval($stmt->fetchColumn() ?? 200);
        }
        
        $url = obtenerUrlPublica($folio);
        
        // Usar API gratuita de QR
        $qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size={$tamaño}x{$tamaño}&data=" . urlencode($url);
        
        return [
            'success' => true,
            'qr_url' => $qrUrl,
            'enlace_publico' => $url
        ];
        
    } catch (Exception $e) {
        error_log("Error al generar QR: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Error al generar código QR'
        ];
    }
}
?>