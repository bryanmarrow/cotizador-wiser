<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error de Acceso - WISER</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#1f2937',
                        secondary: '#6b7280',
                        accent: '#3b82f6',
                        error: '#ef4444'
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center">
    <div class="max-w-md mx-auto text-center p-8">
        <div class="bg-white rounded-lg shadow-lg p-8">
            <!-- Icono de Error -->
            <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-red-100 mb-6">
                <svg class="h-8 w-8 text-error" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                          d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z">
                    </path>
                </svg>
            </div>
            
            <!-- Título -->
            <h1 class="text-2xl font-bold text-gray-900 mb-4">
                Acceso no disponible
            </h1>
            
            <!-- Mensaje de Error -->
            <p class="text-gray-600 mb-6">
                <?= isset($error) ? htmlspecialchars($error) : 'El enlace al que intentas acceder no está disponible.' ?>
            </p>
            
            <!-- Información Adicional -->
            <div class="bg-gray-50 rounded-lg p-4 mb-6 text-left">
                <h3 class="font-medium text-gray-900 mb-2">Posibles causas:</h3>
                <ul class="text-sm text-gray-600 space-y-1">
                    <li>• El enlace ha expirado (válido por 24 horas)</li>
                    <li>• El enlace ha sido deshabilitado</li>
                    <li>• El folio no es válido</li>
                    <li>• La cotización no está disponible</li>
                </ul>
            </div>
            
            <!-- Acciones -->
            <div class="space-y-3">
                <p class="text-sm text-gray-600">
                    Si crees que esto es un error, contacta a tu vendedor o al equipo de WISER.
                </p>
                
                <div class="flex flex-col sm:flex-row gap-3 justify-center">
                    <button onclick="window.history.back()" 
                            class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors">
                        Volver
                    </button>
                    
                    <a href="mailto:soporte@wiser.com.mx?subject=Error de acceso a cotización" 
                       class="px-4 py-2 bg-accent text-white rounded-lg hover:bg-blue-600 transition-colors">
                        Contactar Soporte
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="mt-8">
            <img src="assets/img/logo_wiser_web.webp" alt="WISER" class="h-8 w-auto mx-auto mb-2">
            <p class="text-xs text-gray-500">
                &copy; <?= date('Y') ?> WISER. Todos los derechos reservados.
            </p>
        </div>
    </div>
</body>
</html>