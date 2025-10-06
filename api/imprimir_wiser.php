<?php
header('Content-Type: text/html; charset=utf-8');

require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Requiere estar logueado para imprimir
requireLogin();

try {
    $quoteId = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    if (!$quoteId) {
        throw new Exception('ID de cotización requerido');
    }
    
    $conn = obtenerConexionBaseDatos();
    
    // Get quote header
    $headerQuery = "SELECT * FROM Cotizacion_Header WHERE Id = ?";
    $headerStmt = $conn->prepare($headerQuery);
    $headerStmt->execute([$quoteId]);
    $header = $headerStmt->fetch();
    
    if (!$header) {
        throw new Exception('Cotización no encontrada');
    }
    
    // Get quote details
    $detailsQuery = "SELECT * FROM Cotizacion_Detail WHERE IdHeader = ? ORDER BY Id";
    $detailsStmt = $conn->prepare($detailsQuery);
    $detailsStmt->execute([$quoteId]);
    $details = $detailsStmt->fetchAll();
    
    // Update quote status to printed
    $updateStatusQuery = "UPDATE Cotizacion_Header SET Estado = ? WHERE Id = ?";
    $updateStmt = $conn->prepare($updateStatusQuery);
    $updateStmt->execute([STATE_PRINTED, $quoteId]);
    
} catch (Exception $e) {
    echo "<div class='error'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    exit;
}

// Calculate totals
$totalEquiposCosto = array_sum(array_map(function($d) { return $d['Costo'] * $d['Cantidad']; }, $details));
$totalPagoMensual = array_sum(array_map(function($d) { return ($d['PagoEquipo'] + $d['Seguro']) * $d['Cantidad']; }, $details));
$totalSeguro = array_sum(array_map(function($d) { return $d['Seguro'] * $d['Cantidad']; }, $details));
$subtotal = $totalEquiposCosto + $totalSeguro;
$iva = $subtotal * 0.16;
$mensualidad = $totalPagoMensual;

// Calculate deposits and residuals
$depositoGarantia = $mensualidad;
$pagoPlacas = 4000; // Fixed amount as shown in PDF
$primerPago = $mensualidad + $depositoGarantia + $pagoPlacas;

// Residual calculations (using first equipment as reference)
$firstDetail = $details[0] ?? null;
$residualAmount = 0;
$residualIVA = 0;
$pago1Residual = 0;
$pago3Residual = 0;

