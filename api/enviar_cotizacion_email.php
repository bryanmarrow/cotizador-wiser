<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');

session_start();
require_once '../vendor/autoload.php';
require_once '../config/database.php';
require_once '../config/email_config.php';
require_once '../includes/functions.php';
require_once '../includes/shared_links.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\OAuth;

// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    enviarRespuestaJson('error', 'No autorizado', null, 401);
}

// Verificar método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    enviarRespuestaJson('error', 'Método no permitido', null, 405);
}

// Inicializar conexión a la base de datos
$database = new Database();
$pdo = $database->getConnection();

// Obtener datos de entrada
$input = json_decode(file_get_contents('php://input'), true);

// Validar campos requeridos
$camposRequeridos = ['cotizacion_id'];
$camposFaltantes = validarCamposRequeridos($camposRequeridos, $input);
if (!empty($camposFaltantes)) {
    enviarRespuestaJson('error', 'Faltan campos requeridos: ' . implode(', ', $camposFaltantes), null, 400);
}

$cotizacionId = intval($input['cotizacion_id']);
$emailDestino = $input['email_destino'] ?? null;

try {
    // Verificar que la cotización existe y obtener datos
    $stmt = $pdo->prepare("
        SELECT ch.Id, ch.UserId, ch.NombreCliente, ch.Estado, ch.FechaCreacion,
               u.full_name as NombreUsuario, u.email as EmailUsuario, u.role as RolUsuario
        FROM Cotizacion_Header ch
        LEFT JOIN users u ON ch.UserId = u.id
        WHERE ch.Id = ?
    ");
    $stmt->execute([$cotizacionId]);
    $cotizacion = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$cotizacion) {
        enviarRespuestaJson('error', 'Cotización no encontrada', null, 404);
    }
    
    // Verificar permisos
    $esAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
    $esVendor = isset($_SESSION['role']) && $_SESSION['role'] === 'vendor';
    $esCliente = isset($_SESSION['role']) && $_SESSION['role'] === 'cliente';
    $esPropietario = $cotizacion['UserId'] == $_SESSION['user_id'];
    
    if (!$esAdmin && !$esVendor && !$esCliente && !$esPropietario) {
        enviarRespuestaJson('error', 'No tienes permisos para enviar esta cotización', null, 403);
    }
    
    // Determinar email de destino según el rol
    if ($esCliente) {
        // Para clientes, usar su propio email
        $emailDestino = $_SESSION['email'] ?? $cotizacion['EmailUsuario'];
        if (!$emailDestino) {
            enviarRespuestaJson('error', 'No se encontró email del cliente', null, 400);
        }
    } else {
        // Para admin/vendor, requiere email_destino
        if (!$emailDestino) {
            enviarRespuestaJson('error', 'Email de destino es requerido para admin/vendor', null, 400);
        }
        
        // Validar formato de email
        if (!filter_var($emailDestino, FILTER_VALIDATE_EMAIL)) {
            enviarRespuestaJson('error', 'Formato de email inválido', null, 400);
        }
    }
    
    // Verificar si existe un enlace compartido, si no, crearlo
    $stmt = $pdo->prepare("SELECT Folio FROM Enlaces_Compartidos_Log WHERE CotizacionId = ?");
    $stmt->execute([$cotizacionId]);
    $enlaceExistente = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$enlaceExistente) {
        $resultado = crearEnlaceCompartido($cotizacionId, false);
        if (!$resultado['success']) {
            enviarRespuestaJson('error', 'No se pudo generar enlace para envío', null, 500);
        }
        $folio = $resultado['folio'];
    } else {
        $folio = $enlaceExistente['Folio'];
    }
    
    // Generar URL pública
    $urlPublica = obtenerUrlPublica($folio);
    
    // Enviar email
    $resultadoEmail = enviarEmailCotizacion($emailDestino, $cotizacion, $urlPublica, $folio);
    
    if ($resultadoEmail['success']) {
        // Registrar envío en log (opcional - podríamos crear una tabla para esto)
        error_log("Email enviado - Cotización: {$cotizacionId}, Destino: {$emailDestino}, Usuario: {$_SESSION['user_id']}");
        
        enviarRespuestaJson('success', 'Email enviado exitosamente', [
            'email_destino' => $emailDestino,
            'folio' => $folio,
            'fecha_envio' => date('Y-m-d H:i:s')
        ]);
    } else {
        enviarRespuestaJson('error', 'Error al enviar email: ' . $resultadoEmail['message'], null, 500);
    }
    
} catch (Exception $e) {
    error_log("Error en enviar_cotizacion_email.php: " . $e->getMessage());
    enviarRespuestaJson('error', 'Error interno del servidor', null, 500);
}

