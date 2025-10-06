<?php
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Si ya está logueado, redirigir al cotizador
if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';

// Procesar formulario de login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action'])) {
            if ($_POST['action'] === 'login') {
                $username = trim($_POST['username'] ?? '');
                $password = $_POST['password'] ?? '';
                
                if (empty($username) || empty($password)) {
                    $error = 'Por favor, completa todos los campos.';
                } else {
                    $user = authenticateUser($username, $password);
                    if ($user) {
                        createUserSession($user);
                        header('Location: index.php');
                        exit;
                    } else {
                        $error = 'Usuario o contraseña incorrectos.';
                    }
                }
            } elseif ($_POST['action'] === 'forgot') {
                $email = trim($_POST['email'] ?? '');
                if (empty($email)) {
                    $error = 'Por favor, ingresa tu email.';
                } else {
                    if (requestPasswordReset($email)) {
                        $success = 'Se ha enviado un enlace de recuperación a tu email.';
                    } else {
                        $error = 'Email no encontrado en el sistema.';
                    }
                }
            }
        }
    } catch (Exception $e) {
        $error = 'Error del sistema. Inténtalo más tarde.';
        error_log("Error en login: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - WISER Cotizador</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#1f2937',
                        secondary: '#6b7280',
                        accent: '#3b82f6',
                        wiser: '#4A90E2'
                    }
                }
            }
        }
    </script>
    <style>
        .loader {
            border: 3px solid #f3f4f6;
            border-top: 3px solid #4A90E2;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center">
    <!-- Loader Global -->
    <div id="global-loader" class="fixed inset-0 bg-white bg-opacity-90 z-50 hidden items-center justify-center">
        <div class="text-center">
            <img src="assets/img/logo_wiser_web.webp" alt="WISER" class="w-32 h-auto mx-auto mb-4">
            <div class="loader mx-auto"></div>
            <p class="mt-4 text-gray-600">Cargando...</p>
        </div>
    </div>

    <div class="w-full max-w-md">
        <!-- Logo Principal -->
        <div class="text-center mb-8 fade-in">
            <img src="assets/img/logo_wiser_web.webp" alt="WISER" class="w-40 h-auto mx-auto mb-4">
            <h1 class="text-2xl font-bold text-gray-900">Iniciar sesión</h1>
            <!-- <p class="text-gray-600 text-sm mt-2">Accede a tu cuenta para continuar</p> -->
        </div>

        <!-- Formulario de Login -->
        <div id="login-form" class="bg-white rounded-lg shadow-lg p-8 fade-in">
            <!-- Mensajes -->
            <?php if ($error): ?>
            <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-red-700 text-sm">
                <?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
            <div class="mb-4 p-3 bg-green-50 border border-green-200 rounded-lg text-green-700 text-sm">
                <?php echo htmlspecialchars($success); ?>
            </div>
            <?php endif; ?>

            <form method="POST" id="loginForm">
                <input type="hidden" name="action" value="login">
                
                <div class="space-y-4">
                    <div>
                        <label for="username" class="block text-sm font-medium text-gray-700 mb-2">Usuario</label>
                        <input type="text" id="username" name="username" required
                               class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-wiser focus:border-transparent transition-all"
                               placeholder="Ingresa tu usuario">
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-2">Contraseña</label>
                        <input type="password" id="password" name="password" required
                               class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-wiser focus:border-transparent transition-all"
                               placeholder="Ingresa tu contraseña">
                    </div>

                    <button type="submit" id="loginBtn" 
                            class="w-full bg-wiser text-white py-3 px-4 rounded-lg font-medium hover:bg-blue-600 focus:ring-2 focus:ring-wiser focus:ring-offset-2 transition-all flex items-center justify-center">
                        <span id="loginText">Iniciar Sesión</span>
                        <div id="loginLoader" class="loader ml-2 hidden"></div>
                    </button>
                </div>
            </form>

            <!-- Enlace Forgot Password -->
            <!-- <div class="mt-6 text-center">
                <button id="forgotPasswordBtn" class="text-sm text-wiser hover:text-blue-600 transition-colors">
                    ¿Olvidaste tu contraseña?
                </button>
            </div> -->
        </div>

        <!-- Formulario de Recuperación -->
        <div id="forgot-form" class="bg-white rounded-lg shadow-lg p-8 fade-in hidden">
            <div class="text-center mb-6">
                <h2 class="text-xl font-semibold text-gray-900">Recuperar Contraseña</h2>
                <p class="text-gray-600 text-sm mt-2">Ingresa tu email para recibir un enlace de recuperación</p>
            </div>

            <form method="POST" id="forgotForm">
                <input type="hidden" name="action" value="forgot">
                
                <div class="space-y-4">
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                        <input type="email" id="email" name="email" required
                               class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-wiser focus:border-transparent transition-all"
                               placeholder="tu@email.com">
                    </div>

                    <button type="submit" id="forgotBtn"
                            class="w-full bg-wiser text-white py-3 px-4 rounded-lg font-medium hover:bg-blue-600 transition-all flex items-center justify-center">
                        <span id="forgotText">Enviar Enlace</span>
                        <div id="forgotLoader" class="loader ml-2 hidden"></div>
                    </button>
                </div>
            </form>

            <div class="mt-6 text-center">
                <button id="backToLoginBtn" class="text-sm text-gray-600 hover:text-gray-800 transition-colors">
                    ← Volver al login
                </button>
            </div>
        </div>

        <!-- Footer -->
        <div class="text-center mt-8 text-sm text-gray-500">
            <p>&copy; 2025 WISER. Todos los derechos reservados.</p>
        </div>
    </div>

    <script>
        // Funciones del loader
        function showGlobalLoader() {
            document.getElementById('global-loader').classList.remove('hidden');
            document.getElementById('global-loader').classList.add('flex');
        }

        function hideGlobalLoader() {
            document.getElementById('global-loader').classList.add('hidden');
            document.getElementById('global-loader').classList.remove('flex');
        }

        // Alternar entre formularios
        document.getElementById('forgotPasswordBtn').addEventListener('click', function() {
            document.getElementById('login-form').classList.add('hidden');
            document.getElementById('forgot-form').classList.remove('hidden');
        });

        document.getElementById('backToLoginBtn').addEventListener('click', function() {
            document.getElementById('forgot-form').classList.add('hidden');
            document.getElementById('login-form').classList.remove('hidden');
        });

        // Manejo del formulario de login
        document.getElementById('loginForm').addEventListener('submit', function() {
            const btn = document.getElementById('loginBtn');
            const text = document.getElementById('loginText');
            const loader = document.getElementById('loginLoader');
            
            btn.disabled = true;
            text.textContent = 'Iniciando...';
            loader.classList.remove('hidden');
        });

        // Manejo del formulario de recuperación
        document.getElementById('forgotForm').addEventListener('submit', function() {
            const btn = document.getElementById('forgotBtn');
            const text = document.getElementById('forgotText');
            const loader = document.getElementById('forgotLoader');
            
            btn.disabled = true;
            text.textContent = 'Enviando...';
            loader.classList.remove('hidden');
        });

        // Ocultar loader al cargar la página
        window.addEventListener('load', function() {
            hideGlobalLoader();
        });
    </script>
</body>
</html>