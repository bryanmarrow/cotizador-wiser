<?php
session_start();
require_once 'includes/functions.php';
require_once 'config/constants.php';
require_once 'config/email_config.php';

// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
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

$pageTitle = 'Gestión de Cotizaciones';
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
        .table-animate { animation: fadeIn 0.3s ease-in-out; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
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

        /* Enhanced button touch targets for mobile */
        @media (max-width: 640px) {
            button {
                min-height: 44px;
                min-width: 44px;
            }
        }
        
        /* Desktop enhancements */
        @media (min-width: 768px) {
            /* Better spacing and layout for desktop */
            .desktop-hover:hover {
                background-color: rgba(59, 130, 246, 0.05);
                transform: translateY(-1px);
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
                transition: all 0.2s ease;
            }
            
            /* Table row hover effects */
            .table-row:hover {
                background-color: #f8fafc;
            }
            
            /* Improved pagination for desktop */
            .pagination-desktop {
                justify-content: center;
            }
            
            .pagination-desktop .pagination-info {
                position: absolute;
                left: 0;
            }
        }
        
        /* Filtros colapsables */
        .filtros-collapsed {
            max-height: 0;
            opacity: 0;
            margin-top: 0;
        }
        
        .filtros-expanded {
            max-height: 500px;
            opacity: 1;
        }
        
        /* Animación del icono chevron */
        .chevron-up {
            transform: rotate(180deg);
        }
    </style>
</head>
<body class="bg-gray-50 h-screen overflow-hidden flex flex-col">
    <!-- Header -->
    <header class="bg-white shadow-sm border-b">
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
                            <span class="text-sm font-medium text-gray-900">Gestión</span>
                            <div class="text-xs text-gray-500">Cotizaciones</div>
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

    <!-- Side Menu Drawer -->
    <div id="panel-menu" class="fixed inset-0 z-50 hidden">
        <div class="absolute inset-0 bg-black bg-opacity-50" id="superposicion-menu"></div>
        <div class="absolute left-0 top-0 h-full w-80 max-w-[90vw] bg-white shadow-xl transform -translate-x-full transition-transform duration-300" id="contenido-menu">
            <!-- Header del menú -->
            <div class="flex items-center justify-between p-4 border-b bg-gray-50">
                <div class="flex items-center space-x-3">
                    <img src="assets/img/logo_wiser_web.webp" alt="WISER" class="h-8 w-auto">
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
                            <?= strtoupper(substr($user['fullName'], 0, 1)) ?>
                        </span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-gray-900 font-medium truncate"><?= htmlspecialchars($user['fullName']) ?></p>
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

                    <button id="menu-mis-cotizaciones" class="w-full flex items-center space-x-3 p-3 rounded-lg text-gray-700 hover:bg-gray-100 transition-colors bg-blue-50">
                        <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        <div class="text-left">
                            <div class="font-medium text-blue-900">Gestión de Cotizaciones</div>
                            <div class="text-xs text-blue-600">Ver y administrar cotizaciones</div>
                        </div>
                    </button>
                </nav>
            </div>

            <!-- Footer del menú -->
            <div class="p-4 border-t bg-gray-50">
                <button id="menu-logout" class="w-full flex items-center justify-center space-x-2 p-3 rounded-lg text-red-700 hover:bg-red-50 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013 3v1"></path>
                    </svg>
                    <span class="font-medium">Cerrar Sesión</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Loading State -->
    <div id="loading-state" class="flex-1 flex items-center justify-center">
        <div class="text-center">
            <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-accent mb-4"></div>
            <p class="text-gray-600">Cargando cotizaciones...</p>
        </div>
    </div>

    <!-- Main Content -->
    <main id="main-content" class="hidden flex-1 overflow-y-auto">
        <div class="max-w-md sm:max-w-lg md:max-w-2xl lg:max-w-4xl xl:max-w-6xl mx-auto w-full">
            <!-- Header de Filtros -->
            <div class="bg-white border-b p-4 sticky top-0 z-10">
                <div class="flex items-center justify-between mb-4">
                    <h1 class="text-xl md:text-2xl font-bold text-gray-900">Cotizaciones</h1>
                    <div class="flex items-center gap-2">
                        <!-- Botón de Filtros -->
                        <button id="toggle-filtros" class="flex items-center gap-2 px-3 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">
                            <svg id="filtros-icon" class="w-4 h-4 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.414A1 1 0 013 6.707V4z"></path>
                            </svg>
                            <span>Filtros</span>
                            <svg id="chevron-filtros" class="w-4 h-4 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>
                        
                        <!-- Toggle Vista - Solo móvil -->
                        <div class="flex bg-gray-100 rounded-lg p-1 md:hidden">
                            <button id="vista-tabla" class="px-2 py-1 rounded-md text-xs font-medium bg-white text-gray-900 shadow-sm">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"></path>
                                </svg>
                            </button>
                            <button id="vista-cards" class="px-2 py-1 rounded-md text-xs font-medium text-gray-500 hover:text-gray-900">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Contenedor de Filtros Colapsable -->
                <div id="contenedor-filtros" class="hidden overflow-hidden transition-all duration-300 ease-in-out">
                    <div class="pt-4 border-t">
                        <!-- Filtros Responsivos -->
                        <div class="space-y-3">
                    <!-- Búsqueda Cliente -->
                    <div class="md:grid md:grid-cols-3 md:gap-4 md:items-end">
                        <div class="md:col-span-2">
                            <input type="text" id="filtro-cliente" placeholder="Buscar cliente..." 
                                   class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-accent focus:border-transparent">
                        </div>
                        <div class="hidden md:flex md:items-center md:gap-2">
                            <!-- Toggle Vista - Solo visible en desktop -->
                            <div class="flex bg-gray-100 rounded-lg p-1">
                                <button id="vista-tabla-desktop" class="px-3 py-1.5 rounded-md text-xs font-medium bg-white text-gray-900 shadow-sm transition-colors">
                                    <svg class="w-3 h-3 mr-1 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"></path>
                                    </svg>
                                    Tabla
                                </button>
                                <button id="vista-cards-desktop" class="px-3 py-1.5 rounded-md text-xs font-medium text-gray-500 hover:text-gray-900 transition-colors">
                                    <svg class="w-3 h-3 mr-1 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path>
                                    </svg>
                                    Cards
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Filtros Principal -->
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-2 md:gap-3">
                        <select id="filtro-estado" class="px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-accent focus:border-transparent">
                            <option value="">Todos</option>
                            <option value="borrador">Borrador</option>
                            <option value="completada">Completada</option>
                            <option value="impresa">Impresa</option>
                        </select>

                        <?php if ($user['role'] === 'admin'): ?>
                        <select id="filtro-usuario" class="px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-accent focus:border-transparent">
                            <option value="">Todos los usuarios</option>
                        </select>
                        <?php else: ?>
                        <select id="filtro-tipo" class="px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-accent focus:border-transparent">
                            <option value="">Todos los tipos</option>
                        </select>
                        <?php endif; ?>
                        
                        <input type="date" id="filtro-fecha-desde" placeholder="Desde" class="px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-accent focus:border-transparent">
                        <input type="date" id="filtro-fecha-hasta" placeholder="Hasta" class="px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-accent focus:border-transparent">
                    </div>

                    <!-- Botones -->
                    <div class="flex gap-2 md:justify-end">
                        <button id="aplicar-filtros" class="flex-1 md:flex-none md:px-6 bg-accent text-white py-2 px-3 rounded-lg hover:bg-blue-600 transition-colors text-sm font-medium">
                            <svg class="w-4 h-4 mr-1.5 inline md:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.414A1 1 0 013 6.707V4z"></path>
                            </svg>
                            Filtrar
                        </button>
                        <button id="limpiar-filtros" class="flex-1 md:flex-none md:px-6 bg-gray-500 text-white py-2 px-3 rounded-lg hover:bg-gray-600 transition-colors text-sm font-medium">
                            <svg class="w-4 h-4 mr-1.5 inline md:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                            </svg>
                            Limpiar
                        </button>
                    </div>
                        </div>
                    </div>
                </div>

                <!-- Total -->
                <div class="text-center mt-3 pt-3 border-t">
                    <span class="text-sm text-gray-600">Total: <span id="total-registros" class="font-semibold">0</span> cotizaciones</span>
                </div>
            </div>

            <!-- Vista Tabla (Mobile) -->
            <div id="contenedor-tabla" class="p-4 md:hidden">
                <div id="lista-cotizaciones-mobile" class="space-y-3">
                    <!-- Se llena dinámicamente -->
                </div>
            </div>

            <!-- Vista Cards (Mobile) -->
            <div id="contenedor-cards" class="hidden p-4 md:hidden">
                <div id="lista-cotizaciones-cards" class="space-y-3">
                    <!-- Se llena dinámicamente -->
                </div>
            </div>

            <!-- Vista Tabla Desktop -->
            <div id="contenedor-tabla-desktop" class="hidden md:block p-4 lg:p-6">
                <div class="bg-white rounded-lg shadow-sm border overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr class="text-left">
                                    <th class="px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Cliente
                                    </th>
                                    <th class="px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Fecha
                                    </th>
                                    <th class="px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Estado
                                    </th>
                                    <th class="px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Total
                                    </th>
                                    <th class="px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Usuario
                                    </th>
                                    <th class="px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Acciones
                                    </th>
                                </tr>
                            </thead>
                            <tbody id="lista-cotizaciones-desktop" class="bg-white divide-y divide-gray-200">
                                <!-- Se llena dinámicamente -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Vista Cards Desktop -->
            <div id="contenedor-cards-desktop" class="hidden md:block p-4 lg:p-6">
                <div id="lista-cotizaciones-cards-desktop" class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-4">
                    <!-- Se llena dinámicamente -->
                </div>
            </div>

            <!-- Paginación -->
            <div class="bg-white border-t p-4 sticky bottom-0">
                <div class="md:flex md:justify-between md:items-center">
                    <div class="text-sm text-gray-600 mb-3 md:mb-0">
                        <span>Mostrando <span id="desde">0</span> - <span id="hasta">0</span> de <span id="total">0</span></span>
                    </div>
                    <div class="flex justify-center items-center space-x-2">
                        <button id="pagina-anterior" class="px-3 py-2 text-sm border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                            <svg class="w-4 h-4 mr-1 inline md:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                            </svg>
                            <span class="hidden md:inline">←</span> Ant
                        </button>
                        <div id="numeros-pagina" class="flex space-x-1">
                            <!-- Se llena dinámicamente -->
                        </div>
                        <button id="pagina-siguiente" class="px-3 py-2 text-sm border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                            Sig <span class="hidden md:inline">→</span>
                            <svg class="w-4 h-4 ml-1 inline md:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Scripts -->
    <script>
        // Configuración global
        window.CURRENT_USER = <?= json_encode($user) ?>;
        window.EMAIL_ENABLED = <?= EMAIL_ENABLED ? 'true' : 'false' ?>;
        
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
            window.location.href = 'index.php';
        });

        document.getElementById('menu-nueva-cotizacion').addEventListener('click', () => {
            cerrarMenu();
            window.location.href = 'index.php';
        });

        document.getElementById('menu-mis-cotizaciones').addEventListener('click', () => {
            cerrarMenu();
            // Ya estamos en cotizaciones
        });

        document.getElementById('menu-logout').addEventListener('click', () => {
            if (confirm('¿Estás seguro de que quieres cerrar sesión?')) {
                window.location.href = 'logout.php';
            }
        });
        
        // Funcionalidad de filtros colapsables
        document.getElementById('toggle-filtros').addEventListener('click', () => {
            const contenedor = document.getElementById('contenedor-filtros');
            const chevron = document.getElementById('chevron-filtros');
            const isHidden = contenedor.classList.contains('hidden');
            
            if (isHidden) {
                // Mostrar filtros
                contenedor.classList.remove('hidden');
                setTimeout(() => {
                    contenedor.classList.add('filtros-expanded');
                    contenedor.classList.remove('filtros-collapsed');
                }, 10);
                chevron.classList.add('chevron-up');
            } else {
                // Ocultar filtros
                contenedor.classList.add('filtros-collapsed');
                contenedor.classList.remove('filtros-expanded');
                chevron.classList.remove('chevron-up');
                
                setTimeout(() => {
                    contenedor.classList.add('hidden');
                }, 300);
            }
        });
    </script>
    <script src="assets/js/cotizaciones.js"></script>
    <script src="assets/js/desktop-utils.js"></script>
    
    <!-- Debug temporal para verificar vistas -->
    <script>
        // Debug function para verificar el estado de las vistas
        function debugVistas() {
            // console.log('🔍 DEBUG: Verificando estado de vistas');
            
            const elementos = {
                'vista-tabla': document.getElementById('vista-tabla'),
                'vista-cards': document.getElementById('vista-cards'),
                'contenedor-tabla': document.getElementById('contenedor-tabla'),
                'contenedor-cards': document.getElementById('contenedor-cards'),
                'lista-cotizaciones-mobile': document.getElementById('lista-cotizaciones-mobile'),
                'lista-cotizaciones-cards': document.getElementById('lista-cotizaciones-cards')
            };
            
            Object.entries(elementos).forEach(([id, elemento]) => {
                if (elemento) {
                    // console.log(`✅ ${id}: Encontrado`);
                    // console.log(`   - Classes: ${elemento.className}`);
                    // console.log(`   - Visible: ${!elemento.classList.contains('hidden')}`);
                    if (id.includes('lista-cotizaciones')) {
                        // console.log(`   - Contenido: ${elemento.innerHTML.length > 0 ? 'Tiene contenido' : 'Vacío'}`);
                    }
                } else {
                    // console.log(`❌ ${id}: NO encontrado`);
                }
            });
            
            // Verificar que gestor esté inicializado
            if (window.gestorCotizaciones) {
                // console.log(`🎯 Gestor inicializado. Vista actual: ${window.gestorCotizaciones.vistaActual}`);
            } else {
                // console.log('❌ Gestor NO inicializado');
            }
        }
        
        // Ejecutar debug después de que todo esté cargado
        setTimeout(debugVistas, 1000);
        
        // También permitir ejecutarlo manualmente desde consola
        window.debugVistas = debugVistas;
        
        // Interceptar cambios de vista para debug
        function interceptarCambioVista() {
            const btnTabla = document.getElementById('vista-tabla');
            const btnCards = document.getElementById('vista-cards');
            const btnTablaDesktop = document.getElementById('vista-tabla-desktop');
            const btnCardsDesktop = document.getElementById('vista-cards-desktop');
            
            if (btnTabla) {
                btnTabla.addEventListener('click', function() {
                    // console.log('🔄 Click en vista-tabla');
                    setTimeout(debugVistas, 100);
                });
            }
            
            if (btnCards) {
                btnCards.addEventListener('click', function() {
                    // console.log('🔄 Click en vista-cards');
                    setTimeout(debugVistas, 100);
                });
            }
            
            // Desktop view toggles
            if (btnTablaDesktop) {
                btnTablaDesktop.addEventListener('click', function() {
                    // Sync with mobile toggle and call gestor function
                    if (window.gestorCotizaciones && window.gestorCotizaciones.cambiarVista) {
                        window.gestorCotizaciones.cambiarVista('tabla');
                    }
                    setTimeout(debugVistas, 100);
                });
            }
            
            if (btnCardsDesktop) {
                btnCardsDesktop.addEventListener('click', function() {
                    // Sync with mobile toggle and call gestor function
                    if (window.gestorCotizaciones && window.gestorCotizaciones.cambiarVista) {
                        window.gestorCotizaciones.cambiarVista('cards');
                    }
                    setTimeout(debugVistas, 100);
                });
            }
        }
        
        // Inicializar debug cuando esté listo
        document.addEventListener('DOMContentLoaded', function() {
            // console.log('🚀 Página cargada, configurando debug...');
            interceptarCambioVista();
        });
    </script>
</body>
</html>