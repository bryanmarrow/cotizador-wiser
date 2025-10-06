<?php
header('Content-Type: text/html; charset=utf-8');

require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/shared_links.php';

// Obtener y validar el folio
$folio = $_GET['folio'] ?? '';
if (empty($folio)) {
    echo "<div class='error'>Error: Folio requerido</div>";
    exit;
}

$folio = sanitizarEntrada($folio);

// Validar enlace compartido
$validacion = validarEnlaceCompartido($folio);
if (!$validacion['valido']) {
    echo "<div class='error'>Error: Enlace inválido o expirado</div>";
    exit;
}

$cotizacionId = $validacion['cotizacion_id'];

try {
    // Obtener datos de la cotización
    $stmt = $pdo->prepare("
        SELECT 
            ch.*,
            u.full_name as VendedorNombre,
            u.email as VendedorEmail
        FROM Cotizacion_Header ch
        LEFT JOIN users u ON ch.UserId = u.id
        WHERE ch.Id = ?
    ");
    $stmt->execute([$cotizacionId]);
    $header = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$header) {
        throw new Exception('Cotización no encontrada');
    }

    // Obtener equipos de la cotización
    $stmt = $pdo->prepare("
        SELECT * FROM Cotizacion_Detail 
        WHERE IdHeader = ? 
        ORDER BY Id ASC
    ");
    $stmt->execute([$cotizacionId]);
    $details = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log("Error al obtener cotización pública: " . $e->getMessage());
    echo "<div class='error'>Error al cargar la cotización</div>";
    exit;
}

// Función para formatear fecha en español
function formatearFechaEspanol($fecha) {
    $meses = [
        1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril',
        5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto',
        9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre'
    ];
    $dias = [
        'Monday' => 'lunes', 'Tuesday' => 'martes', 'Wednesday' => 'miércoles',
        'Thursday' => 'jueves', 'Friday' => 'viernes', 'Saturday' => 'sábado', 'Sunday' => 'domingo'
    ];

    $timestamp = is_numeric($fecha) ? $fecha : strtotime($fecha);
    $diaSemana = $dias[date('l', $timestamp)];
    $dia = date('d', $timestamp);
    $mes = $meses[(int)date('m', $timestamp)];
    $anio = date('Y', $timestamp);

    return ucfirst($diaSemana) . ', ' . $dia . ' de ' . $mes . ' de ' . $anio;
}

// Obtener fecha de cotización
$fechaCotizacion = $header['FechaCreacion'] ?? date('Y-m-d H:i:s');
$fechaCotizacionFormateada = formatearFechaEspanol($fechaCotizacion);

// Calcular totales usando valores de la BD
$totalPagoEquipo = 0;
$totalSeguro = 0;
$totalPagoGPS = 0;
$totalPagoPlacas = 0;

foreach ($details as $d) {
    $cantidad = $d['Cantidad'];

    // Usar valores ya calculados en la BD
    $totalPagoEquipo += floatval($d['PagoEquipo'] ?? 0) * $cantidad;
    $totalSeguro += floatval($d['Seguro'] ?? 0) * $cantidad;
    $totalPagoGPS += floatval($d['PagoGPS'] ?? 0) * $cantidad;
    $totalPagoPlacas += floatval($d['PagoPlacas'] ?? 0) * $cantidad;
}

// Subtotal mensual = PagoEquipo + Seguro + GPS + Placas
$totalSubtotal = $totalPagoEquipo + $totalSeguro + $totalPagoGPS + $totalPagoPlacas;

// IVA
$totalIVA = $totalSubtotal * 0.16;

// Pago mensual total = Subtotal + IVA
$totalPagoMensual = $totalSubtotal + $totalIVA;

// Anticipo
$anticipo = floatval($header['Anticipo'] ?? 0);

// Totales de residuales desde el Header
$totalResidual1Pago = floatval($header['TotalResidual1Pago'] ?? 0);
$totalResidual3Pagos = floatval($header['TotalResidual3Pagos'] ?? 0);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cotización <?php echo htmlspecialchars($folio); ?> - WISER</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: 'Arial', sans-serif;
            line-height: 1.4;
            color: #333;
            background: white;
            padding: 20px;
            max-width: 800px;
            margin: 0 auto;
            font-size: 12px;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 30px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 20px;
        }
        
        .logo-section {
            flex: 1;
        }
        
        .date-section {
            text-align: right;
            font-size: 11px;
        }
        
        .date-label {
            font-weight: bold;
            margin-bottom: 3px;
        }
        
        .folio-section {
            text-align: right;
            margin-top: 10px;
        }
        
        .folio-label {
            font-size: 10px;
            color: #666;
            text-transform: uppercase;
        }
        
        .folio-number {
            font-size: 14px;
            font-weight: bold;
            color: #4A90E2;
            font-family: monospace;
        }
        
        .cotizacion-title {
            font-size: 20px;
            font-weight: bold;
            margin: 20px 0 10px 0;
            color: #333;
        }
        
        .client-name {
            font-size: 14px;
            color: #2e4be0;
            font-weight: bold;
            margin-bottom: 20px;
        }
        
        .equipment-section {
            margin-bottom: 30px;
        }
        
        .equipment-header {
            background: #2e4be0;
            color: white;
            font-weight: bold;
            font-size: 12px;
            padding: 8px 12px;
            margin-bottom: 15px;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
            color-adjust: exact;
        }
        
        .equipment-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 20px;
        }
        
        .left-column {
            border-right: 1px solid #ddd;
            padding-right: 20px;
        }
        
        .calculation-row {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            border-bottom: 1px solid #eee;
        }
        
        .calculation-row:last-child {
            border-bottom: none;
        }
        
        .calculation-row.total {
            border-top: 2px solid #333;
            border-bottom: 2px solid #333;
            font-weight: bold;
            margin-top: 10px;
            padding-top: 10px;
        }
        
        .calculation-row.subtotal {
            border-top: 1px solid #333;
            font-weight: bold;
            margin-top: 5px;
        }
        
        .label {
            font-weight: normal;
        }
        
        .value {
            font-weight: bold;
            text-align: right;
        }
        
        .right-column {
            padding-left: 20px;
        }
        
        .info-box {
            border: 1px solid #333;
            margin-bottom: 10px;
        }
        
        .info-box-header {
            background: #f0f0f0;
            padding: 6px 12px;
            font-weight: bold;
            text-align: center;
            border-bottom: 1px solid #333;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 4px 12px;
            border-bottom: 1px solid #ddd;
        }
        
        .info-row:last-child {
            border-bottom: none;
        }
        
        .payment-details {
            margin-top: 20px;
        }
        
        .payment-row {
            display: flex;
            justify-content: space-between;
            padding: 4px 0;
            font-size: 11px;
        }
        
        .payment-row.highlight {
            font-weight: bold;
            font-size: 12px;
            margin-top: 8px;
            padding-top: 8px;
            border-top: 1px solid #ccc;
        }
        
        .residual-section {
            margin-top: 15px;
            padding-top: 10px;
            border-top: 1px solid #ddd;
        }
        
        .footer {
            margin-top: 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 10px;
            color: #666;
            border-top: 1px solid #ddd;
            padding-top: 15px;
        }
        
        .footer-left {
            font-weight: bold;
        }
        
        .footer-right {
            text-align: right;
        }
        
        .page-number {
            font-weight: bold;
        }
        
        .no-print {
            margin-top: 30px;
            text-align: center;
        }
        
        .btn {
            padding: 8px 16px;
            margin: 0 5px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 11px;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-primary {
            background: #4A90E2;
            color: white;
        }
        
        .btn-secondary {
            background: #666;
            color: white;
        }
        
        @media print {
            .no-print {
                display: none !important;
            }

            body {
                padding: 0;
                background: white;
                max-width: none;
                margin: 0;
            }

            .page-break {
                page-break-before: always;
            }

            /* Forzar colores de fondo en impresión */
            .equipment-header {
                background: #2e4be0 !important;
                color: white !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                color-adjust: exact !important;
            }

            .info-box-header {
                background: #666 !important;
                color: white !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                color-adjust: exact !important;
            }
        }
        
        @media (max-width: 700px) {
            .equipment-details {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .left-column {
                border-right: none;
                padding-right: 0;
            }
            
            .right-column {
                padding-left: 0;
            }
            
            .header {
                flex-direction: column;
                gap: 15px;
            }
            
            .date-section {
                text-align: left;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo-section">
            <img src="../assets/img/logo_wiser_web.webp" width="200" alt="Logo Wiser">
        </div>
        <div class="date-section">
            <div class="date-label">Fecha de cotización:</div>
            <div><?php echo $fechaCotizacionFormateada; ?></div>
            <div class="folio-section">
                <div class="folio-label">Folio:</div>
                <div class="folio-number"><?php echo htmlspecialchars($folio); ?></div>
            </div>
        </div>
    </div>

    <?php foreach ($details as $index => $detail):
        // Agregar salto de página y header para equipos después del primero
        if ($index > 0): ?>
    <div class="page-break">
        <div class="header">
            <div class="logo-section">
                <img src="../assets/img/logo_wiser_web.webp" width="200" alt="Logo Wiser">
            </div>
            <div class="date-section">
                <div class="date-label">Fecha de cotización:</div>
                <div><?php echo $fechaCotizacionFormateada; ?></div>
                <div class="folio-section">
                    <div class="folio-label">Folio:</div>
                    <div class="folio-number"><?php echo htmlspecialchars($folio); ?></div>
                </div>
            </div>
        </div>

        <div class="cotizacion-title">Cotización</div>
        <div class="client-name">CLIENTE: <?php echo htmlspecialchars($header['NombreCliente']); ?></div>
    </div>
    <?php else: ?>
    <div class="cotizacion-title">Cotización</div>
    <div class="client-name">CLIENTE: <?php echo htmlspecialchars($header['NombreCliente']); ?></div>
    <?php endif;
        // Usar valores ya calculados de la BD
        $cantidad = $detail['Cantidad'];

        $pagoEquipoMensual = floatval($detail['PagoEquipo'] ?? 0) * $cantidad;
        $pagoSeguroMensual = floatval($detail['Seguro'] ?? 0) * $cantidad;
        $pagoGPSMensual = floatval($detail['PagoGPS'] ?? 0) * $cantidad;
        $pagoPlacasMensual = floatval($detail['PagoPlacas'] ?? 0) * $cantidad;

        // Subtotal completo = Equipo + Seguro + GPS + Placas
        $subtotalCompleto = $pagoEquipoMensual + $pagoSeguroMensual + $pagoGPSMensual + $pagoPlacasMensual;
        $ivaCompleto = $subtotalCompleto * 0.16;
        $mensualidadTotal = $subtotalCompleto + $ivaCompleto;

        // Verificar si tiene GPS/Placas
        $tieneGPS = floatval($detail['PagoGPS'] ?? 0) > 0;
        $tienePlacas = floatval($detail['PagoPlacas'] ?? 0) > 0;
    ?>
    <div class="equipment-section">
        <div class="equipment-header">
            (<?php echo $detail['Cantidad']; ?>) <?php echo strtoupper(htmlspecialchars($detail['Equipo'])); ?> MARCA <?php echo strtoupper(htmlspecialchars($detail['Marca'])); ?>
        </div>

        <div class="equipment-details">
            <div class="left-column">
                <div class="calculation-row">
                    <span class="label">Equipo:</span>
                    <span class="value">$<?php echo number_format($pagoEquipoMensual, 2); ?></span>
                </div>
                <?php if ($tieneGPS): ?>
                <div class="calculation-row">
                    <span class="label">GPS:</span>
                    <span class="value">$<?php echo number_format($pagoGPSMensual, 2); ?></span>
                </div>
                <?php endif; ?>
                <?php if ($tienePlacas): ?>
                <div class="calculation-row">
                    <span class="label">Placas:</span>
                    <span class="value">$<?php echo number_format($pagoPlacasMensual, 2); ?></span>
                </div>
                <?php endif; ?>
                <div class="calculation-row">
                    <span class="label">Seguro:</span>
                    <span class="value">$<?php echo number_format($pagoSeguroMensual, 2); ?></span>
                </div>
                <div class="calculation-row subtotal">
                    <span class="label">SUBTOTAL:</span>
                    <span class="value">$<?php echo number_format($subtotalCompleto, 2); ?></span>
                </div>
                <div class="calculation-row">
                    <span class="label">IVA:</span>
                    <span class="value">$<?php echo number_format($ivaCompleto, 2); ?></span>
                </div>
                <div class="calculation-row total">
                    <span class="label">MENSUALIDAD:</span>
                    <span class="value">$<?php echo number_format($mensualidadTotal, 2); ?></span>
                </div>

                <div class="payment-details">
                    <div class="payment-row">
                        <span>Deposito de garantía:</span>
                        <span>$<?php echo number_format($mensualidadTotal, 2); ?></span>
                    </div>
                    <div class="payment-row highlight">
                        <span>1er PAGO:</span>
                        <span>$<?php echo number_format($mensualidadTotal * 2, 2); ?></span>
                    </div>
                    <div class="payment-row">
                        <span style="font-size: 10px; font-style: italic;">Mensualidad + Dep. Garantía</span>
                    </div>
                    
                    <div class="residual-section">
                        <?php
                        $costoVenta = ($detail['CostoVenta'] ?? $detail['Costo']) * $detail['Cantidad'];
                        $residualAmount = $detail['Residual1Pago'] > 0 ? $detail['ValorResidual'] * $detail['Cantidad'] : $costoVenta * 0.20;
                        $residualIVA = $detail['IvaResidual'] > 0 ? $detail['IvaResidual'] * $detail['Cantidad'] : $residualAmount * 0.16;
                        $pago1Residual = $detail['Residual1Pago'] > 0 ? $detail['Residual1Pago'] * $detail['Cantidad'] : $residualAmount + $residualIVA;
                        $pago3Residual = $detail['Residual3Pagos'] > 0 ? $detail['Residual3Pagos'] * $detail['Cantidad'] : $pago1Residual * 1.1 / 3;
                        ?>
                        <div class="payment-row">
                            <span>RESIDUAL:</span>
                            <span>$<?php echo number_format($residualAmount, 2); ?></span>
                        </div>
                        <div class="payment-row">
                            <span>IVA:</span>
                            <span>$<?php echo number_format($residualIVA, 2); ?></span>
                        </div>
                        <div class="payment-row">
                            <span>1 pago de:</span>
                            <span>$<?php echo number_format($pago1Residual, 2); ?></span>
                        </div>
                        <div class="payment-row">
                            <span>3 pagos de:</span>
                            <span>$<?php echo number_format($pago3Residual, 2); ?></span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="right-column">
                <div class="info-box">
                    <div class="info-box-header">RESUMEN</div>
                    <div class="info-row">
                        <span>Marca</span>
                        <span><?php echo strtoupper(htmlspecialchars($detail['Marca'])); ?></span>
                    </div>
                    <div class="info-row">
                        <span>Plazo</span>
                        <span><?php echo $detail['Plazo']; ?> meses</span>
                    </div>
                    <div class="info-row">
                        <span>Mensualidad</span>
                        <span>$<?php echo number_format($mensualidadTotal, 2); ?></span>
                    </div>
                    <div class="info-row">
                        <span>Moneda</span>
                        <span><?php echo htmlspecialchars($header['Moneda'] ?? 'MXN'); ?>*</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="footer">
        <div class="footer-left">
            <!-- INCLUYE GARANTÍA Y GPS -->
        </div>
        <div class="footer-right">
            <div class="page-number">Página: <?php echo ($index + 1); ?>/<?php echo (count($details) + 1); ?></div>
            <div>* Todos los valores están presentados en moneda <?php echo htmlspecialchars($header['Moneda'] ?? 'MXN'); ?></div>
            <!-- <div style="margin-top: 5px; font-size: 9px;">
                Enlace válido hasta: <?php echo date('d/m/Y H:i', strtotime($header['FechaExpiracion'] ?? '+30 days')); ?>
            </div> -->
        </div>
    </div>
    <?php endforeach; ?>

    <!-- Última página con totales -->
    <div class="page-break">
        <div class="header">
            <div class="logo-section">
                <img src="../assets/img/logo_wiser_web.webp" width="200" alt="Logo Wiser">
            </div>
            <div class="date-section">
                <div class="date-label">Fecha de cotización:</div>
                <div><?php echo $fechaCotizacionFormateada; ?></div>
                <div class="folio-section">
                    <div class="folio-label">Folio:</div>
                    <div class="folio-number"><?php echo htmlspecialchars($folio); ?></div>
                </div>
            </div>
        </div>

        <div class="cotizacion-title">Cotización</div>
        <div class="client-name">CLIENTE: <?php echo htmlspecialchars($header['NombreCliente']); ?></div>

        <div class="equipment-section">
            <div class="equipment-header">
                TOTAL
            </div>
            
            <div class="equipment-details">
                <div class="left-column">
                    <div class="calculation-row">
                        <span class="label">Equipo:</span>
                        <span class="value">$<?php echo number_format($totalPagoEquipo, 2); ?></span>
                    </div>
                    <?php if ($totalPagoGPS > 0): ?>
                    <div class="calculation-row">
                        <span class="label">GPS:</span>
                        <span class="value">$<?php echo number_format($totalPagoGPS, 2); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($totalPagoPlacas > 0): ?>
                    <div class="calculation-row">
                        <span class="label">Placas:</span>
                        <span class="value">$<?php echo number_format($totalPagoPlacas, 2); ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="calculation-row">
                        <span class="label">Seguro:</span>
                        <span class="value">$<?php echo number_format($totalSeguro, 2); ?></span>
                    </div>
                    <div class="calculation-row subtotal">
                        <span class="label">SUBTOTAL:</span>
                        <span class="value">$<?php echo number_format($totalSubtotal, 2); ?></span>
                    </div>
                    <div class="calculation-row">
                        <span class="label">IVA:</span>
                        <span class="value">$<?php echo number_format($totalIVA, 2); ?></span>
                    </div>
                    <div class="calculation-row total">
                        <span class="label">MENSUALIDAD:</span>
                        <span class="value">$<?php echo number_format($totalPagoMensual, 2); ?></span>
                    </div>

                    <div class="payment-details">
                        <div class="payment-row">
                            <span>Deposito de garantía:</span>
                            <span>$<?php echo number_format($totalPagoMensual, 2); ?></span>
                        </div>
                        <div class="payment-row highlight">
                            <span>1er PAGO:</span>
                            <span>$<?php echo number_format($totalPagoMensual * 2, 2); ?></span>
                        </div>
                        <div class="payment-row">
                            <span style="font-size: 10px; font-style: italic;">Mensualidad + Dep. Garantía</span>
                        </div>
                        
                        <div class="residual-section">
                            <?php
                            // Usar valores de residuales ya calculados en la BD
                            $totalResidualAmount = array_sum(array_map(function($d) {
                                return floatval($d['ValorResidual'] ?? 0) * $d['Cantidad'];
                            }, $details));
                            $totalResidualIVA = array_sum(array_map(function($d) {
                                return floatval($d['IvaResidual'] ?? 0) * $d['Cantidad'];
                            }, $details));
                            ?>
                            <div class="payment-row">
                                <span>RESIDUAL:</span>
                                <span>$<?php echo number_format($totalResidualAmount, 2); ?></span>
                            </div>
                            <div class="payment-row">
                                <span>IVA:</span>
                                <span>$<?php echo number_format($totalResidualIVA, 2); ?></span>
                            </div>
                            <div class="payment-row">
                                <span>1 pago de:</span>
                                <span>$<?php echo number_format($totalResidual1Pago, 2); ?></span>
                            </div>
                            <div class="payment-row">
                                <span>3 pagos de:</span>
                                <span>$<?php echo number_format($totalResidual3Pagos, 2); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="right-column">
                    <div class="info-box">
                        <div class="info-box-header">TOTAL</div>
                        <div class="info-row">
                            <span>Plazo</span>
                            <span><?php echo $details[0]['Plazo'] ?? '18'; ?> meses</span>
                        </div>
                        <div class="info-row">
                            <span>Mensualidad</span>
                            <span>$<?php echo number_format($totalPagoMensual, 2); ?></span>
                        </div>
                        <div class="info-row">
                            <span>Moneda</span>
                            <span><?php echo htmlspecialchars($header['Moneda'] ?? 'MXN'); ?>*</span>
                        </div>
                        <?php if ($anticipo > 0): ?>
                        <div class="info-row">
                            <span>Anticipo</span>
                            <span style="color: #28a745; font-weight: bold;">$<?php echo number_format($anticipo, 2); ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="info-row">
                            <span>Total Contrato</span>
                            <?php
                            // Total Contrato = (Subtotal SIN IVA × Plazo) - Anticipo
                            $plazo = $details[0]['Plazo'] ?? 18;
                            $totalContratoSinIVA = $totalSubtotal * $plazo;
                            $totalContratoFinal = $totalContratoSinIVA - $anticipo;
                            ?>
                            <span>$<?php echo number_format($totalContratoFinal, 2); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="footer">
            <div class="footer-left">
                <!-- INCLUYE GARANTÍA Y GPS -->
            </div>
            <div class="footer-right">
                <div class="page-number">Página: <?php echo (count($details) + 1); ?>/<?php echo (count($details) + 1); ?></div>
                <div>* Todos los valores están presentados en moneda <?php echo htmlspecialchars($header['Moneda'] ?? 'MXN'); ?></div>
                <!-- <div style="margin-top: 5px; font-size: 9px;">
                    Enlace válido hasta: <?php echo date('d/m/Y H:i', strtotime($header['FechaExpiracion'] ?? '+30 days')); ?>
                </div> -->
            </div>
        </div>
    </div>

    <div class="no-print">
        <button onclick="window.print()" class="btn btn-primary">Imprimir</button>
        <button onclick="window.close()" class="btn btn-secondary">Cerrar</button>
    </div>

    <script>
        // Auto-print if requested
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('autoprint') === '1') {
            window.onload = function() {
                window.print();
            };
        }
    </script>
</body>
</html>