<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
// header('Access-Control-Allow-Origin: *');
// header('Access-Control-Allow-Methods: POST');
// header('Access-Control-Allow-Headers: Content-Type');
// header('Access-Control-Allow-Credentials: true');

require_once '../includes/functions.php';
require_once '../config/constants.php';

// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    enviarRespuestaJson(API_ERROR, 'No autorizado', null, 401);
}

// Verificar permisos (solo admin y vendor pueden editar)
if ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'vendor') {
    enviarRespuestaJson(API_ERROR, 'No tienes permisos para editar cotizaciones', null, 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    enviarRespuestaJson(API_ERROR, 'Método no permitido', null, 405);
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        enviarRespuestaJson(API_ERROR, 'Datos JSON inválidos', null, 400);
    }
    
    $cotizacionId = intval($input['cotizacionId'] ?? 0);
    $cambios = $input['cambios'] ?? [];
    
    if ($cotizacionId <= 0) {
        enviarRespuestaJson(API_ERROR, 'ID de cotización inválido', null, 400);
    }
    
    $conn = obtenerConexionBaseDatos();
    
    // Verificar que la cotización existe y obtener datos actuales
    $queryVerificacion = "SELECT UserId, TipoCliente FROM Cotizacion_Header WHERE Id = ?";
    $stmtVerificacion = $conn->prepare($queryVerificacion);
    $stmtVerificacion->execute([$cotizacionId]);
    $cotizacion = $stmtVerificacion->fetch(PDO::FETCH_ASSOC);
    
    if (!$cotizacion) {
        enviarRespuestaJson(API_ERROR, 'Cotización no encontrada', null, 404);
    }
    
    // Verificar permisos de acceso (vendor solo puede editar sus propias cotizaciones)
    if ($_SESSION['role'] === 'vendor' && $cotizacion['UserId'] != $_SESSION['user_id']) {
        enviarRespuestaJson(API_ERROR, 'No tienes permiso para editar esta cotización', null, 403);
    }
    
    $conn->beginTransaction();
    
    // Obtener comisión del tipo de cliente para cálculos
    $comision = 0;
    if ($cotizacion['TipoCliente']) {
        $queryComision = "SELECT Comision FROM Catalogo_TipoCliente WHERE Codigo = ? AND Activo = 1";
        $stmtComision = $conn->prepare($queryComision);
        $stmtComision->execute([$cotizacion['TipoCliente']]);
        $tipoCliente = $stmtComision->fetch(PDO::FETCH_ASSOC);
        if ($tipoCliente) {
            $comision = floatval($tipoCliente['Comision']);
        }
    }
    
    // Actualizar datos de la cotización principal en Cotizacion_Header
    if (!empty($cambios['cotizacion'])) {
        $camposActualizar = [];
        $valoresActualizar = [];
        
        // Validar y actualizar Tasa
        if (isset($cambios['cotizacion']['tasa'])) {
            $tasa = floatval($cambios['cotizacion']['tasa']);
            if ($tasa > 0) {
                $camposActualizar[] = "Tasa = ?";
                $valoresActualizar[] = $tasa;
            }
        }
        
        // Validar y actualizar Plazo en Header
        if (isset($cambios['cotizacion']['plazo'])) {
            $plazo = intval($cambios['cotizacion']['plazo']);
            // Validar plazo: 12-36 meses
            if ($plazo >= 12 && $plazo <= 36) {
                $camposActualizar[] = "Plazo = ?";
                $valoresActualizar[] = $plazo;
            } else {
                throw new Exception("El plazo debe estar entre 12 y 36 meses");
            }
        }
        
        // Validar y actualizar P_Residual en Header
        if (isset($cambios['cotizacion']['porcentajeResidual'])) {
            $residual = floatval($cambios['cotizacion']['porcentajeResidual']);
            // Validar residual: 10-25%
            if ($residual >= 10 && $residual <= 25) {
                $camposActualizar[] = "P_Residual = ?";
                $valoresActualizar[] = $residual;
            } else {
                throw new Exception("El porcentaje residual debe estar entre 10% y 25%");
            }
        }
        
        // Actualizar TotalContrato si viene del frontend (calculado correctamente)
        if (isset($cambios['cotizacion']['totalContrato'])) {
            $totalContrato = floatval($cambios['cotizacion']['totalContrato']);
            if ($totalContrato > 0) {
                $camposActualizar[] = "TotalContrato = ?";
                $valoresActualizar[] = $totalContrato;
            }
        }
        
        // Siempre actualizar la comisión junto con otros cambios
        if (!empty($camposActualizar) || $comision > 0) {
            if ($comision > 0) {
                $camposActualizar[] = "Comision = ?";
                $valoresActualizar[] = $comision;
            }
            
            $camposActualizar[] = "FechaModificacion = NOW()";
            $valoresActualizar[] = $cotizacionId;
            
            $queryActualizar = "UPDATE Cotizacion_Header SET " . implode(', ', $camposActualizar) . " WHERE Id = ?";
            $stmtActualizar = $conn->prepare($queryActualizar);
            $stmtActualizar->execute($valoresActualizar);
        }
        
        // También actualizar Plazo y P_Residual en TODOS los detalles (deben ser iguales para todos)
        if (isset($cambios['cotizacion']['plazo']) || isset($cambios['cotizacion']['porcentajeResidual'])) {
            $camposDetalle = [];
            $valoresDetalle = [];
            
            if (isset($cambios['cotizacion']['plazo'])) {
                $plazo = intval($cambios['cotizacion']['plazo']);
                $camposDetalle[] = "Plazo = ?";
                $valoresDetalle[] = $plazo;
            }
            
            if (isset($cambios['cotizacion']['porcentajeResidual'])) {
                $residual = floatval($cambios['cotizacion']['porcentajeResidual']);
                $camposDetalle[] = "P_Residual = ?";
                $valoresDetalle[] = $residual;
            }
            
            if (!empty($camposDetalle)) {
                $valoresDetalle[] = $cotizacionId;
                $queryActualizarDetalle = "UPDATE Cotizacion_Detail SET " . implode(', ', $camposDetalle) . " WHERE IdHeader = ?";
                $stmtActualizarDetalle = $conn->prepare($queryActualizarDetalle);
                $stmtActualizarDetalle->execute($valoresDetalle);
            }
        }
    }
    
    // Eliminar equipos
    if (!empty($cambios['equiposEliminados'])) {
        foreach ($cambios['equiposEliminados'] as $equipoIdEliminar) {
            $equipoId = intval($equipoIdEliminar);
            if ($equipoId > 0) {
                $queryEliminarEquipo = "DELETE FROM Cotizacion_Detail WHERE Id = ? AND IdHeader = ?";
                $stmtEliminarEquipo = $conn->prepare($queryEliminarEquipo);
                $stmtEliminarEquipo->execute([$equipoId, $cotizacionId]);
                
                if ($stmtEliminarEquipo->rowCount() === 0) {
                    throw new Exception("No se pudo eliminar el equipo con ID $equipoId");
                }
            }
        }
    }

    // Agregar nuevos equipos
    if (!empty($cambios['equiposNuevos'])) {
        // Obtener datos del header para los nuevos equipos
        $queryHeaderData = "SELECT Tasa, Plazo, P_Residual FROM Cotizacion_Header WHERE Id = ?";
        $stmtHeaderData = $conn->prepare($queryHeaderData);
        $stmtHeaderData->execute([$cotizacionId]);
        $headerData = $stmtHeaderData->fetch(PDO::FETCH_ASSOC);
        
        if (!$headerData) {
            throw new Exception("No se encontraron datos del header de la cotización");
        }
        
        foreach ($cambios['equiposNuevos'] as $equipoNuevo) {
            // Validaciones
            $cantidad = intval($equipoNuevo['cantidad'] ?? 1);
            $costo = floatval($equipoNuevo['costo'] ?? 0);
            
            if ($cantidad < 1 || $cantidad > 10) {
                throw new Exception("La cantidad debe estar entre 1 y 10");
            }
            
            if ($costo <= 0) {
                throw new Exception("El costo debe ser mayor a 0");
            }
            
            // Insertar nuevo equipo con valores calculados
            $queryInsertarEquipo = "INSERT INTO Cotizacion_Detail (
                IdHeader, Equipo, Marca, Modelo, Cantidad, Costo, 
                Plazo, P_Residual, Moneda,
                CostoVenta, PagoEquipo, Seguro, Margen,
                ValorResidual, IvaResidual, Residual1Pago, Residual3Pagos,
                TarifaSeguro, CostoPlacas, CostoGPS, 
                PagoPlacas, PagoGPS, InteresPlacas, InteresGPS
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmtInsertarEquipo = $conn->prepare($queryInsertarEquipo);
            $stmtInsertarEquipo->execute([
                $cotizacionId,
                $equipoNuevo['equipo'] ?? '',
                $equipoNuevo['marca'] ?? '',
                $equipoNuevo['modelo'] ?? '',
                $cantidad,
                $costo,
                $headerData['Plazo'],
                $headerData['P_Residual'],
                'MXN', // Moneda por defecto
                floatval($equipoNuevo['costoVenta'] ?? 0),
                floatval($equipoNuevo['pagoEquipo'] ?? 0),
                floatval($equipoNuevo['seguro'] ?? 0),
                floatval($equipoNuevo['margen'] ?? 0),
                floatval($equipoNuevo['valorResidual'] ?? 0),
                floatval($equipoNuevo['ivaResidual'] ?? 0),
                floatval($equipoNuevo['residual1Pago'] ?? 0),
                floatval($equipoNuevo['residual3Pagos'] ?? 0),
                floatval($equipoNuevo['tarifaSeguro'] ?? 0.006),
                floatval($equipoNuevo['placasCost'] ?? 0),
                floatval($equipoNuevo['gpsCost'] ?? 0),
                floatval($equipoNuevo['placasMonthlyPayment'] ?? 0),
                floatval($equipoNuevo['gpsMonthlyPayment'] ?? 0),
                0, // InteresPlacas - will be calculated if needed
                0  // InteresGPS - will be calculated if needed
            ]);
        }
    }

    // Actualizar equipos individuales
    if (!empty($cambios['equipos'])) {
        foreach ($cambios['equipos'] as $equipoModificado) {
            // Validaciones según wizard.js
            $cantidad = intval($equipoModificado['cantidad'] ?? 1);
            $costo = floatval($equipoModificado['costo'] ?? 0);
            $equipoId = intval($equipoModificado['id'] ?? 0);
            
            // Validar cantidad: 1-10
            if ($cantidad < 1 || $cantidad > 10) {
                throw new Exception("La cantidad debe estar entre 1 y 10");
            }
            
            // Validar costo: debe ser mayor a 0
            if ($costo <= 0) {
                throw new Exception("El costo debe ser mayor a 0");
            }
            
            // Validar que el equipo existe y pertenece a la cotización
            if ($equipoId <= 0) {
                throw new Exception("ID de equipo inválido");
            }
            
            // Actualizar todos los campos calculados del equipo si están disponibles
            $camposEquipo = ['Cantidad = ?', 'Costo = ?'];
            $valoresEquipo = [$cantidad, $costo];
            
            // Si el frontend envió valores calculados, actualizarlos también
            if (isset($equipoModificado['costoVenta'])) {
                $camposEquipo[] = 'CostoVenta = ?';
                $valoresEquipo[] = floatval($equipoModificado['costoVenta']);
            }
            if (isset($equipoModificado['pagoEquipo'])) {
                $camposEquipo[] = 'PagoEquipo = ?';
                $valoresEquipo[] = floatval($equipoModificado['pagoEquipo']);
            }
            if (isset($equipoModificado['seguro'])) {
                $camposEquipo[] = 'Seguro = ?';
                $valoresEquipo[] = floatval($equipoModificado['seguro']);
            }
            if (isset($equipoModificado['margen'])) {
                $camposEquipo[] = 'Margen = ?';
                $valoresEquipo[] = floatval($equipoModificado['margen']);
            }
            if (isset($equipoModificado['valorResidual'])) {
                $camposEquipo[] = 'ValorResidual = ?';
                $valoresEquipo[] = floatval($equipoModificado['valorResidual']);
            }
            if (isset($equipoModificado['ivaResidual'])) {
                $camposEquipo[] = 'IvaResidual = ?';
                $valoresEquipo[] = floatval($equipoModificado['ivaResidual']);
            }
            if (isset($equipoModificado['residual1Pago'])) {
                $camposEquipo[] = 'Residual1Pago = ?';
                $valoresEquipo[] = floatval($equipoModificado['residual1Pago']);
            }
            if (isset($equipoModificado['residual3Pagos'])) {
                $camposEquipo[] = 'Residual3Pagos = ?';
                $valoresEquipo[] = floatval($equipoModificado['residual3Pagos']);
            }
            if (isset($equipoModificado['tarifaSeguro'])) {
                $camposEquipo[] = 'TarifaSeguro = ?';
                $valoresEquipo[] = floatval($equipoModificado['tarifaSeguro']);
            }
            // Campos de placas y GPS (nombres correctos de la BD)
            if (isset($equipoModificado['placasCost'])) {
                $camposEquipo[] = 'CostoPlacas = ?';
                $valoresEquipo[] = floatval($equipoModificado['placasCost']);
            }
            if (isset($equipoModificado['gpsCost'])) {
                $camposEquipo[] = 'CostoGPS = ?';
                $valoresEquipo[] = floatval($equipoModificado['gpsCost']);
            }
            if (isset($equipoModificado['placasMonthlyPayment'])) {
                $camposEquipo[] = 'PagoPlacas = ?';
                $valoresEquipo[] = floatval($equipoModificado['placasMonthlyPayment']);
            }
            if (isset($equipoModificado['gpsMonthlyPayment'])) {
                $camposEquipo[] = 'PagoGPS = ?';
                $valoresEquipo[] = floatval($equipoModificado['gpsMonthlyPayment']);
            }
            // Calcular intereses automáticamente si se proporcionan los costos
            // Obtener plazo de los cambios o usar el plazo actual de la cotización
            $plazoParaInteres = 18; // default
            if (isset($cambios['cotizacion']['plazo'])) {
                $plazoParaInteres = intval($cambios['cotizacion']['plazo']);
            } else {
                // Obtener plazo actual de la BD si no hay cambios
                $queryPlazoActual = "SELECT Plazo FROM Cotizacion_Header WHERE Id = ?";
                $stmtPlazoActual = $conn->prepare($queryPlazoActual);
                $stmtPlazoActual->execute([$cotizacionId]);
                $plazoActualResult = $stmtPlazoActual->fetch(PDO::FETCH_ASSOC);
                if ($plazoActualResult) {
                    $plazoParaInteres = intval($plazoActualResult['Plazo']);
                }
            }
            
            if (isset($equipoModificado['placasCost'])) {
                $placasCost = floatval($equipoModificado['placasCost']);
                $factorInteres = 1.10;
                $anios = $plazoParaInteres / 12.0;
                $costoPlacasConInteres = $placasCost * pow($factorInteres, $anios);
                $interesPlacas = $costoPlacasConInteres - $placasCost;
                $camposEquipo[] = 'InteresPlacas = ?';
                $valoresEquipo[] = $interesPlacas;
            }
            if (isset($equipoModificado['gpsCost'])) {
                $gpsCost = floatval($equipoModificado['gpsCost']);
                $factorInteres = 1.10;
                $anios = $plazoParaInteres / 12.0;
                $costoGPSConInteres = $gpsCost * pow($factorInteres, $anios);
                $interesGPS = $costoGPSConInteres - $gpsCost;
                $camposEquipo[] = 'InteresGPS = ?';
                $valoresEquipo[] = $interesGPS;
            }
            
            $valoresEquipo[] = $equipoId;
            $valoresEquipo[] = $cotizacionId;
            
            $queryActualizarEquipo = "UPDATE Cotizacion_Detail SET " . implode(', ', $camposEquipo) . " WHERE Id = ? AND IdHeader = ?";
            $stmtActualizarEquipo = $conn->prepare($queryActualizarEquipo);
            $resultado = $stmtActualizarEquipo->execute($valoresEquipo);
            
            // Verificar que se actualizó al menos una fila
            if ($stmtActualizarEquipo->rowCount() === 0) {
                throw new Exception("No se pudo actualizar el equipo con ID $equipoId");
            }
        }
    }
    
    // Recalcular totales de la cotización solo si no se envió totalContrato desde el frontend
    if (!isset($cambios['cotizacion']['totalContrato'])) {
        recalcularTotalesCotizacion($conn, $cotizacionId);
    }
    
    $conn->commit();
    
    enviarRespuestaJson(API_SUCCESS, 'Cotización actualizada exitosamente', [
        'cotizacionId' => $cotizacionId
    ]);
    
} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollBack();
    }
    
    registrarError('Error actualizando cotización', [
        'error' => $e->getMessage(),
        'cotizacion_id' => $cotizacionId ?? null,
        'user_id' => $_SESSION['user_id'] ?? null,
        'cambios' => $cambios ?? null
    ]);
    
    // Mostrar errores de validación específicos al usuario
    $mensaje = $e->getMessage();
    if (strpos($mensaje, 'debe estar entre') !== false || 
        strpos($mensaje, 'debe ser mayor') !== false ||
        strpos($mensaje, 'ID de equipo inválido') !== false ||
        strpos($mensaje, 'No se pudo actualizar') !== false) {
        enviarRespuestaJson(API_ERROR, $mensaje, null, 400);
    } else {
        enviarRespuestaJson(API_ERROR, 'Error interno del servidor', null, 500);
    }
}

