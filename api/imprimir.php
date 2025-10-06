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
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cotización #<?php echo $quoteId; ?> - <?php echo APP_NAME; ?></title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            background: white;
            padding: 20px;
            max-width: 800px;
            margin: 0 auto;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #3b82f6;
            padding-bottom: 20px;
        }
        
        .header h1 {
            color: #1f2937;
            font-size: 2rem;
            margin-bottom: 10px;
        }
        
        .header .quote-info {
            display: flex;
            justify-content: space-between;
            margin-top: 15px;
            font-size: 0.9rem;
            color: #6b7280;
        }
        
        .client-info {
            background: #f8fafc;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        
        .client-info h2 {
            color: #1f2937;
            margin-bottom: 15px;
            font-size: 1.2rem;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .info-item {
            display: flex;
            justify-content: space-between;
        }
        
        .info-label {
            font-weight: 600;
            color: #4b5563;
        }
        
        .equipment-section {
            margin-bottom: 30px;
        }
        
        .equipment-section h2 {
            color: #1f2937;
            margin-bottom: 20px;
            font-size: 1.2rem;
        }
        
        .equipment-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #e5e7eb;
            background: white;
        }
        
        .equipment-table th,
        .equipment-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .equipment-table th {
            background: #f3f4f6;
            font-weight: 600;
            color: #374151;
            font-size: 0.85rem;
        }
        
        .equipment-table td {
            font-size: 0.85rem;
        }
        
        .equipment-table .number {
            text-align: right;
            font-family: monospace;
        }
        
        .totals-section {
            background: #eff6ff;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #3b82f6;
        }
        
        .totals-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .total-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
        }
        
        .total-final {
            border-top: 2px solid #3b82f6;
            padding-top: 15px;
            margin-top: 15px;
            display: flex;
            justify-content: space-between;
            font-size: 1.1rem;
            font-weight: bold;
        }
        
        .footer {
            margin-top: 40px;
            text-align: center;
            font-size: 0.8rem;
            color: #6b7280;
            border-top: 1px solid #e5e7eb;
            padding-top: 20px;
        }
        
        .no-print {
            margin-top: 30px;
            text-align: center;
        }
        
        .btn {
            padding: 10px 20px;
            margin: 0 10px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9rem;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-primary {
            background: #3b82f6;
            color: white;
        }
        
        .btn-secondary {
            background: #6b7280;
            color: white;
        }
        
        @media print {
            .no-print {
                display: none !important;
            }
            
            body {
                padding: 0;
                background: white;
            }
            
            .header h1 {
                font-size: 1.5rem;
            }
        }
        
        @media (max-width: 600px) {
            .info-grid,
            .totals-grid {
                grid-template-columns: 1fr;
            }
            
            .header .quote-info {
                flex-direction: column;
                gap: 5px;
            }
            
            .equipment-table {
                font-size: 0.75rem;
            }
            
            .equipment-table th,
            .equipment-table td {
                padding: 8px 4px;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1><?php echo APP_NAME; ?></h1>
        <div class="quote-info">
            <span>Cotización #<?php echo str_pad($quoteId, 6, '0', STR_PAD_LEFT); ?></span>
            <span>Fecha: <?php echo date('d/m/Y H:i', strtotime($header['FechaCreacion'])); ?></span>
            <span>Estado: <?php echo ucfirst($header['Estado']); ?></span>
        </div>
    </div>

    <div class="client-info">
        <h2>Información del Cliente</h2>
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">Cliente:</span>
                <span><?php echo htmlspecialchars($header['NombreCliente']); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Tipo:</span>
                <span><?php echo htmlspecialchars($header['TipoCliente']); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Tasa:</span>
                <span><?php echo number_format($header['Tasa'], 2); ?>%</span>
            </div>
            <div class="info-item">
                <span class="info-label">Moneda:</span>
                <span><?php echo htmlspecialchars($header['Moneda'] ?? 'MXN'); ?></span>
            </div>
        </div>
    </div>

    <div class="equipment-section">
        <h2>Equipos Cotizados (<?php echo count($details); ?>)</h2>
        <table class="equipment-table">
            <thead>
                <tr>
                    <th>Cantidad</th>
                    <th>Equipo</th>
                    <th>Marca</th>
                    <th>Modelo</th>
                    <?php if (puedeVerInformacionSensible()): ?>
                    <th>Costo Unit.</th>
                    <?php endif; ?>
                    <th>Plazo</th>
                    <th>Pago Mensual</th>
                    <?php if (puedeVerInformacionSensible()): ?>
                    <th>Total</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($details as $detail): ?>
                <tr>
                    <td class="number"><?php echo $detail['Cantidad']; ?></td>
                    <td><?php echo htmlspecialchars($detail['Equipo']); ?></td>
                    <td><?php echo htmlspecialchars($detail['Marca']); ?></td>
                    <td><?php echo htmlspecialchars($detail['Modelo'] ?: '-'); ?></td>
                    <?php if (puedeVerInformacionSensible()): ?>
                    <td class="number">$<?php echo number_format($detail['Costo'], 2); ?></td>
                    <?php endif; ?>
                    <td class="number"><?php echo $detail['Plazo']; ?> meses</td>
                    <td class="number">$<?php echo number_format(($detail['PagoEquipo'] + $detail['Seguro']) * $detail['Cantidad'], 2); ?></td>
                    <?php if (puedeVerInformacionSensible()): ?>
                    <td class="number">$<?php echo number_format($detail['CostoVenta'] * $detail['Cantidad'], 2); ?></td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="totals-section">
        <h2>Resumen</h2>
        <div class="totals-grid">
            <div class="total-item">
                <span>Número de equipos:</span>
                <span><?php echo count($details); ?></span>
            </div>
            <div class="total-item">
                <span>Costo total equipos:</span>
                <span>$<?php echo number_format(array_sum(array_map(function($d) { return $d['Costo'] * $d['Cantidad']; }, $details)), 2); ?></span>
            </div>
            <div class="total-item">
                <span>Total  del contrato:</span>
                <span>$<?php echo number_format($header['TotalContrato'], 2); ?></span>
            </div>
            <?php if (puedeVerInformacionSensible()): ?>
            <div class="total-item">
                <span>Utilidad estimada:</span>
                <span class="text-success"><?php echo number_format($header['TotalUtilidad'] * 100, 2); ?>%</span>
            </div>
            <?php endif; ?>
        </div>
        <div class="total-final">
            <span>TOTAL DE CONTRATO:</span>
            <span>$<?php echo number_format($header['TotalContrato'], 2); ?></span>
        </div>
    </div>

    <div class="footer">
        <p>Cotización generada el <?php echo date('d/m/Y H:i:s'); ?></p>
        <!-- <p>Esta cotización es válida por 30 días a partir de la fecha de emisión.</p> -->
    </div>

    <div class="no-print">
        <button onclick="window.print()" class="btn btn-primary">Imprimir</button>
        <a href="../index.php" class="btn btn-secondary">Nueva Cotización</a>
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