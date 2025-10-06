<?php
/**
 * Configuración de Email/SMTP desde Base de Datos
 * Las configuraciones ahora se obtienen desde la tabla email_config
 */

require_once __DIR__ . '/../includes/email_config_db.php';

try {
    // Cargar configuraciones desde la base de datos
    defineEmailConstants();

    // Mensaje de éxito en log para debug
    error_log("Configuraciones de email cargadas exitosamente desde la base de datos");

} catch (Exception $e) {
    // Error crítico: no se pueden cargar las configuraciones
    error_log("ERROR CRÍTICO: No se pueden cargar las configuraciones de email desde la base de datos: " . $e->getMessage());

    // Mostrar error al usuario
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'error',
            'message' => 'Error de configuración del sistema de email. Contacta al administrador.',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        exit;
    } else {
        // Si ya se enviaron headers, mostrar error simple
        echo '<div style="color: red; font-weight: bold; padding: 20px; background: #ffe6e6; border: 1px solid #ff0000; margin: 10px;">';
        echo 'Error de configuración del sistema de email. Contacta al administrador.';
        echo '</div>';
        exit;
    }
}

/**
 * Funciones helper para obtener configuraciones específicas
 */
function getSmtpConfig() {
    return [
        'host' => SMTP_HOST,
        'port' => SMTP_PORT,
        'username' => SMTP_USERNAME,
        'password' => SMTP_PASSWORD,
        'encryption' => SMTP_ENCRYPTION
    ];
}

function getEmailSenderConfig() {
    return [
        'from_address' => EMAIL_FROM_ADDRESS,
        'from_name' => EMAIL_FROM_NAME,
        'reply_to' => EMAIL_REPLY_TO,
        'reply_name' => EMAIL_REPLY_NAME
    ];
}

function getCompanyConfig() {
    return [
        'name' => COMPANY_NAME,
        'phone' => COMPANY_PHONE,
        'email' => COMPANY_EMAIL,
        'whatsapp' => COMPANY_WHATSAPP
    ];
}

/**
 * Verificar que todas las configuraciones críticas estén definidas
 */
function validateEmailConfig() {
    $required = [
        'SMTP_HOST', 'SMTP_PORT', 'SMTP_USERNAME', 'SMTP_PASSWORD', 'SMTP_ENCRYPTION',
        'EMAIL_FROM_ADDRESS', 'EMAIL_FROM_NAME', 'EMAIL_REPLY_TO', 'EMAIL_REPLY_NAME',
        'COMPANY_NAME', 'COMPANY_PHONE', 'COMPANY_EMAIL', 'COMPANY_WHATSAPP',
        'EMAIL_ENABLED'
    ];

    $missing = [];
    foreach ($required as $const) {
        if (!defined($const)) {
            $missing[] = $const;
        }
    }

    if (!empty($missing)) {
        throw new Exception("Faltan configuraciones requeridas en la base de datos: " . implode(', ', $missing));
    }

    return true;
}

// Validar configuraciones al cargar el archivo
try {
    validateEmailConfig();
} catch (Exception $e) {
    error_log("ERROR: Configuraciones de email incompletas: " . $e->getMessage());
    throw $e;
}

/**
 * INSTRUCCIONES PARA ADMINISTRAR CONFIGURACIONES:
 *
 * Las configuraciones ahora se almacenan en la tabla 'email_config' de la base de datos.
 *
 * Para modificar configuraciones, puedes:
 *
 * 1. Usar phpMyAdmin o tu cliente MySQL favorito para editar la tabla directamente
 * 2. Usar las funciones PHP:
 *    - getEmailConfig('SMTP_HOST') para obtener una configuración
 *    - updateEmailConfig('SMTP_HOST', 'nuevo.servidor.com') para actualizar
 *    - getAllEmailConfigs() para obtener todas las configuraciones
 *
 * 3. Crear un panel de administración web (recomendado para el futuro)
 *
 * CONFIGURACIONES DISPONIBLES:
 * - SMTP_HOST: Servidor SMTP
 * - SMTP_PORT: Puerto SMTP (587 para TLS, 465 para SSL)
 * - SMTP_USERNAME: Usuario para autenticación
 * - SMTP_PASSWORD: Contraseña de aplicación
 * - SMTP_ENCRYPTION: Tipo de encriptación (tls/ssl)
 * - EMAIL_FROM_ADDRESS: Email del remitente
 * - EMAIL_FROM_NAME: Nombre del remitente
 * - EMAIL_REPLY_TO: Email para respuestas
 * - EMAIL_REPLY_NAME: Nombre para respuestas
 * - COMPANY_NAME: Nombre de la empresa
 * - COMPANY_PHONE: Teléfono de contacto
 * - COMPANY_EMAIL: Email principal
 * - COMPANY_WHATSAPP: WhatsApp de contacto
 * - EMAIL_ENABLED: true/false para habilitar/deshabilitar emails
 */
?>