if ($firstDetail) {
    $costoVenta = $firstDetail['CostoVenta'] * $firstDetail['Cantidad'];
    $residualPercentage = 20; // Default 20%
    $residualAmount = $costoVenta * ($residualPercentage / 100);
    $residualIVA = $residualAmount * 0.16;
    $pago1Residual = $residualAmount + $residualIVA;
    $pago3Residual = ($residualAmount + $residualIVA) * 1.1 / 3;
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cotización WISER #<?php echo $quoteId; ?></title>
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
        
        .wiser-logo {
            font-size: 48px;
            font-weight: bold;
            color: #4A90E2;
            margin-bottom: 5px;
            letter-spacing: 2px;
        }
        
        .logo-subtitle {
            font-size: 10px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-left: 20px;
        }
        
        .date-section {
            text-align: right;
            font-size: 11px;
        }
        
        .date-label {
            font-weight: bold;
            margin-bottom: 3px;
        }
        
        .cotizacion-title {
            font-size: 20px;
            font-weight: bold;
            margin: 20px 0 10px 0;
            color: #333;
        }
        
        .client-name {
            font-size: 14px;
            color: #FF6B35;
            font-weight: bold;
            margin-bottom: 20px;
        }
        
        .equipment-section {
            margin-bottom: 30px;
        }
        
        .equipment-header {
            background: #FF6B35;
            color: white;
            font-weight: bold;
            font-size: 12px;
            padding: 8px 12px;
            margin-bottom: 15px;
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
        
        .btn-new {
            background: #FF6B35;
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
            <div class="date-label">Fecha de impresión:</div>
            <div><?php echo strftime('%A, %d de %B de %Y', time()); ?></div>
        </div>
    </div>

    <div class="cotizacion-title">Cotización</div>
    <div class="client-name">CLIENTE: <?php echo htmlspecialchars($header['NombreCliente']); ?></div>

    <?php foreach ($details as $index => $detail): ?>
    <div class="equipment-section">
        <div class="equipment-header">
            (<?php echo $detail['Cantidad']; ?>) <?php echo strtoupper(htmlspecialchars($detail['Equipo'])); ?> MARCA <?php echo strtoupper(htmlspecialchars($detail['Marca'])); ?>
        </div>
        
        <div class="equipment-details">
            <div class="left-column">
                <div class="calculation-row">
                    <span class="label">Equipo:</span>
                    <span class="value">$<?php echo number_format($detail['Costo'] * $detail['Cantidad'], 2); ?></span>
                </div>
                <div class="calculation-row">
                    <span class="label">Seguro:</span>
                    <span class="value">$<?php echo number_format($detail['Seguro'] * $detail['Cantidad'], 2); ?></span>
                </div>
                <div class="calculation-row subtotal">
                    <span class="label">SUBTOTAL:</span>
                    <span class="value">$<?php echo number_format(($detail['Costo'] + $detail['Seguro']) * $detail['Cantidad'], 2); ?></span>
                </div>
                <div class="calculation-row">
                    <span class="label">IVA:</span>
                    <span class="value">$<?php echo number_format(($detail['Costo'] + $detail['Seguro']) * $detail['Cantidad'] * 0.16, 2); ?></span>
                </div>
                <div class="calculation-row total">
                    <span class="label">MENSUALIDAD:</span>
                    <span class="value">$<?php echo number_format(($detail['PagoEquipo'] + $detail['Seguro']) * $detail['Cantidad'], 2); ?></span>
                </div>
                
                <div class="payment-details">
                    <div class="payment-row">
                        <span>Deposito de garantía:</span>
                        <span>$<?php echo number_format(($detail['PagoEquipo'] + $detail['Seguro']) * $detail['Cantidad'], 2); ?> + pago de placas: $<?php echo number_format($pagoPlacas, 2); ?></span>
                    </div>
                    <div class="payment-row highlight">
                        <span>1er PAGO:</span>
                        <span>$<?php echo number_format((($detail['PagoEquipo'] + $detail['Seguro']) * $detail['Cantidad'] * 2) + $pagoPlacas, 2); ?></span>
                    </div>
                    <div class="payment-row">
                        <span style="font-size: 10px; font-style: italic;">Mensualidad + Dep. Garantía</span>
                    </div>
                    
                    <div class="residual-section">
                        <?php
                        $costoVenta = $detail['CostoVenta'] * $detail['Cantidad'];
                        $residualAmount = $costoVenta * 0.20; // 20% residual
                        $residualIVA = $residualAmount * 0.16;
                        $pago1Residual = $residualAmount + $residualIVA;
                        $pago3Residual = $pago1Residual * 1.1 / 3;
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
                    <div class="info-box-header">subtotal</div>
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
                        <span>$<?php echo number_format(($detail['PagoEquipo'] + $detail['Seguro']) * $detail['Cantidad'], 2); ?></span>
                    </div>
                    <div class="info-row">
                        <span>Moneda</span>
                        <span><?php echo htmlspecialchars($header['Moneda'] ?? 'MXN'); ?>*</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <div class="footer">
        <div class="footer-left">
            INCLUYE GARANTÍA Y GPS
        </div>
        <div class="footer-right">
            <div class="page-number">Página: 1/2</div>
            <div>* Todos los valores están presentados en moneda <?php echo htmlspecialchars($header['Moneda'] ?? 'MXN'); ?></div>
        </div>
    </div>

    <!-- Segunda página con totales -->
    <div class="page-break">
        <div class="header">
            <div class="logo-section">
                <img src="../assets/img/logo_wiser_web.webp" width="200" alt="Logo Wiser">
            </div>
            <div class="date-section">
                <div class="date-label">Fecha de impresión:</div>
                <div><?php echo strftime('%A, %d de %B de %Y', time()); ?></div>
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
                        <span class="value">$<?php echo number_format($totalEquiposCosto, 2); ?></span>
                    </div>
                    <div class="calculation-row">
                        <span class="label">Seguro:</span>
                        <span class="value">$<?php echo number_format($totalSeguro, 2); ?></span>
                    </div>
                    <div class="calculation-row subtotal">
                        <span class="label">SUBTOTAL:</span>
                        <span class="value">$<?php echo number_format($totalEquiposCosto + $totalSeguro, 2); ?></span>
                    </div>
                    <div class="calculation-row">
                        <span class="label">IVA:</span>
                        <span class="value">$<?php echo number_format(($totalEquiposCosto + $totalSeguro) * 0.16, 2); ?></span>
                    </div>
                    <div class="calculation-row total">
                        <span class="label">MENSUALIDAD:</span>
                        <span class="value">$<?php echo number_format($totalPagoMensual, 2); ?></span>
                    </div>
                    
                    <div class="payment-details">
                        <div class="payment-row">
                            <span>Deposito de garantía:</span>
                            <span>$<?php echo number_format($totalPagoMensual, 2); ?> + pago de placas: $<?php echo number_format($pagoPlacas, 2); ?></span>
                        </div>
                        <div class="payment-row highlight">
                            <span>1er PAGO:</span>
                            <span>$<?php echo number_format($totalPagoMensual * 2 + $pagoPlacas, 2); ?></span>
                        </div>
                        <div class="payment-row">
                            <span style="font-size: 10px; font-style: italic;">Mensualidad + Dep. Garantía</span>
                        </div>
                        
                        <div class="residual-section">
                            <?php
                            $totalCostoVenta = array_sum(array_map(function($d) { return $d['CostoVenta'] * $d['Cantidad']; }, $details));
                            $totalResidualAmount = $totalCostoVenta * 0.20;
                            $totalResidualIVA = $totalResidualAmount * 0.16;
                            $totalPago1Residual = $totalResidualAmount + $totalResidualIVA;
                            $totalPago3Residual = $totalPago1Residual * 1.1 / 3;
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
                                <span>$<?php echo number_format($totalPago1Residual, 2); ?></span>
                            </div>
                            <div class="payment-row">
                                <span>3 pagos de:</span>
                                <span>$<?php echo number_format($totalPago3Residual, 2); ?></span>
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
                    </div>
                </div>
            </div>
        </div>

        <div class="footer">
            <div class="footer-left">
                INCLUYE GARANTÍA Y GPS
            </div>
            <div class="footer-right">
                <div class="page-number">Página: 2/2</div>
                <div>* Todos los valores están presentados en moneda <?php echo htmlspecialchars($header['Moneda'] ?? 'MXN'); ?></div>
            </div>
        </div>
    </div>

    <div class="no-print">
        <button onclick="window.print()" class="btn btn-primary">Imprimir</button>
        <a href="../index.php" class="btn btn-secondary">Nueva Cotización</a>
        <!-- <a href="imprimir.php?id=<?php echo $quoteId; ?>" class="btn btn-new">Versión Original</a> -->
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