function enviarEmailCotizacion($emailDestino, $cotizacion, $urlPublica, $folio) {
    // Verificar si el envío de emails está habilitado
    if (!EMAIL_ENABLED) {
        error_log("Email deshabilitado temporalmente - Cotización: {$cotizacion['Id']}, Destino: {$emailDestino}");
        return ['success' => true, 'message' => 'Email simulado (envío deshabilitado temporalmente)'];
    }

    $mail = new PHPMailer(true);

    try {
        // Configuración del servidor SMTP
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_ENCRYPTION === 'tls' ? PHPMailer::ENCRYPTION_STARTTLS : PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = SMTP_PORT;
        
        // Configuración del remitente y destinatario
        $mail->setFrom(EMAIL_FROM_ADDRESS, EMAIL_FROM_NAME);
        $mail->addAddress($emailDestino);
        $mail->addReplyTo(EMAIL_REPLY_TO, EMAIL_REPLY_NAME);
        
        // Contenido del email
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = "Cotización #{$cotizacion['Id']} - {$cotizacion['NombreCliente']}";
        
        // Template HTML del email
        $htmlContent = generarTemplateEmailCotizacion($cotizacion, $urlPublica, $folio);
        $mail->Body = $htmlContent;
        
        // Versión texto plano (fallback)
        $mail->AltBody = "
        Hola,
        
        Te enviamos tu cotización #{$cotizacion['Id']} para {$cotizacion['NombreCliente']}.
        
        Puedes ver los detalles en el siguiente enlace:
        {$urlPublica}
        
        Folio: {$folio}
        Válido por 24 horas.
        
        Saludos,
        Equipo " . COMPANY_NAME . "
        ";
        
        $mail->send();
        return ['success' => true, 'message' => 'Email enviado exitosamente'];
        
    } catch (Exception $e) {
        error_log("Error PHPMailer: {$mail->ErrorInfo}");
        return ['success' => false, 'message' => "Error al enviar email: {$mail->ErrorInfo}"];
    }
}