function recalcularTotalesCotizacion($conn, $cotizacionId) {
    try {
        // Obtener datos completos del header incluyendo parámetros de cálculo
        $queryCotizacion = "SELECT h.Tasa, h.Comision, h.TipoCliente, h.Plazo, h.P_Residual 
                           FROM Cotizacion_Header h WHERE h.Id = ?";
        $stmtCotizacion = $conn->prepare($queryCotizacion);
        $stmtCotizacion->execute([$cotizacionId]);
        $datosHeader = $stmtCotizacion->fetch(PDO::FETCH_ASSOC);
        
        if (!$datosHeader) {
            throw new Exception("No se encontraron datos de la cotización $cotizacionId");
        }
        
        // Obtener equipos con todos los datos necesarios
        $queryEquipos = "SELECT 
                            d.Id, 
                            d.Costo, 
                            d.Cantidad, 
                            d.Plazo, 
                            d.P_Residual, 
                            d.Equipo,
                            COALESCE(e.TarifaSeguro, 0.006) as TarifaSeguroEquipo
                        FROM Cotizacion_Detail d
                        LEFT JOIN Catalogo_Equipos e ON d.Equipo = e.Nombre AND e.Activo = 1
                        WHERE d.IdHeader = ?";
        $stmtEquipos = $conn->prepare($queryEquipos);
        $stmtEquipos->execute([$cotizacionId]);
        $equipos = $stmtEquipos->fetchAll(PDO::FETCH_ASSOC);
        
        // Parámetros de cálculo
        $tasa = floatval($datosHeader['Tasa']);
        $comision = floatval($datosHeader['Comision']);
        
        // Variables para totales
        $totalContratoTotal = 0;
        $utilidadTotal = 0;
        $totalCostosCompra = 0;
        $totalCostosVenta = 0;
        $totalResidual1Pago = 0;
        $totalResidual3Pagos = 0;
        
        // Recalcular cada equipo con lógica completa del wizard.js
        foreach ($equipos as $equipo) {
            $costo = floatval($equipo['Costo']);
            $cantidad = intval($equipo['Cantidad']);
            $plazo = intval($equipo['Plazo']);
            $residual = floatval($equipo['P_Residual']);
            $tarifaSeguro = floatval($equipo['TarifaSeguroEquipo']);
            
            // Cálculos siguiendo la lógica exacta del wizard.js
            $margen = 1 + ($plazo * ($tasa / 100));
            $costoVenta = $margen * $costo; // Por unidad
            $seguro = $costo * $tarifaSeguro; // Por unidad
            
            // Residuales por unidad
            $valorResidual = $costoVenta * ($residual / 100);
            $ivaResidual = $valorResidual * 0.16;
            $residual1Pago = $valorResidual + $ivaResidual;
            $residual3Pagos = ($valorResidual + $ivaResidual) * 1.1 / 3;
            
            // ** CÁLCULOS PARA PLACAS Y GPS **
            $costosPlacas = 4200;
            $costosGPS = 3300;
            $factorInteres = 1.10;
            $anios = $plazo / 12.0;
            $costoPlacasConInteres = $costosPlacas * pow($factorInteres, $anios);
            $costoGPSConInteres = $costosGPS * pow($factorInteres, $anios);
            
            $pagoMensualPlacas = round(($costoPlacasConInteres / $plazo) * 100) / 100;
            $pagoMensualGPS = round(($costoGPSConInteres / $plazo) * 100) / 100;
            $pagoMensualPlacasGPS = $pagoMensualPlacas + $pagoMensualGPS;
            
            // Pago mensual por unidad
            $pagoMensualEquipo = ($costoVenta - $valorResidual) / $plazo;
            $subtotalEquipoSinExtras = $pagoMensualEquipo + $seguro;
            
            // NUEVO SUBTOTAL COMPLETO = Equipo + Seguro + Placas + GPS
            $subtotalCompleto = round(($subtotalEquipoSinExtras + $pagoMensualPlacasGPS) * 100) / 100;
            
            // IVA aplicado al subtotal completo
            $ivaCompleto = round($subtotalCompleto * 0.16 * 100) / 100;
            
            // Pago mensual total con placas y GPS
            $pagoMensualTotalConExtras = round(($subtotalCompleto + $ivaCompleto) * 100) / 100;
            
            // Para compatibilidad con código existente
            $pagoMensualTotal = $pagoMensualTotalConExtras;
            
            // Totales por equipo (multiplicado por cantidad)
            $costoVentaTotal = $costoVenta * $cantidad;
            $pagoEquipoTotal = $pagoMensualEquipo * $cantidad;
            $seguroTotal = $seguro * $cantidad;
            $residual1PagoTotal = $residual1Pago * $cantidad;
            $residual3PagosTotal = $residual3Pagos * $cantidad;
            
            // Total del contrato = pagos mensuales con placas y GPS × plazo × cantidad (SIGUIENDO WIZARD.JS)
            $contratoEquipo = $pagoMensualTotalConExtras * $cantidad * $plazo;
            
            // Acumular totales generales
            $totalContratoTotal += $contratoEquipo;
            $totalCostosCompra += ($costo * $cantidad);
            $totalCostosVenta += $costoVentaTotal;
            $totalResidual1Pago += $residual1PagoTotal;
            $totalResidual3Pagos += $residual3PagosTotal;
            
            // Actualizar todos los campos calculados en Cotizacion_Detail
            $queryActualizarDetalle = "UPDATE Cotizacion_Detail SET 
                                         TarifaSeguro = ?,
                                         CostoVenta = ?, 
                                         PagoEquipo = ?, 
                                         Seguro = ?, 
                                         Margen = ?,
                                         ValorResidual = ?,
                                         IvaResidual = ?,
                                         Residual1Pago = ?,
                                         Residual3Pagos = ?
                                       WHERE Id = ?";
            $stmtActualizarDetalle = $conn->prepare($queryActualizarDetalle);
            $stmtActualizarDetalle->execute([
                $tarifaSeguro,
                $costoVentaTotal, 
                $pagoEquipoTotal, 
                $seguroTotal, 
                $margen,
                $valorResidual * $cantidad,
                $ivaResidual * $cantidad,
                $residual1PagoTotal,
                $residual3PagosTotal,
                $equipo['Id']
            ]);
        }
        
        // Calcular porcentaje de utilidad usando la fórmula: 1 - (costosCompra / costosVenta)
        $porcentajeUtilidad = $totalCostosVenta > 0 ? (1 - ($totalCostosCompra / $totalCostosVenta)) : 0;
        
        // Aplicar rounding wizard.js: Math.floor(totalContrato * 100) / 100
        $totalContratoFinal = floor($totalContratoTotal * 100) / 100;
        
        // Actualizar todos los totales en Cotizacion_Header incluyendo residuales
        $queryActualizarTotales = "UPDATE Cotizacion_Header SET 
                                    TotalContrato = ?, 
                                    TotalUtilidad = ?,
                                    TotalResidual1Pago = ?,
                                    TotalResidual3Pagos = ?
                                  WHERE Id = ?";
        
        $stmtActualizarTotales = $conn->prepare($queryActualizarTotales);
        $stmtActualizarTotales->execute([
            $totalContratoFinal,
            $porcentajeUtilidad,
            $totalResidual1Pago,
            $totalResidual3Pagos,
            $cotizacionId
        ]);
    } catch (Exception $e) {
        throw new Exception("Error en recalcularTotalesCotizacion: " . $e->getMessage());
    }
}
?>
