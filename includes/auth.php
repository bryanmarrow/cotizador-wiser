<?php
require_once __DIR__ . '/functions.php';

// Configuración de sesión segura
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0); // Cambiar a 1 en HTTPS

// Tiempo de expiración de sesión (24 horas)
define('SESSION_LIFETIME', 24 * 60 * 60);

/**
 * Autenticar usuario con username/email y contraseña
 */
function authenticateUser($username, $password) {
    try {
        $conn = obtenerConexionBaseDatos();
        
        // Buscar por username o email
        $sql = "SELECT * FROM users WHERE (username = ? OR email = ?) AND active = 1";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password'])) {
            // Actualizar último login
            $updateSql = "UPDATE users SET last_login = NOW() WHERE id = ?";
            $updateStmt = $conn->prepare($updateSql);
            $updateStmt->execute([$user['id']]);
            
            return $user;
        }
        
        return false;
    } catch (Exception $e) {
        error_log("Error en autenticación: " . $e->getMessage());
        return false;
    }
}

/**
 * Crear sesión de usuario
 */
function createUserSession($user) {
    iniciarSesion();
    
    // Regenerar ID de sesión por seguridad
    session_regenerate_id(true);
    
    // Guardar datos en sesión
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['login_time'] = time();
    $_SESSION['expires'] = time() + SESSION_LIFETIME;
    
    // Guardar sesión en base de datos
    try {
        $conn = obtenerConexionBaseDatos();
        $sessionId = session_id();
        $expires = date('Y-m-d H:i:s', $_SESSION['expires']);
        $data = json_encode($_SESSION);
        
        $sql = "INSERT INTO user_sessions (id, user_id, expires, data) VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE expires = VALUES(expires), data = VALUES(data)";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$sessionId, $user['id'], $expires, $data]);
    } catch (Exception $e) {
        error_log("Error guardando sesión: " . $e->getMessage());
    }
}

/**
 * Verificar si el usuario está logueado
 */
function isLoggedIn() {
    iniciarSesion();
    
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['expires'])) {
        return false;
    }
    
    // Verificar si la sesión ha expirado
    if (time() > $_SESSION['expires']) {
        destroyUserSession();
        return false;
    }
    
    // Extender sesión si está cerca de expirar (renovar cada hora)
    if (($_SESSION['expires'] - time()) < (SESSION_LIFETIME - 3600)) {
        $_SESSION['expires'] = time() + SESSION_LIFETIME;
        updateSessionInDatabase();
    }
    
    return true;
}

/**
 * Obtener datos del usuario actual
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    return [
        'id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'full_name' => $_SESSION['full_name'],
        'email' => $_SESSION['email'],
        'role' => $_SESSION['role'],
        'login_time' => $_SESSION['login_time']
    ];
}

/**
 * Verificar si el usuario tiene un rol específico
 */
function hasRole($role) {
    $user = getCurrentUser();
    return $user && $user['role'] === $role;
}


/**
 * Destruir sesión de usuario
 */
function destroyUserSession() {
    iniciarSesion();
    
    // Eliminar de base de datos
    if (isset($_SESSION['user_id'])) {
        try {
            $conn = obtenerConexionBaseDatos();
            $sessionId = session_id();
            $sql = "DELETE FROM user_sessions WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$sessionId]);
        } catch (Exception $e) {
            error_log("Error eliminando sesión: " . $e->getMessage());
        }
    }
    
    // Limpiar variables de sesión
    $_SESSION = array();
    
    // Eliminar cookie de sesión
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Destruir sesión
    session_destroy();
}

/**
 * Solicitar recuperación de contraseña
 */
function requestPasswordReset($email) {
    try {
        $conn = obtenerConexionBaseDatos();
        
        // Verificar si el email existe
        $sql = "SELECT id FROM users WHERE email = ? AND active = 1";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if (!$user) {
            return false;
        }
        
        // Generar token único
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', time() + 3600); // 1 hora
        
        // Guardar token en base de datos
        $updateSql = "UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->execute([$token, $expires, $user['id']]);
        
        // Aquí se enviaría el email (por ahora solo simulamos)
        // sendPasswordResetEmail($email, $token);
        
        return true;
    } catch (Exception $e) {
        error_log("Error en recuperación de contraseña: " . $e->getMessage());
        return false;
    }
}

/**
 * Actualizar sesión en base de datos
 */
function updateSessionInDatabase() {
    try {
        $conn = obtenerConexionBaseDatos();
        $sessionId = session_id();
        $expires = date('Y-m-d H:i:s', $_SESSION['expires']);
        $data = json_encode($_SESSION);
        
        $sql = "UPDATE user_sessions SET expires = ?, data = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$expires, $data, $sessionId]);
    } catch (Exception $e) {
        error_log("Error actualizando sesión: " . $e->getMessage());
    }
}

/**
 * Limpiar sesiones expiradas (ejecutar periódicamente)
 */
function cleanExpiredSessions() {
    try {
        $conn = obtenerConexionBaseDatos();
        $sql = "DELETE FROM user_sessions WHERE expires < NOW()";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
    } catch (Exception $e) {
        error_log("Error limpiando sesiones: " . $e->getMessage());
    }
}

/**
 * Requiere login - redirige si no está autenticado
 */
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Requiere rol específico
 */
function requireRole($role) {
    requireLogin();
    if (!hasRole($role)) {
        header('HTTP/1.1 403 Forbidden');
        die('Acceso denegado');
    }
}

// Limpiar sesiones expiradas automáticamente (1% de probabilidad)
if (random_int(1, 100) === 1) {
    cleanExpiredSessions();
}
?>