function generarTemplateEmailCotizacion($cotizacion, $urlPublica, $folio) {
    $fechaCreacion = date('d/m/Y', strtotime($cotizacion['FechaCreacion']));
    
    return "
    <!DOCTYPE html>
    <html lang='es'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Cotización WISER</title>
        <style>
            /* Reset y base */
            * { box-sizing: border-box; }
            body { 
                font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; 
                margin: 0; 
                padding: 0; 
                background-color: #f8fafc;
                line-height: 1.6;
            }
            
            .email-wrapper {
                padding: 40px 20px;
                background-color: #f8fafc;
            }
            
            .container { 
                max-width: 600px; 
                margin: 0 auto; 
                background: #ffffff;
                border-radius: 8px;
                overflow: hidden;
                box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
                border: 1px solid #e2e8f0;
            }
            
            /* Header limpio y profesional */
            .header { 
                background-color: #ffffff;
                padding: 48px 40px 32px 40px;
                text-align: center;
                border-bottom: 1px solid #e2e8f0;
            }
            
            .logo {
                max-width: 160px;
                height: auto;
                margin-bottom: 24px;
            }
            
            .header-title {
                color: #1e293b;
                font-size: 28px;
                font-weight: 600;
                margin: 0 0 8px 0;
                letter-spacing: -0.025em;
            }
            
            .header-subtitle {
                color: #64748b;
                font-size: 16px;
                margin: 0;
                font-weight: 400;
            }
            
            /* Contenido principal */
            .content { 
                padding: 40px;
            }
            
            .greeting {
                font-size: 18px;
                font-weight: 500;
                color: #1e293b;
                margin: 0 0 16px 0;
            }
            
            .description {
                font-size: 16px;
                line-height: 1.6;
                color: #475569;
                margin: 0 0 32px 0;
                font-weight: 400;
            }
            
            /* Card de información - Diseño ejecutivo */
            .info-card {
                border: 1px solid #e2e8f0;
                border-radius: 8px;
                overflow: hidden;
                margin: 32px 0;
                background: #ffffff;
            }
            
            .info-header {
                background-color: #f8fafc;
                padding: 20px 24px;
                border-bottom: 1px solid #e2e8f0;
            }
            
            .info-title {
                font-size: 16px;
                font-weight: 600;
                color: #1e293b;
                margin: 0;
            }
            
            .info-content {
                padding: 24px;
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 24px;
            }
            
            .info-item {
                display: flex;
                flex-direction: column;
                gap: 4px;
            }
            
            .info-label {
                font-size: 12px;
                font-weight: 500;
                color: #64748b;
                text-transform: uppercase;
                letter-spacing: 0.05em;
            }
            
            .info-value {
                font-size: 15px;
                font-weight: 500;
                color: #1e293b;
            }
            
            /* Folio - Diseño limpio */
            .folio-card {
                background-color: #2050e6;
                border-radius: 8px;
                padding: 32px 24px;
                text-align: center;
                margin: 32px 0;
                color: #ffffff;
            }
            
            .folio-label {
                font-size: 13px;
                opacity: 0.9;
                margin-bottom: 8px;
                font-weight: 500;
                text-transform: uppercase;
                letter-spacing: 0.05em;
            }
            
            .folio-value {
                font-size: 28px;
                font-weight: 700;
                letter-spacing: 2px;
                margin-bottom: 8px;
                font-family: 'Monaco', 'Menlo', monospace;
            }
            
            .folio-validity {
                font-size: 13px;
                opacity: 0.8;
                font-weight: 400;
            }
            
            /* Botón principal - Estilo ejecutivo */
            .btn-container { 
                text-align: center; 
                margin: 40px 0;
            }
            
            .btn-primary { 
                display: inline-flex;
                align-items: center;
                gap: 8px;
                background-color: #2050e6;
                color: #ffffff;
                padding: 14px 32px;
                text-decoration: none;
                border-radius: 6px;
                font-weight: 500;
                font-size: 15px;
                letter-spacing: 0.025em;
                transition: all 0.2s ease;
                box-shadow: 0 1px 3px 0 rgba(32, 80, 230, 0.12);
            }
            
            .btn-primary:hover {
                background-color: #1a45cc;
                transform: translateY(-1px);
                box-shadow: 0 4px 12px 0 rgba(32, 80, 230, 0.15);
            }

            /* Botón WhatsApp */
            .btn-whatsapp {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                background-color: #25d366;
                color: #ffffff;
                padding: 12px 24px;
                text-decoration: none;
                border-radius: 6px;
                font-weight: 500;
                font-size: 14px;
                letter-spacing: 0.025em;
                transition: all 0.2s ease;
                box-shadow: 0 1px 3px 0 rgba(37, 211, 102, 0.12);
                margin-top: 16px;
            }

            .btn-whatsapp:hover {
                background-color: #20ba5a;
                transform: translateY(-1px);
                box-shadow: 0 4px 12px 0 rgba(37, 211, 102, 0.25);
            }
            
            /* Advertencia - Estilo limpio */
            .notice {
                background-color: #fef3c7;
                border: 1px solid #fbbf24;
                border-radius: 6px;
                padding: 16px 20px;
                margin: 32px 0;
            }
            
            .notice-content {
                font-size: 14px;
                color: #92400e;
                margin: 0;
                line-height: 1.5;
            }
            
            .notice-title {
                font-weight: 600;
                color: #78350f;
            }
            
            /* Divider */
            .divider {
                height: 1px;
                background-color: #e2e8f0;
                margin: 32px 0;
            }
            
            /* Footer ejecutivo */
            .footer {
                background-color: #f8fafc;
                padding: 32px 40px;
                text-align: center;
                border-top: 1px solid #e2e8f0;
            }
            
            .footer-brand {
                font-size: 18px;
                font-weight: 600;
                color: #1e293b;
                margin: 0 0 8px 0;
            }
            
            .footer-contact {
                font-size: 14px;
                color: #64748b;
                margin: 0 0 16px 0;
                font-weight: 400;
            }
            
            .footer-address {
                font-size: 13px;
                color: #64748b;
                margin: 0 0 16px 0;
                font-weight: 400;
                line-height: 1.4;
            }
            
            .footer-disclaimer {
                font-size: 12px;
                color: #94a3b8;
                margin: 0;
                line-height: 1.4;
                border-top: 1px solid #e2e8f0;
                padding-top: 16px;
            }
            
            /* Responsive */
            @media only screen and (max-width: 600px) {
                .email-wrapper { padding: 20px 16px; }
                .header { padding: 32px 24px 24px 24px; }
                .content { padding: 24px; }
                .footer { padding: 24px; }
                .info-content { grid-template-columns: 1fr; gap: 16px; }
                .folio-value { font-size: 24px; letter-spacing: 1.5px; }
                .btn-primary { padding: 12px 24px; font-size: 14px; }
                .header-title { font-size: 24px; }
            }
            
            /* Modo oscuro */
            @media (prefers-color-scheme: dark) {
                .email-wrapper { background-color: #f8fafc; }
                .container { background: #ffffff; }
                .header { background-color: #ffffff; }
                .info-header { background-color: #f8fafc; }
                .footer { background-color: #f8fafc; }
            }
        </style>
    </head>
    <body>
        <div class='email-wrapper'>
            <div class='container'>
                <!-- Header ejecutivo -->
                <div class='header'>
                    <img src='https://cdn.wiserarrendadora.com.mx/logo_wiser_web.jpg' 
                         alt='WISER' class='logo'>
                    <h1 class='header-title'>COTIZACIÓN</h1>
                    <p class='header-subtitle'>Accede a tu cotización personalizada</p>
                </div>
                
                <!-- Contenido principal -->
                <div class='content'>
                    <h2 class='greeting'>Estimado(a) Cliente,</h2>
                    <p class='description'>
                        Su cotización ha sido procesada exitosamente. Puede acceder a ella 
                        utilizando el enlace de acceso seguro que se proporciona a continuación.
                    </p>
                    
                    <!-- Folio de acceso -->
                    <div class='folio-card'>
                        <div class='folio-label'>Folio de cotización</div>
                        <div class='folio-value'>{$folio}</div>
                        <div class='folio-validity'>Enlace válido por 24 horas</div>
                    </div>
                    
                    <!-- Botón de acceso -->
                    <div class='btn-container'>
                        <a href='{$urlPublica}' class='btn-primary' style='color: #ffffff; text-decoration: none;'>
                            <svg style='width: 16px; height: 16px; fill: currentColor;' viewBox='0 0 24 24'>
                                <path d='M15 12a3 3 0 11-6 0 3 3 0 016 0z'></path>
                                <path fill-rule='evenodd' d='M1.323 11.447C2.811 6.976 7.028 3.75 12.001 3.75c4.97 0 9.185 3.223 10.675 7.69.12.362.12.752 0 1.113-1.487 4.471-5.705 7.697-10.677 7.697-4.97 0-9.186-3.223-10.675-7.69a1.762 1.762 0 010-1.113zM17.25 12a5.25 5.25 0 11-10.5 0 5.25 5.25 0 0110.5 0z' clip-rule='evenodd'></path>
                            </svg>
                            Acceder a Cotización
                        </a>
                        <br>
                        <a href='https://wa.me/5212211042874?text=Hola,%20me%20interesa%20obtener%20más%20información%20sobre%20la%20cotización%20{$folio}' class='btn-whatsapp' style='color: #ffffff; text-decoration: none;'>
                            Contáctanos por WhatsApp
                        </a>
                    </div>
                    
                    <!-- Aviso importante -->
                    <div class='notice'>
                        <p class='notice-content'>
                            <span class='notice-title'>Aviso:</span> El enlace de acceso tiene una validez de 24 horas 
                            por motivos de seguridad. Si requiere acceso posterior a este período, 
                            solicite un nuevo enlace de acceso.
                        </p>
                    </div>
                </div>
                
                <!-- Footer corporativo -->
                <div class='footer'>
                    <h4 class='footer-brand'>" . COMPANY_NAME . "</h4>
                    <p class='footer-contact'>
                        " . COMPANY_EMAIL . "
                    </p>
                    <p class='footer-address'>
                        www.wiserarrendadora.com.mx
                    </p>
                    <p class='footer-disclaimer'>
                        Este mensaje fue generado automáticamente. Por favor, no responda a este correo electrónico.<br>
                        Confidencial: Este mensaje y cualquier archivo adjunto están destinados únicamente para el uso de la persona o entidad a la que se dirige.
                    </p>
                </div>
            </div>
        </div>
    </body>
    </html>
    ";
}
?>