<?php
session_start();
require_once 'includes/functions.php';
require_once 'config/constants.php';

// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Obtener ID de cotización
$cotizacionId = intval($_GET['id'] ?? 0);

if ($cotizacionId <= 0) {
    header('Location: cotizaciones.php');
    exit;
}

// Obtener información del usuario
$user = [
    'id' => $_SESSION['user_id'],
    'username' => $_SESSION['username'],
    'fullName' => $_SESSION['full_name'],
    'email' => $_SESSION['email'],
    'role' => $_SESSION['role']
];

$pageTitle = 'Detalle de Cotización';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - WISER Cotizador</title>
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
        .loading { opacity: 0.5; pointer-events: none; }
        
        /* Mobile scroll improvements */
        main {
            -webkit-overflow-scrolling: touch;
            scroll-behavior: smooth;
        }

        /* Custom scrollbar for webkit browsers */
        main::-webkit-scrollbar {
            width: 4px;
        }

        main::-webkit-scrollbar-track {
            background: transparent;
        }

        main::-webkit-scrollbar-thumb {
            background: rgba(156, 163, 175, 0.5);
            border-radius: 2px;
        }

        main::-webkit-scrollbar-thumb:hover {
            background: rgba(156, 163, 175, 0.8);
        }

        /* Desktop enhancements */
        @media (min-width: 1024px) {
            /* Better spacing for desktop */
            .desktop-hover:hover {
                background-color: rgba(59, 130, 246, 0.05);
                transform: translateY(-1px);
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
                transition: all 0.2s ease;
            }
            
            /* Sticky sidebar */
            .sidebar-sticky {
                position: sticky;
                top: 1rem;
                max-height: calc(100vh - 2rem);
                overflow-y: auto;
            }
            
            /* Equipment cards grid for larger screens */
            .equipment-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
                gap: 1rem;
            }
        }
        
        @media (min-width: 768px) {
            /* Tablet improvements */
            .tablet-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media print {
            .no-print { display: none !important; }
            .print-full-width { width: 100% !important; }
            body { overflow: visible !important; height: auto !important; }
            main { overflow: visible !important; height: auto !important; }
            .lg\:grid { display: block !important; }
            .lg\:col-span-1, .lg\:col-span-2 { all: unset !important; }
        }
    </style>
</head>
<body class="bg-gray-50 h-screen overflow-hidden flex flex-col">
    <!-- Header -->
    <header class="bg-white shadow-sm border-b no-print">
        <div class="max-w-md sm:max-w-lg md:max-w-2xl lg:max-w-4xl xl:max-w-6xl mx-auto px-4 py-3">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <button id="boton-atras" class="p-2 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                        </svg>
                    </button>
                    <div class="flex items-center space-x-2">
                        <img src="assets/img/logo_wiser_web.webp" alt="WISER" class="h-8 w-auto">
                        <div class="hidden sm:block">
                            <span class="text-sm font-medium text-gray-900" id="titulo-detalle">Detalle</span>
                            <div class="text-xs text-gray-500" id="subtitulo-detalle">Cotización #<span id="numero-cotizacion-header">000</span></div>
                        </div>
                    </div>
                </div>
                <div class="flex items-center space-x-2">
                    <button id="menu-hamburguesa" class="p-2 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors" title="Menú">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </header>

    <!-- Menu Lateral -->
    <div id="panel-menu" class="fixed inset-0 z-50 hidden no-print">
        <div class="absolute inset-0 bg-black bg-opacity-50" id="superposicion-menu"></div>
        <div class="absolute left-0 top-0 h-full w-80 max-w-[90vw] bg-white shadow-xl transform -translate-x-full transition-transform duration-300" id="contenido-menu">
            <div class="flex flex-col h-full">
                <!-- Header del Menú -->
                <div class="flex items-center justify-between p-4 border-b">
                    <h2 class="text-lg font-semibold text-gray-900">Menú</h2>
                    <button id="cerrar-menu" class="p-2 rounded-lg text-gray-600 hover:bg-gray-100">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                <!-- Menú Principal -->
                <div class="flex-1 overflow-y-auto">
                    <nav class="p-4 space-y-2">
                        <button id="menu-nueva-cotizacion" class="w-full flex items-center space-x-3 p-3 rounded-lg text-gray-700 hover:bg-gray-100 transition-colors">
                            <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                            <div class="text-left">
                                <div class="font-medium">Nueva Cotización</div>
                                <div class="text-xs text-gray-500">Crear una nueva cotización</div>
                            </div>
                        </button>
                        <button id="menu-mis-cotizaciones" class="w-full flex items-center space-x-3 p-3 rounded-lg text-gray-700 hover:bg-gray-100 transition-colors">
                            <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            <div class="text-left">
                                <div class="font-medium">Gestión de Cotizaciones</div>
                                <div class="text-xs text-gray-500">Ver y administrar cotizaciones</div>
                            </div>
                        </button>
                    </nav>
                </div>
                <!-- Footer del Menú -->
                <div class="border-t p-4">
                    <div class="text-sm text-gray-600 mb-3">
                        <?= htmlspecialchars($user['fullName']) ?>
                        <div class="text-xs text-gray-400"><?= ucfirst($user['role']) ?></div>
                    </div>
                    <button id="menu-logout" class="w-full flex items-center justify-center space-x-2 p-3 rounded-lg text-red-700 hover:bg-red-50 transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013 3v1"></path>
                        </svg>
                        <span>Cerrar Sesión</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

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
            <svg class="w-16 h-16 text-red-500 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
            </svg>
            <h3 class="text-lg font-medium text-gray-900 mb-2">Error al cargar</h3>
            <p id="error-message" class="text-gray-600 mb-4 text-sm"></p>
            <button onclick="window.history.back()" class="bg-accent text-white px-4 py-2 rounded-lg hover:bg-blue-600 transition-colors text-sm">
                Volver
            </button>
        </div>
    </div>

    <!-- Main Content -->
    <main id="main-content" class="hidden flex-1 overflow-y-auto">
        <div class="max-w-md sm:max-w-lg md:max-w-2xl lg:max-w-4xl xl:max-w-6xl mx-auto w-full">
            <!-- Header de Cotización -->
            <div class="bg-white border-b p-4 lg:p-6 sticky top-0 z-10">
                <div class="flex items-center justify-between mb-3">
                    <h1 class="text-xl md:text-2xl lg:text-3xl font-bold text-gray-900">
                        Cotización <span id="cotizacion-numero">#000</span>
                    </h1>
                    <div id="estado-badge" class="text-xs md:text-sm px-2 py-1 md:px-3 md:py-1.5 rounded-full"></div>
                </div>
                
                <!-- Información Básica -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-2 md:gap-4 text-sm md:text-base">
                    <!-- <div class="flex justify-between md:flex-col md:justify-start">
                        <span class="text-gray-600">Cliente:</span>
                        <span id="cliente-info" class="font-medium text-right md:text-left md:mt-1"></span>
                    </div> -->
                    <!-- <div class="flex justify-between md:flex-col md:justify-start">
                        <span class="text-gray-600">Vendedor:</span>
                        <span id="vendedor-info" class="font-medium text-right md:text-left md:mt-1"></span>
                    </div> -->
                    <div class="flex justify-between md:flex-col md:justify-start">
                        <span class="text-gray-600">Fecha:</span>
                        <span id="fecha-info" class="font-medium text-right md:text-left md:mt-1"></span>
                    </div>
                </div>

                <!-- Botones de Acción -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-1 gap-2 mt-4 no-print">
                    <div id="admin-actions" class="hidden">
                        <button id="btn-eliminar" class="w-full bg-red-600 text-white py-2 px-3 md:py-3 lg:py-2 rounded-lg hover:bg-red-700 transition-colors text-sm font-medium flex items-center justify-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                            </svg>
                            Eliminar
                        </button>
                    </div>
                </div>

                <!-- Botones de Edición (solo para admin/vendor) -->
                <div id="edit-actions" class="hidden grid grid-cols-1 gap-2 mt-2 no-print">
                    <button id="btn-editar" class="w-full bg-orange-600 text-white py-2 px-3 md:py-3 lg:py-2 rounded-lg hover:bg-orange-700 transition-colors text-sm font-medium flex items-center justify-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                        </svg>
                        Editar
                    </button>
                    <div id="edit-mode-actions" class="hidden grid grid-cols-2 gap-2">
                        <button id="btn-guardar" class="bg-green-600 text-white py-2 px-3 rounded-lg hover:bg-green-700 transition-colors text-sm font-medium flex items-center justify-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span class="hidden sm:inline">Guardar</span>
                        </button>
                        <button id="btn-cancelar" class="bg-gray-600 text-white py-2 px-3 rounded-lg hover:bg-gray-700 transition-colors text-sm font-medium flex items-center justify-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                            <span class="hidden sm:inline">Cancelar</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Layout Principal (Responsive) -->
            <div class="lg:grid lg:grid-cols-3 lg:gap-6 lg:p-6">
                <!-- Columna Principal (Equipos) -->
                <div class="lg:col-span-2">
                    <!-- Equipos -->
                    <div class="p-4 lg:p-0">
                        <div id="lista-equipos" class="space-y-3">
                            <!-- Se llena dinámicamente -->
                        </div>
                    </div>
                </div>

                <!-- Sidebar (Totales y Resumen) -->
                <div class="lg:col-span-1 sidebar-sticky">
                    <!-- Totales -->
                    <div class="bg-gray-50 p-4 lg:p-0">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4 lg:mb-6">Resumen</h3>
                        
                        <!-- Información del Contrato -->
                        <div class="bg-white rounded-lg p-4 lg:p-6 mb-4 shadow-sm">
                            <h4 class="font-medium text-gray-900 mb-3 lg:mb-4">Información del Contrato</h4>
                            <div class="space-y-3 text-sm lg:text-base">
                                <div class="grid grid-cols-2 gap-2 lg:block lg:space-y-2">
                                    <div class="flex justify-between lg:justify-between">
                                        <span class="text-gray-600">Moneda:</span>
                                        <span id="moneda-contrato" class="font-medium"></span>
                                    </div>
                                    <div class="flex justify-between lg:justify-between">
                                        <span class="text-gray-600">Plazo:</span>
                                        <span id="plazo-contrato" class="font-medium"></span>
                                    </div>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">% Residual:</span>
                                    <span id="porcentaje-residual" class="font-medium"></span>
                                </div>
                                <div id="info-sensible" class="space-y-2 border-t pt-2 mt-3">
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Tasa:</span>
                                        <span id="tasa-interes" class="font-medium"></span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Comisión:</span>
                                        <span id="comision-valor" class="font-medium"></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Totales Financieros -->
                        <div class="bg-white rounded-lg p-4 lg:p-6 shadow-sm">
                            <h4 class="font-medium text-gray-900 mb-3 lg:mb-4">Totales</h4>
                            <div class="space-y-3 text-sm lg:text-base">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Subtotal:</span>
                                    <span id="subtotal-valor" class="font-medium"></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">IVA:</span>
                                    <span id="iva-valor" class="font-medium"></span>
                                </div>
                                <div class="flex justify-between border-t pt-3 font-semibold">
                                    <span>Total Equipo:</span>
                                    <span id="total-equipo" class="text-lg lg:text-xl"></span>
                                </div>
                                <div id="totales-financieros" class="space-y-3 border-t pt-3 mt-3">
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Pago Mensual:</span>
                                        <span id="pago-mensual" class="font-medium text-lg lg:text-xl text-accent"></span>
                                    </div>

                                    <div id="anticipo-container" class="flex justify-between pt-3" style="display: none;">
                                        <span class="text-gray-600">Anticipo:</span>
                                        <span id="anticipo" class="font-medium text-lg lg:text-xl text-green-600"></span>
                                    </div>

                                    <div class="flex justify-between pt-3">
                                        <span class="text-gray-600">Monto a Financiar:</span>
                                        <span id="monto-financiar" class="font-medium text-lg lg:text-xl text-blue-600"></span>
                                    </div>

                                    <div class="flex justify-between border-t pt-3 font-bold">
                                        <span>Total Contrato:</span>
                                        <span id="total-contrato" class="text-xl lg:text-2xl text-success"></span>
                                    </div>                                    
                                    <!-- Sección de Residuales -->
                                    <div class="bg-blue-50 p-4 lg:p-5 rounded-lg border border-blue-200 mt-4">
                                        <h4 class="font-medium text-blue-900 mb-3 text-sm lg:text-base">Opciones de Residual</h4>
                                        <div class="space-y-2">
                                            <div class="flex justify-between">
                                                <span class="text-blue-700 text-sm lg:text-base">1 Pago total:</span>
                                                <span id="total-residual-1pago" class="font-medium text-blue-900"></span>
                                            </div>
                                            <div class="flex justify-between">
                                                <span class="text-blue-700 text-sm lg:text-base">3 Pagos c/u:</span>
                                                <span id="total-residual-3pagos" class="font-medium text-blue-900"></span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="flex justify-between border-t pt-3">
                                        <span class="text-gray-600">Utilidad:</span>
                                        <span id="utilidad-total" class="font-medium lg:text-lg"></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Loading Overlay -->
    <div id="loading-overlay" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center no-print">
        <div class="bg-white rounded-lg p-6 text-center">
            <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-accent mb-4"></div>
            <p class="text-gray-600">Guardando cambios...</p>
        </div>
    </div>

    <!-- Scripts -->
    <script>
        // Configuración global
        window.CURRENT_USER = <?= json_encode($user) ?>;
        window.COTIZACION_ID = <?= $cotizacionId ?>;
        
        // Menu functionality
        function abrirMenu() {
            const panel = document.getElementById('panel-menu');
            const contenido = document.getElementById('contenido-menu');
            panel.classList.remove('hidden');
            setTimeout(() => {
                contenido.classList.remove('-translate-x-full');
            }, 10);
        }

        function cerrarMenu() {
            const panel = document.getElementById('panel-menu');
            const contenido = document.getElementById('contenido-menu');
            contenido.classList.add('-translate-x-full');
            setTimeout(() => {
                panel.classList.add('hidden');
            }, 300);
        }

        // Event listeners
        document.getElementById('menu-hamburguesa').addEventListener('click', abrirMenu);
        document.getElementById('cerrar-menu').addEventListener('click', cerrarMenu);
        document.getElementById('superposicion-menu').addEventListener('click', cerrarMenu);

        document.getElementById('boton-atras').addEventListener('click', () => {
            window.history.back();
        });

        document.getElementById('menu-nueva-cotizacion').addEventListener('click', () => {
            cerrarMenu();
            window.location.href = 'index.php';
        });

        document.getElementById('menu-mis-cotizaciones').addEventListener('click', () => {
            cerrarMenu();
            window.location.href = 'cotizaciones.php';
        });

        document.getElementById('menu-logout').addEventListener('click', () => {
            window.location.href = 'logout.php';
        });
    </script>
    <script src="assets/js/detalle-cotizacion.js"></script>
    <script src="assets/js/desktop-utils.js"></script>
</body>
</html>