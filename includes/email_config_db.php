<?php
/**
 * Funciones para manejar configuración de email desde base de datos
 */

require_once __DIR__ . '/functions.php';

class EmailConfigDB {
    private $conn;
    private static $instance = null;
    private $configCache = [];

    private function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new EmailConfigDB();
        }
        return self::$instance;
    }

    /**
     * Obtener una configuración específica
     */
    public function getConfig($key, $default = null) {
        // Verificar cache primero
        if (isset($this->configCache[$key])) {
            return $this->configCache[$key];
        }

        try {
            $stmt = $this->conn->prepare("SELECT config_value, config_type FROM email_config WHERE config_key = ?");
            $stmt->execute([$key]);
            $result = $stmt->fetch();

            if ($result) {
                $value = $this->convertValue($result['config_value'], $result['config_type']);
                // Guardar en cache
                $this->configCache[$key] = $value;
                return $value;
            }

            return $default;
        } catch (PDOException $e) {
            error_log("Error obteniendo configuración email $key: " . $e->getMessage());
            throw new Exception("Error al acceder a la configuración de email");
        }
    }

    /**
     * Obtener todas las configuraciones
     */
    public function getAllConfigs() {
        try {
            $stmt = $this->conn->query("SELECT config_key, config_value, config_type FROM email_config");
            $configs = [];

            while ($row = $stmt->fetch()) {
                $configs[$row['config_key']] = $this->convertValue($row['config_value'], $row['config_type']);
            }

            // Guardar en cache
            $this->configCache = array_merge($this->configCache, $configs);
            return $configs;
        } catch (PDOException $e) {
            error_log("Error obteniendo todas las configuraciones email: " . $e->getMessage());
            throw new Exception("Error al acceder a la configuración de email");
        }
    }

    /**
     * Actualizar una configuración
     */
    public function updateConfig($key, $value) {
        try {
            $stmt = $this->conn->prepare("UPDATE email_config SET config_value = ?, updated_at = CURRENT_TIMESTAMP WHERE config_key = ?");
            $success = $stmt->execute([$value, $key]);

            if ($success) {
                // Limpiar cache para esta clave
                unset($this->configCache[$key]);
                return true;
            }

            return false;
        } catch (PDOException $e) {
            error_log("Error actualizando configuración email $key: " . $e->getMessage());
            throw new Exception("Error al actualizar la configuración de email");
        }
    }

    /**
     * Convertir valor según su tipo
     */
    private function convertValue($value, $type) {
        switch ($type) {
            case 'boolean':
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
            case 'integer':
                return (int) $value;
            case 'string':
            default:
                return $value;
        }
    }

    /**
     * Verificar si la tabla de configuración existe
     */
    public function tableExists() {
        try {
            $stmt = $this->conn->query("SHOW TABLES LIKE 'email_config'");
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            return false;
        }
    }
}

/**
 * Funciones helper para compatibilidad con el código existente
 */
function getEmailConfig($key, $default = null) {
    try {
        $configDB = EmailConfigDB::getInstance();
        return $configDB->getConfig($key, $default);
    } catch (Exception $e) {
        error_log("Error en getEmailConfig: " . $e->getMessage());
        throw $e;
    }
}

function getAllEmailConfigs() {
    try {
        $configDB = EmailConfigDB::getInstance();
        return $configDB->getAllConfigs();
    } catch (Exception $e) {
        error_log("Error en getAllEmailConfigs: " . $e->getMessage());
        throw $e;
    }
}

function updateEmailConfig($key, $value) {
    try {
        $configDB = EmailConfigDB::getInstance();
        return $configDB->updateConfig($key, $value);
    } catch (Exception $e) {
        error_log("Error en updateEmailConfig: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Definir constantes dinámicamente desde la base de datos
 */
function defineEmailConstants() {
    try {
        $configDB = EmailConfigDB::getInstance();

        // Verificar que la tabla existe
        if (!$configDB->tableExists()) {
            throw new Exception("La tabla email_config no existe en la base de datos");
        }

        $configs = $configDB->getAllConfigs();

        // Definir todas las constantes
        foreach ($configs as $key => $value) {
            if (!defined($key)) {
                define($key, $value);
            }
        }

        return true;
    } catch (Exception $e) {
        error_log("Error definiendo constantes de email: " . $e->getMessage());
        throw $e;
    }
}
?>