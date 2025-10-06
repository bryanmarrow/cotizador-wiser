<?php
require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'config/email_config.php';

// Requiere estar logueado
requireLogin();

// Obtener usuario actual
$currentUser = getCurrentUser();
iniciarSesion();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Cotizador</title>
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
    <link rel="stylesheet" href="assets/css/custom.css">
    <style>
        .loader {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: conic-gradient(
                from 0deg,
                #3b82f6 0deg,
                #1d4ed8 90deg,
                #f3f4f6 180deg,
                #f3f4f6 270deg,
                #3b82f6 360deg
            );
            animation: spin 1s linear infinite;
            position: relative;
        }
        
        .loader::before {
            content: '';
            position: absolute;
            top: 2px;
            left: 2px;
            right: 2px;
            bottom: 2px;
            background: white;
            border-radius: 50%;
        }
        
        .logo-pulse {
            animation: pulse 1.5s ease-in-out infinite;
        }
        
        .loader-fade-in {
            animation: fadeIn 0.3s ease-out;
        }
        
        .loader-fade-out {
            animation: fadeOut 0.3s ease-out;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.05); opacity: 0.8; }
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes fadeOut {
            from { opacity: 1; }
            to { opacity: 0; }
        }
        
        .loader-overlay {
            backdrop-filter: blur(2px);
            transition: all 0.3s ease;
        }

        /* Mobile scroll improvements */
        main {
            -webkit-overflow-scrolling: touch;
            scroll-behavior: smooth;
        }

        /* Desktop main improvements */
        @media (min-width: 768px) {
            main {
                overflow: visible;
                height: auto;
                min-height: auto;
            }
            
            body {
                overflow-y: auto;
                height: auto;
            }
            
            /* Ensure proper layout for desktop */
            .flex.flex-col {
                min-height: 100vh;
            }
        }

        /* Tablet and desktop specific adjustments */
        @media (min-width: 768px) {
            /* Better spacing and layout */
            .contenido-fase {
                margin-bottom: 2rem;
            }
            
            /* Improved form layouts */
            .grid.grid-cols-1 {
                align-items: start;
            }
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

        /* Enhanced button touch targets for mobile */
        @media (max-width: 640px) {
            button {
                min-height: 44px;
                min-width: 44px;
            }
        }

        /* Desktop and tablet improvements */
        @media (min-width: 768px) {
            .contenido-fase {
                animation: fadeInUp 0.3s ease-out;
            }
            
            /* Better hover states for desktop */
            button:hover {
                transform: translateY(-1px);
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                transition: all 0.2s ease;
            }
            
            /* Form improvements */
            input:focus, select:focus {
                transform: scale(1.02);
                transition: transform 0.2s ease;
            }
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Footer gradient animation */
        .footer-gradient {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            position: relative;
            overflow: hidden;
        }

        .footer-gradient::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
            transition: left 0.5s;
        }

        .footer-gradient:hover::before {
            left: 100%;
        }

        /* Total amount animation */
        .total-animate {
            animation: totalPulse 0.3s ease-out;
        }

        @keyframes totalPulse {
            0% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.05);
            }
            100% {
                transform: scale(1);
            }
        }

        /* Loader with gradient animation */
        .loader-gradient {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: conic-gradient(
                from 0deg,
                #3b82f6,
                #1d4ed8,
                #6366f1,
                #8b5cf6,
                #a855f7,
                #3b82f6
            );
            animation: spinGradient 1.5s linear infinite;
            position: relative;
        }
        
        .loader-gradient::before {
            content: '';
            position: absolute;
            top: 3px;
            left: 3px;
            right: 3px;
            bottom: 3px;
            background: white;
            border-radius: 50%;
        }
        
        .loader-gradient::after {
            content: '';
            position: absolute;
            top: 6px;
            left: 6px;
            right: 6px;
            bottom: 6px;
            background: conic-gradient(
                from 0deg,
                #ddd6fe,
                #c7d2fe,
                #bfdbfe,
                #ddd6fe
            );
            border-radius: 50%;
            animation: spinGradient 2s linear infinite reverse;
        }

        @keyframes spinGradient {
            0% {
                transform: rotate(0deg);
            }
            100% {
                transform: rotate(360deg);
            }
        }

        /* Progress bar enhancements */
        .progress-bar {
            background: linear-gradient(90deg, #3b82f6, #1d4ed8, #6366f1, #3b82f6);
            background-size: 200% 100%;
            animation: progressShimmer 2s ease-in-out infinite;
            position: relative;
            overflow: hidden;
        }

        .progress-bar::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            animation: progressGlow 1.5s ease-in-out infinite;
        }

        @keyframes progressShimmer {
            0%, 100% {
                background-position: 0% 50%;
            }
            50% {
                background-position: 100% 50%;
            }
        }

        @keyframes progressGlow {
            0% {
                left: -100%;
            }
            100% {
                left: 100%;
            }
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen flex flex-col">
    <!-- Loader Global -->
    <div id="global-loader" class="fixed inset-0 bg-white bg-opacity-95 loader-overlay z-50 hidden items-center justify-center">
        <div class="text-center loader-fade-in">
            <img id="loader-logo" src="assets/img/logo_wiser_web.webp" alt="WISER" class="w-32 h-auto mx-auto mb-6 logo-pulse">
            <div class="relative mx-auto mb-6">
                <div class="loader-gradient mx-auto"></div>
            </div>
            <p id="loader-message" class="text-gray-600 font-medium">Cargando...</p>
        </div>
    </div>
    <!-- Header -->
    <header class="bg-white shadow-sm border-b">
        <div class="responsive-container py-3">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <button id="boton-atras" class="hidden p-2 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                        </svg>
                    </button>
                    <div class="flex items-center space-x-2">
                        <img src="assets/img/logo_wiser_web.webp" alt="WISER" class="h-8 w-auto">
                        <div class="hidden sm:block">
                            <span class="text-sm font-medium text-gray-900" id="titulo-fase">Cotizaciones</span>
                            <!-- <div class="text-xs text-gray-500" id="subtitulo-fase">Sistema de cotizaciones</div> -->
                        </div>
                    </div>
                </div>
                <div class="flex items-center space-x-2">
                    <button id="alternar-carrito" class="relative p-2 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4m-2.4 2L7 13m0 0l-1.5 1.5M7 13l1.5 1.5m7.5 3a2 2 0 11-4 0 2 2 0 014 0zm-7 0a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                        <span id="contador-carrito" class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center hidden">0</span>
                    </button>
                    <button id="menu-hamburguesa" class="p-2 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors" title="Menú">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="flex-1 overflow-y-auto md:overflow-visible md:flex-none">
        <!-- Progress Bar -->
        <div class="w-full bg-white border-b border-gray-200 px-4 py-4">
            <div class="responsive-container">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-sm text-gray-600">Progreso</span>
                    <span class="text-sm text-gray-600"><span id="paso-actual">1</span> de 5</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2 overflow-hidden">
                    <div id="barra-progreso" class="progress-bar h-2 rounded-full transition-all duration-300" style="width: 20%"></div>
                </div>
            </div>
        </div>
        
        <div class="max-w-md sm:max-w-lg md:max-w-2xl lg:max-w-4xl xl:max-w-5xl mx-auto px-4 py-4 pb-4 md:pb-8">
        <!-- Inicio -->
        <div id="fase-0" class="contenido-fase">
            <div class="bg-white rounded-lg shadow-sm p-6 mb-4">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Cotizaciones</h2>
                <button id="boton-nueva-cotizacion" class="w-full bg-accent text-white py-3 px-4 rounded-lg font-medium hover:bg-blue-600 transition-colors">
                    Nueva cotización
                </button>
            </div>
                       
        </div>

        <!-- Fase 1: Cliente/Moneda/Tasa -->
        <div id="fase-1" class="contenido-fase hidden">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-6">
                <div class="bg-white rounded-lg shadow-sm p-6 space-y-6">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Información del Cliente</h3>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Tipo de cliente</label>
                        <select id="tipo-cliente" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-accent focus:border-transparent">
                            <option value="">Selecciona tipo de cliente</option>
                        </select>
                    </div>

                    <!-- Campo de tasa anual (visible solo para admin/vendedor) -->
                    <div id="contenedor-tasa-personalizada" class="hidden">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Tasa Anual</label>
                        <div class="relative">
                            <input type="number" id="tasa-personalizada" placeholder="12" min="12" max="24" step="0.1" class="w-full p-3 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-accent focus:border-transparent">
                            <span class="absolute right-3 top-3 text-gray-500">%</span>
                        </div>
                        <div class="text-xs text-gray-500 mt-1">Rango permitido: 12% - 24% anual</div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Cliente</label>
                        <input type="text" id="nombre-cliente" placeholder="Nombre del cliente" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-accent focus:border-transparent">
                    </div>
                </div>

<?php if (puedeVerInformacionSensible()): ?>
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Configuración Financiera</h3>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Tasa Anual</label>
                        <div class="flex items-center space-x-2">
                            <span id="tasa-actual" class="text-lg font-semibold text-gray-900">0%</span>
                        </div>
                    </div>
                </div>
<?php else: ?>
                <div class="bg-blue-50 rounded-lg shadow-sm p-6 flex items-center justify-center">
                    <div class="text-center">
                        <svg class="w-12 h-12 text-blue-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <p class="text-blue-700 font-medium">Información del Cliente</p>
                        <p class="text-blue-600 text-sm">Complete los datos del cliente para continuar</p>
                    </div>
                </div>
<?php endif; ?>
            </div>
        </div>

        <!-- Fase 2: Términos Globales (Plazo, Residual) -->
        <div id="fase-2" class="contenido-fase hidden">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-6">
                <div class="bg-white rounded-lg shadow-sm p-6 space-y-6">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Términos de la cotización</h3>
                        <p class="text-sm text-gray-600 mb-6">Estos términos se aplicarán a todos los equipos de la cotización</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Moneda</label>
                        <select id="tipo-moneda" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-accent focus:border-transparent">
                            <option value="">Selecciona moneda</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Plazo</label>
                        <select id="plazo-global" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-accent focus:border-transparent">
                            <option value="">Seleccionar plazo</option>
                        </select>

                        <!-- Custom term input (shown when OTRO is selected) -->
                        <div id="contenedor-plazo-personalizado" class="hidden mt-3">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Plazo personalizado</label>
                            <div class="relative">
                                <input type="number" id="plazo-personalizado-global" placeholder="12" min="1" max="24" class="w-full p-3 pr-16 border border-gray-300 rounded-lg focus:ring-2 focus:ring-accent focus:border-transparent">
                                <span class="absolute right-3 top-3 text-gray-500">meses</span>
                            </div>
                        </div>
                    </div>

                    <!-- Anticipo -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">¿Desea dar un anticipo?</label>
                        <div class="flex gap-4">
                            <label class="flex items-center cursor-pointer">
                                <input type="radio" name="anticipo-opcion" id="anticipo-no" value="no" checked class="w-4 h-4 text-accent focus:ring-accent">
                                <span class="ml-2 text-sm">No</span>
                            </label>
                            <label class="flex items-center cursor-pointer">
                                <input type="radio" name="anticipo-opcion" id="anticipo-si" value="si" class="w-4 h-4 text-accent focus:ring-accent">
                                <span class="ml-2 text-sm">Sí</span>
                            </label>
                        </div>

                        <!-- Input de anticipo (hidden por defecto) -->
                        <div id="contenedor-anticipo" class="hidden mt-3">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Monto del anticipo</label>
                            <div class="relative">
                                <span class="absolute left-3 top-3 text-gray-500">$</span>
                                <input type="number" id="monto-anticipo" placeholder="0" min="0" max="200000" step="0.01" class="w-full p-3 pl-8 border border-gray-300 rounded-lg focus:ring-2 focus:ring-accent focus:border-transparent">
                            </div>
                            <div class="text-xs text-gray-500 mt-1">Máximo: $200,000.00</div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Porcentaje Residual</label>
                        <div class="relative">
                            <input type="number" id="residual-global" placeholder="20" min="20" max="50" step="1" value="20" class="w-full p-3 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-accent focus:border-transparent">
                            <span class="absolute right-3 top-3 text-gray-500">%</span>
                        </div>
                        <div class="text-xs text-gray-500 mt-1">Valor estimado del equipo al final del contrato (20%-50%)</div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-sm p-6">
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                        <div class="flex items-start space-x-3">
                            <svg class="w-5 h-5 text-blue-600 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <div>
                                <h4 class="text-sm font-medium text-blue-900 mb-1">Términos globales</h4>
                                <p class="text-xs text-blue-700">Estos términos se aplicarán automáticamente a todos los equipos que agregues a la cotización.</p>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div class="text-center">
                            <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                            </svg>
                            <h4 class="text-lg font-semibold text-gray-900 mb-2">Configuración Global</h4>
                            <p class="text-sm text-gray-600">Configure los parámetros que se aplicarán a todos los equipos de su cotización</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Fase 3: Selección de Equipos -->
        <div id="fase-3" class="contenido-fase hidden">
            <div class="grid grid-cols-1 lg:grid-cols-4 gap-4 lg:gap-6">
                <!-- Formulario de equipo -->
                <div class="lg:col-span-2">
                    <div class="bg-white rounded-lg shadow-sm p-6 space-y-6" id="formulario-equipo">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">Agregar Equipo</h3>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Cantidad</label>
                            <input type="number" id="cantidad-equipo" value="1" min="1" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-accent focus:border-transparent">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Tipo de equipo</label>
                            <select id="tipo-equipo" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-accent focus:border-transparent">
                                <option value="">Seleccionar equipo</option>
                            </select>
                            
                            <!-- Campo personalizado para otro equipo -->
                            <div id="contenedor-otro-equipo" class="hidden mt-3">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Otro equipo</label>
                                <input type="text" id="otro-equipo" placeholder="Ingrese el tipo de equipo" maxlength="80" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-accent focus:border-transparent">
                                <p class="text-xs text-gray-500 mt-1">Máximo 80 caracteres</p>
                            </div>
                        </div>
                        <div id="contenedor-modelo" class="hidden">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Modelo</label>
                            <select id="modelo-equipo" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-accent focus:border-transparent">
                                <option value="">Seleccionar modelo</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Marca</label>
                            <select id="marca-equipo" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-accent focus:border-transparent">
                                <option value="">Seleccionar marca</option>
                            </select>
                            
                            <!-- Campo personalizado para otra marca -->
                            <div id="contenedor-otra-marca" class="hidden mt-3">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Otra marca</label>
                                <input type="text" id="otra-marca" placeholder="Ingrese la marca" maxlength="80" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-accent focus:border-transparent">
                                <p class="text-xs text-gray-500 mt-1">Máximo 80 caracteres</p>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Costo</label>
                            <div class="relative">
                                <span class="absolute left-3 top-3 text-gray-500">$</span>
                                <input type="number" id="costo-equipo" placeholder="0.00" step="0.01" class="w-full p-3 pl-8 border border-gray-300 rounded-lg focus:ring-2 focus:ring-accent focus:border-transparent">
                            </div>
                        </div>

                        <div class="flex space-x-3">
                            <button id="agregar-equipo" class="flex-1 bg-accent text-white py-3 px-4 rounded-lg font-medium hover:bg-blue-600 transition-colors">
                                Agregar equipo
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Lista de equipos -->
                <div class="lg:col-span-2">
                    <div class="bg-white rounded-lg shadow-sm p-6">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-lg font-semibold text-gray-900">Equipos Seleccionados</h3>
                            <div class="text-sm text-gray-500">
                                <span id="contador-equipos">0</span> equipos
                            </div>
                        </div>
                        
                        <!-- Equipment List -->
                        <div id="lista-equipos" class="space-y-3">
                            <div class="text-center py-8 text-gray-500">
                                <svg class="w-12 h-12 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                                </svg>
                                <p>No hay equipos agregados</p>
                                <p class="text-xs">Use el formulario de la izquierda para agregar equipos</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Fase 4: Resumen -->
        <div id="fase-4" class="contenido-fase hidden">
            <div class="space-y-4">
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Resumen y confirmación</h3>
                    <div id="contenido-resumen"></div>
                </div>
            </div>
        </div>
        
        <!-- Fase 5: Confirmación Final -->
        <div id="fase-5" class="contenido-fase hidden">
            <div class="bg-white rounded-lg shadow-sm p-6 text-center">
                <div class="w-16 h-16 bg-success rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                </div>
                <h3 class="text-xl font-semibold text-gray-900 mb-2">Cotización guardada</h3>
                <p class="text-gray-600 mb-6">Tu cotización ha sido guardada exitosamente</p>
                
                <!-- Sección de QR y Enlace Compartido -->
                <div id="seccion-enlace-compartido" class="bg-blue-50 p-6 rounded-lg border border-blue-200 mb-6">
                    <div class="flex justify-center mb-4">
                        <div class="bg-blue-100 p-3 rounded-full">
                            <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path>
                            </svg>
                        </div>
                    </div>
                    <h4 class="text-lg font-semibold text-blue-900 mb-2">Enlace para Compartir</h4>
                    <p class="text-blue-700 text-sm mb-4">Comparte esta cotización usando el QR o enlace</p>
                    
                    <div class="space-y-4">
                        <!-- QR Code -->
                        <div id="contenedor-qr" class="hidden">
                            <div class="bg-white p-4 rounded-lg inline-block">
                                <img id="imagen-qr" src="" alt="Código QR" class="w-32 h-32 mx-auto">
                            </div>
                            <p class="text-xs text-blue-600 mt-2">Folio: <span id="folio-compartido"></span></p>
                            <p class="text-xs text-blue-500">Válido por 24 horas</p>
                        </div>
                        
                        <!-- Enlace -->
                        <div id="contenedor-enlace" class="hidden">
                            <div class="bg-white p-3 rounded border text-left">
                                <div class="flex items-center justify-between">
                                    <input type="text" id="enlace-publico" readonly 
                                           class="flex-1 text-xs text-gray-600 bg-transparent border-none outline-none mr-2">
                                    <button id="btn-copiar-enlace" 
                                            class="text-blue-600 hover:text-blue-800 text-xs font-medium">
                                        Copiar
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Botón generar -->
                        <button id="btn-generar-enlace" 
                                class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition-colors text-sm font-medium">
                            Generar Enlace Compartido
                        </button>
                        
                        <!-- Sección de envío por email -->
                        <div id="seccion-envio-email" class="border-t pt-4 mt-4 hidden">
                            <h5 class="text-md font-medium text-blue-900 mb-3">Enviar por Email</h5>
                            
                            <!-- Campo email para admin/vendor -->
                            <div id="contenedor-email-destino" class="mb-3 hidden">
                                <input type="email" id="email-destino" placeholder="Ingrese email del cliente" 
                                       class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm">
                                <p class="text-xs text-gray-500 mt-1">Se enviará la cotización a esta dirección</p>
                            </div>
                            
                            <!-- Info para cliente -->
                            <div id="info-email-cliente" class="mb-3 hidden">
                                <p class="text-sm text-blue-700">Se enviará a tu email registrado: <span id="email-usuario-actual" class="font-medium"></span></p>
                            </div>
                            
                            <button id="btn-enviar-email" 
                                    class="w-full bg-green-600 text-white px-6 py-2 rounded-lg hover:bg-green-700 transition-colors text-sm font-medium flex items-center justify-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                </svg>
                                Enviar Cotización por Email
                            </button>
                        </div>
                        
                        <!-- Loading -->
                        <div id="loading-enlace" class="hidden">
                            <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-600 mx-auto"></div>
                            <p class="text-xs text-blue-600 mt-2">Generando enlace...</p>
                        </div>
                        
                        <!-- Loading Email -->
                        <div id="loading-email" class="hidden">
                            <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-green-600 mx-auto"></div>
                            <p class="text-xs text-green-600 mt-2">Enviando email...</p>
                        </div>
                    </div>
                </div>
                
                <div class="space-y-3 mt-6">
                    <!-- <button id="imprimir-cotizacion" class="w-full bg-accent text-white py-3 px-4 rounded-lg font-medium hover:bg-blue-600 transition-colors">
                        Imprimir cotización
                    </button> -->
                    <button id="nueva-cotizacion-final" class="w-full bg-gray-600 text-white py-3 px-4 rounded-lg font-medium hover:bg-gray-700 transition-colors">
                        Nueva cotización
                    </button>
                </div>
            </div>
        </div>
        </div>
    </main>
    

    <!-- Navigation Footer -->
    <footer class="bg-white border-t shadow-lg">
        <div class="responsive-container">
            <!-- Total del contrato destacado (para vendedores y admin) -->
            <div class="footer-gradient text-white px-4 py-3" id="total-contrato-section">
                <div class="text-center">
                    <div class="text-xs font-medium opacity-90 mb-1">Total del Contrato</div>
                    <div class="text-xl font-bold" id="total-pie">$0.00</div>
                </div>
            </div>

            <!-- Desglose para clientes -->
            <div class="bg-blue-600 text-white px-4 py-3 hidden" id="desglose-cliente-section">
                <div class="space-y-2">
                    <div class="flex justify-between items-center">
                        <span class="text-xs font-medium opacity-90">Pago Mensual:</span>
                        <span class="text-lg font-bold" id="pago-mensual-pie">$0.00</span>
                    </div>
                    <div class="border-t border-blue-400 pt-2 space-y-1">
                        <div class="flex justify-between text-xs">
                            <span class="opacity-90">Subtotal:</span>
                            <span class="font-semibold" id="subtotal-pie">$0.00</span>
                        </div>
                        <div class="flex justify-between text-xs">
                            <span class="opacity-90">IVA (16%):</span>
                            <span class="font-semibold" id="iva-pie">$0.00</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Info destacada -->
            <div class="px-4 py-2 border-b border-gray-100" id="info-destacada">
                <div class="flex flex-wrap justify-center items-center gap-2 sm:gap-3 text-xs font-medium bg-blue-50 rounded-lg py-2 px-3" id="contenedor-info-destacada">
                    <span id="tasa-footer" class="hidden text-blue-800">
                        <span class="text-gray-600">Tasa:</span> <span id="tasa-valor" class="font-semibold">-</span>
                    </span>
                    <span id="plazo-footer" class="hidden text-blue-800">
                        <span class="text-gray-600">Plazo:</span> <span id="plazo-valor" class="font-semibold">-</span>
                    </span>
                    <span id="residual-footer" class="hidden text-blue-800">
                        <span class="text-gray-600">Residual:</span> <span id="residual-valor" class="font-semibold">-</span>
                    </span>
                </div>
            </div>
            
            <!-- Botones de navegación -->
            <div class="px-4 py-3">
                <div class="flex justify-between items-center gap-3 max-w-md mx-auto sm:max-w-lg md:max-w-xl">
                    <button id="boton-anterior" class="flex-1 sm:flex-none sm:min-w-[120px] py-3 px-4 text-gray-600 font-medium rounded-lg border border-gray-300 disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50 transition-colors" disabled>
                        Anterior
                    </button>
                    
                    <button id="boton-siguiente" class="flex-1 sm:flex-none sm:min-w-[120px] py-3 px-4 bg-accent text-white font-medium rounded-lg hover:bg-blue-600 transition-colors disabled:opacity-50 disabled:cursor-not-allowed shadow-lg">
                        Siguiente
                    </button>
                </div>
            </div>
        </div>
    </footer>

    <!-- Side Cart Drawer -->
    <div id="panel-carrito" class="fixed inset-0 z-50 hidden">
        <div class="absolute inset-0 bg-black bg-opacity-50" id="superposicion-carrito"></div>
        <div class="absolute right-0 top-0 h-full w-80 sm:w-96 md:w-[420px] max-w-[90vw] bg-white shadow-xl transform translate-x-full transition-transform duration-300" id="contenido-carrito">
            <div class="flex items-center justify-between p-4 border-b">
                <h3 class="text-lg font-semibold text-gray-900">Carrito</h3>
                <button id="cerrar-carrito" class="p-2 rounded-lg text-gray-600 hover:bg-gray-100">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            <div class="p-4 flex-1 overflow-y-auto" id="elementos-carrito">
                <p class="text-gray-500 text-center py-8">No hay equipos agregados</p>
            </div>
            <div class="p-4 border-t bg-gray-50">
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span>Total  del contrato:</span>
                        <span class="font-semibold" id="total-contrato-carrito">$0.00</span>
                    </div>
<?php if (puedeVerInformacionSensible()): ?>
                    <div class="flex justify-between">
                        <span>Utilidad:</span>
                        <span class="font-semibold" id="total-utilidad-carrito">$0.00</span>
                    </div>
<?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Side Menu Drawer -->
    <div id="panel-menu" class="fixed inset-0 z-50 hidden">
        <div class="absolute inset-0 bg-black bg-opacity-50" id="superposicion-menu"></div>
        <div class="absolute left-0 top-0 h-full w-80 sm:w-96 md:w-[420px] max-w-[90vw] bg-white shadow-xl transform -translate-x-full transition-transform duration-300" id="contenido-menu">
            <!-- Header del menú -->
            <div class="flex items-center justify-between p-4 border-b bg-gray-50">
                <div class="flex items-center space-x-3">
                    <img src="assets/img/logo_wiser_web.webp" alt="WISER" class="h-8 w-auto">
                    <!-- <h3 class="text-lg font-semibold text-gray-900">WISER</h3> -->
                </div>
                <button id="cerrar-menu" class="p-2 rounded-lg text-gray-600 hover:bg-gray-100">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <!-- Información del Usuario -->
            <div class="p-4 border-b bg-blue-50">
                <div class="flex items-center space-x-3">
                    <div class="w-12 h-12 bg-blue-600 rounded-full flex items-center justify-center">
                        <span class="text-white font-medium text-lg">
                            <?php echo strtoupper(substr($currentUser['full_name'], 0, 1)); ?>
                        </span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-gray-900 font-medium truncate"><?php echo htmlspecialchars($currentUser['full_name']); ?></p>
                        <!-- <p class="text-gray-600 text-sm"><?php echo htmlspecialchars($currentUser['email']); ?></p> -->
                        <!-- <p class="text-blue-600 text-xs font-medium uppercase"><?php echo ucfirst($currentUser['role']); ?></p> -->
                    </div>
                </div>
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

                    <!-- <?php if ($currentUser['role'] === 'admin'): ?>
                    <button id="menu-usuarios" class="w-full flex items-center space-x-3 p-3 rounded-lg text-gray-700 hover:bg-gray-100 transition-colors">
                        <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                        </svg>
                        <div class="text-left">
                            <div class="font-medium">Gestión de Usuarios</div>
                            <div class="text-xs text-gray-500">Administrar usuarios del sistema</div>
                        </div>
                    </button>
                    <?php endif; ?>

                    <div class="border-t pt-4 mt-4">
                        <button id="menu-perfil" class="w-full flex items-center space-x-3 p-3 rounded-lg text-gray-700 hover:bg-gray-100 transition-colors">
                            <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                            </svg>
                            <div class="text-left">
                                <div class="font-medium">Mi Perfil</div>
                                <div class="text-xs text-gray-500">Configuración de cuenta</div>
                            </div>
                        </button>
                    </div> -->
                </nav>
            </div>

            <!-- Footer del menú -->
            <div class="p-4 border-t bg-gray-50">
                <button id="menu-logout" class="w-full flex items-center justify-center space-x-2 p-3 rounded-lg text-red-700 hover:bg-red-50 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                    </svg>
                    <span class="font-medium">Cerrar Sesión</span>
                </button>
                <!-- <div class="mt-3 text-center">
                    <p class="text-xs text-gray-500">&copy; 2025 WISER</p>
                    <p class="text-xs text-gray-400">Versión 2.0</p>
                </div> -->
            </div>
        </div>
    </div>

    <!-- Loading Spinner -->
    <div id="cargando" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden items-center justify-center">
        <div class="bg-white rounded-lg p-6 text-center">
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-accent mx-auto mb-4"></div>
            <p class="text-gray-600">Cargando...</p>
        </div>
    </div>

    <!-- Scripts -->
    <script>
        // Configuración global de permisos y usuario
        window.CONFIG_PERMISOS = {
            puedeVerInformacionSensible: <?php echo puedeVerInformacionSensible() ? 'true' : 'false'; ?>,
            esVendedor: <?php echo esVendedor() ? 'true' : 'false'; ?>
        };
        
        window.CURRENT_USER = {
            id: <?php echo $currentUser['id']; ?>,
            username: '<?php echo htmlspecialchars($currentUser['username']); ?>',
            fullName: '<?php echo htmlspecialchars($currentUser['full_name']); ?>',
            email: '<?php echo htmlspecialchars($currentUser['email']); ?>',
            role: '<?php echo $currentUser['role']; ?>'
        };

        window.EMAIL_ENABLED = <?= EMAIL_ENABLED ? 'true' : 'false' ?>;
    </script>
    <script src="assets/js/wizard.js"></script>
    <script src="assets/js/cart.js"></script>
    <script src="assets/js/calculations.js"></script>
    <script src="assets/js/desktop-utils.js"></script>
    
    <script>
        // Sistema avanzado de loader
        class LoaderManager {
            constructor() {
                this.loader = document.getElementById('global-loader');
                this.message = document.getElementById('loader-message');
                this.isVisible = false;
                this.currentTimeout = null;
            }

            show(message = 'Cargando...', duration = null) {
                if (this.currentTimeout) {
                    clearTimeout(this.currentTimeout);
                }

                this.message.textContent = message;
                this.loader.classList.remove('hidden');
                this.loader.classList.add('flex', 'loader-fade-in');
                this.isVisible = true;

                // Auto-hide si se especifica duración
                if (duration) {
                    this.currentTimeout = setTimeout(() => {
                        this.hide();
                    }, duration);
                }
            }

            hide() {
                if (!this.isVisible) return;

                this.loader.classList.add('loader-fade-out');
                
                setTimeout(() => {
                    this.loader.classList.add('hidden');
                    this.loader.classList.remove('flex', 'loader-fade-in', 'loader-fade-out');
                    this.isVisible = false;
                }, 300);

                if (this.currentTimeout) {
                    clearTimeout(this.currentTimeout);
                    this.currentTimeout = null;
                }
            }

            showWithPromise(message, asyncFunction) {
                this.show(message);
                return asyncFunction().finally(() => {
                    setTimeout(() => this.hide(), 200);
                });
            }
        }

        // Instancia global del loader
        window.loaderManager = new LoaderManager();

        // Funciones de compatibilidad
        function showGlobalLoader(message = 'Cargando...') {
            window.loaderManager.show(message);
        }

        function hideGlobalLoader() {
            window.loaderManager.hide();
        }

        // Menu hamburguesa functionality
        document.getElementById('menu-hamburguesa').addEventListener('click', function() {
            const panel = document.getElementById('panel-menu');
            const contenido = document.getElementById('contenido-menu');
            
            panel.classList.remove('hidden');
            setTimeout(() => {
                contenido.classList.remove('-translate-x-full');
            }, 10);
        });

        // Cerrar menú
        document.getElementById('cerrar-menu').addEventListener('click', cerrarMenu);
        document.getElementById('superposicion-menu').addEventListener('click', cerrarMenu);

        function cerrarMenu() {
            const panel = document.getElementById('panel-menu');
            const contenido = document.getElementById('contenido-menu');
            
            contenido.classList.add('-translate-x-full');
            setTimeout(() => {
                panel.classList.add('hidden');
            }, 300);
        }

        // Menu actions
        document.getElementById('menu-nueva-cotizacion').addEventListener('click', function() {
            cerrarMenu();
            if (window.asistente) {
                window.asistente.nuevaCotizacion();
            }
        });

        document.getElementById('menu-mis-cotizaciones').addEventListener('click', function() {
            cerrarMenu();
            window.location.href = 'cotizaciones.php';
        });

        <?php if ($currentUser['role'] === 'admin'): ?>
        // document.getElementById('menu-usuarios').addEventListener('click', function() {
        //     cerrarMenu();
        //     // TODO: Implementar gestión de usuarios
        //     alert('Funcionalidad en desarrollo: Gestión de Usuarios');
        // });
        <?php endif; ?>

        // document.getElementById('menu-perfil').addEventListener('click', function() {
        //     cerrarMenu();
        //     // TODO: Implementar perfil de usuario
        //     alert('Funcionalidad en desarrollo: Mi Perfil');
        // });

        // Logout functionality (both buttons)
        function handleLogout() {
            if (confirm('¿Estás seguro de que quieres cerrar sesión?')) {
                showGlobalLoader('Cerrando sesión...');
                window.location.href = 'logout.php';
            }
        }

        document.getElementById('menu-logout').addEventListener('click', handleLogout);

        // Ocultar loader al cargar página
        window.addEventListener('load', function() {
            setTimeout(() => hideGlobalLoader(), 500);
        });

        // Interceptar cambios de fase cuando el asistente esté listo
        document.addEventListener('DOMContentLoaded', function() {
            // Esperamos a que wizard.js cargue y cree el asistente
            setTimeout(() => {
                if (window.asistente) {
                    setupPhaseLoaders();
                    setupEquipmentLoaders();
                }
            }, 100);
        });

        function setupPhaseLoaders() {
            // Interceptar función siguienteFase
            const originalSiguienteFase = window.asistente.siguienteFase;
            window.asistente.siguienteFase = function() {
                window.loaderManager.show('...', 500);
                
                setTimeout(() => {
                    originalSiguienteFase.call(this);
                }, 100);
            };

            // Interceptar función faseAnterior
            const originalFaseAnterior = window.asistente.faseAnterior;
            window.asistente.faseAnterior = function() {
                window.loaderManager.show('...', 400);
                
                setTimeout(() => {
                    originalFaseAnterior.call(this);
                }, 100);
            };
        }

        function setupEquipmentLoaders() {
            // Agregar loader al botón directamente sin interceptar la función
            const agregarEquipoBtn = document.getElementById('agregar-equipo');
            if (agregarEquipoBtn) {
                // Remover cualquier event listener existente
                const nuevoBtn = agregarEquipoBtn.cloneNode(true);
                agregarEquipoBtn.parentNode.replaceChild(nuevoBtn, agregarEquipoBtn);
                
                // Agregar el nuevo event listener con loader
                nuevoBtn.addEventListener('click', function() {
                    // Mostrar loader brevemente
                    window.loaderManager.show('Agregando equipo...', 200);
                    
                    // Llamar directamente al método del asistente
                    if (window.asistente) {
                        window.asistente.agregarEquipo();
                    }
                });
            }
        }

        // Interceptar llamadas AJAX/fetch para APIs
        const originalFetch = window.fetch;
        window.fetch = function(...args) {
            const url = args[0];
            
            // Solo mostrar loader para calcular.php
            if (typeof url === 'string' && url.includes('calcular.php')) {
                window.loaderManager.show('Procesando cálculos...');
                
                return originalFetch.apply(this, args).finally(() => {
                    setTimeout(() => window.loaderManager.hide(), 200);
                });
            }
            
            return originalFetch.apply(this, args);
        };
    </script>
</body>
</html>