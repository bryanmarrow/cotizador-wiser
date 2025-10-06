<?php
$folio = htmlspecialchars($_GET['folio'] ?? '');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cotización <?= $folio ?> - WISER</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#1f2937',
                        secondary: '#6b7280',
                        accent: '#3b82f6',
                        success: '#10b981',
                        warning: '#f59e0b',
                        error: '#ef4444'
                    }
                }
            }
        }
    </script>
    <style>
        .content-animate { animation: fadeIn 0.5s ease-in-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        main { -webkit-overflow-scrolling: touch; scroll-behavior: smooth; }
        main::-webkit-scrollbar { width: 4px; }
        main::-webkit-scrollbar-track { background: transparent; }
        main::-webkit-scrollbar-thumb { background: rgba(156, 163, 175, 0.5); border-radius: 2px; }
        main::-webkit-scrollbar-thumb:hover { background: rgba(156, 163, 175, 0.8); }
        @media print {
            .no-print { display: none !important; }
            body { overflow: visible !important; height: auto !important; }
            main { overflow: visible !important; height: auto !important; }
        }
    </style>
</head>
<body class="bg-gray-50 h-screen overflow-hidden flex flex-col">
    <!-- Header -->
    <header class="bg-white shadow-sm border-b no-print">
        <div class="max-w-md mx-auto px-4 py-3">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-2">
                    <img src="assets/img/logo_wiser_web.webp" alt="WISER" class="h-8 w-auto">
                    <div class="hidden sm:block">
                        <span class="text-sm font-medium text-gray-900">Cotización</span>
                        <div class="text-xs text-gray-500" id="public-folio"><?= $folio ?></div>
                    </div>
                </div>
                <button onclick="window.open('api/imprimir_publico.php?folio=<?= $folio ?>&autoprint=1', '_blank');" class="p-2 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors" title="Imprimir">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                </button>
            </div>
        </div>
    </header>

    <!-- Loading State -->
    <div id="loading-state" class="flex-1 flex items-center justify-center">
        <div class="text-center">
            <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-accent mb-4"></div>
            <p class="text-gray-600">Cargando detalle de cotización...</p>
        </div>
    </div>

    <!-- Error State -->
    <div id="error-state" class="hidden flex-1 flex items-center justify-center">
        <div class="text-center max-w-sm mx-auto px-4">
            <svg class="w-16 h-16 text-red-500 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path></svg>
            <h3 class="text-lg font-medium text-gray-900 mb-2">Error al Cargar</h3>
            <p id="error-message" class="text-gray-600 mb-4 text-sm"></p>
            <a href="https://wiserarrendadora.com.mx" class="bg-accent text-white px-4 py-2 rounded-lg hover:bg-blue-600 transition-colors text-sm">
                Volver al Sitio Principal
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <main id="main-content" class="hidden flex-1 overflow-y-auto">
        <div class="max-w-md mx-auto w-full">
            <!-- Header de Cotización -->
            <div class="bg-white border-b p-4 sticky top-0 z-10">
                <div class="flex items-center justify-between mb-3">
                    <h1 class="text-xl font-bold text-gray-900">
                        Cotización <span id="cotizacion-numero">#000</span>
                    </h1>
                    <div id="estado-badge" class="text-xs px-2 py-1 rounded-full"></div>
                </div>
                
                <div class="space-y-2 text-sm">                    
                    <div class="flex justify-between">
                        <span class="text-gray-600">Vendedor:</span>
                        <span id="vendedor-info" class="font-medium text-right"></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Fecha:</span>
                        <span id="fecha-info" class="font-medium text-right"></span>
                    </div>
                </div>
            </div>

            <!-- Totales -->
            <div class="bg-gray-50 p-4">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Resumen</h3>
                
                <div class="bg-white rounded-lg p-4 mb-4">
                    <h4 class="font-medium text-gray-900 mb-3">Información del Contrato</h4>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Moneda:</span>
                            <span id="moneda-contrato" class="font-medium"></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Plazo:</span>
                            <span id="plazo-contrato" class="font-medium"></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">% Residual:</span>
                            <span id="porcentaje-residual" class="font-medium"></span>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg p-4">
                    <h4 class="font-medium text-gray-900 mb-3">Totales</h4>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Subtotal:</span>
                            <span id="subtotal-valor" class="font-medium"></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">IVA:</span>
                            <span id="iva-valor" class="font-medium"></span>
                        </div>
                        <div class="flex justify-between border-t pt-2 font-semibold">
                            <span>Total Equipo:</span>
                            <span id="total-equipo" class="text-lg"></span>
                        </div>
                        <div class="space-y-2 border-t pt-2 mt-2">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Pago Mensual:</span>
                                <span id="pago-mensual" class="font-medium text-lg text-accent"></span>
                            </div>
                            <div class="flex justify-between border-t pt-2 font-bold">
                                <span>Total Contrato:</span>
                                <span id="total-contrato" class="text-xl text-success"></span>
                            </div>
                            
                            <div class="bg-blue-50 p-3 rounded-lg border border-blue-200 mt-3">
                                <h4 class="font-medium text-blue-900 mb-2 text-sm">Opciones de Residual</h4>
                                <div class="space-y-2">
                                    <div class="flex justify-between">
                                        <span class="text-blue-700 text-sm">1 Pago total:</span>
                                        <span id="total-residual-1pago" class="font-medium text-blue-900"></span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-blue-700 text-sm">3 Pagos c/u:</span>
                                        <span id="total-residual-3pagos" class="font-medium text-blue-900"></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Equipos -->
            <div class="p-4">
                <div id="lista-equipos" class="space-y-3">
                    <!-- Se llena dinámicamente -->
                </div>
            </div>
        </div>
    </main>

    <script>
        const folio = '<?= $folio ?>';
        document.addEventListener('DOMContentLoaded', () => {
            new DetallePublicoCotizacion(folio);
        });
    </script>
    <script src="assets/js/cotizacion-publica.js"></script>
</body>
</html>
