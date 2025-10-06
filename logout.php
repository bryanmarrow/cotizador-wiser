<?php
require_once 'includes/auth.php';

// Destruir sesión
destroyUserSession();

// Redirigir al login
header('Location: login.php');
exit;
?>