-- Estructura de tablas para el módulo de Prospectos y CRM

CREATE TABLE prospectos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(120) NOT NULL,
    empresa VARCHAR(150) DEFAULT NULL,
    email VARCHAR(150) NOT NULL,
    telefono VARCHAR(25) DEFAULT NULL,
    canal ENUM('Social', 'Email', 'Web', 'Referral', 'Otro') DEFAULT 'Otro',
    etapa ENUM('Nuevo', 'Contactado', 'En seguimiento', 'Completado', 'Perdido') DEFAULT 'Nuevo',
    responsable_id INT UNSIGNED DEFAULT NULL,
    fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    notas TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE responsables (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(120) NOT NULL,
    email VARCHAR(150) NOT NULL,
    telefono VARCHAR(25) DEFAULT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE actividades_prospecto (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    prospecto_id INT UNSIGNED NOT NULL,
    tipo ENUM('Llamada', 'Correo', 'Reunión', 'Nota', 'Seguimiento') NOT NULL,
    descripcion TEXT NOT NULL,
    fecha_programada DATETIME DEFAULT NULL,
    completada TINYINT(1) NOT NULL DEFAULT 0,
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (prospecto_id) REFERENCES prospectos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE conversiones (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    prospecto_id INT UNSIGNED NOT NULL,
    fecha_conversion DATETIME NOT NULL,
    valor_estimado DECIMAL(12,2) DEFAULT NULL,
    notas TEXT NULL,
    FOREIGN KEY (prospecto_id) REFERENCES prospectos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE prospectos
    ADD CONSTRAINT fk_prospectos_responsable
    FOREIGN KEY (responsable_id) REFERENCES responsables(id) ON DELETE SET NULL;
