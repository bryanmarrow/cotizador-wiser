<?php
// Application constants
define('APP_NAME', 'Cotizador Wiser');
define('APP_VERSION', '1.0.0');

// Database constants
define('DB_HOST', 'localhost');
define('DB_NAME', 'cotizador_wiser');
define('DB_USER', 'root');
define('DB_PASS', '');

// Application settings
define('DEFAULT_RESIDUAL', 20);
define('DEFAULT_CURRENCY', 'MXN');
define('AUTO_SAVE_INTERVAL', 30000); // 30 seconds in milliseconds

// API Response constants
define('API_SUCCESS', 'success');
define('API_ERROR', 'error');
define('API_WARNING', 'warning');

// Quote states
define('STATE_DRAFT', 'borrador');
define('STATE_COMPLETED', 'completada');
define('STATE_PRINTED', 'impresa');

// Validation rules
define('MIN_COST', 1);
define('MAX_COST', 99999999);
define('MIN_QUANTITY', 1);
define('MAX_QUANTITY', 999);
define('MIN_TERM', 12);
define('MAX_TERM', 84);

// Equipment insurance rates (as fallback)
$EQUIPMENT_INSURANCE = [
    'Portacontenedor' => 0.0075,
    'Plataforma' => 0.006,
    'Plataforma HQ' => 0.006,
    'Caja seca' => 0.006,
    'Dollie' => 0.007,
    'Lowboy' => 0.008,
    'Góndola' => 0.007,
    'Otro' => 0.006
];

// Client types and rates (as fallback)
$CLIENT_TYPES = [
    'AAA' => ['label' => 'AAA - Excelente', 'rate' => 2.0],
    'AA' => ['label' => 'AA - Muy Bueno', 'rate' => 2.5],
    'A' => ['label' => 'A - Bueno', 'rate' => 3.0]
];
?>