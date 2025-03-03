use hub_providencia
show tables
CREATE TABLE datos_personales (
    Fecha DATETIME,
    Rut VARCHAR(12),
    Nombre VARCHAR(50),
    Correo VARCHAR(100),
    Comuna VARCHAR(50),
    Celular VARCHAR(20),
    Profesion VARCHAR(50),
    Genero VARCHAR(20)
);
CREATE TABLE administradores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rut VARCHAR(12) NOT NULL UNIQUE,
    nombre VARCHAR(100) NOT NULL,
    correo VARCHAR(100) NOT NULL,
    telefono VARCHAR(20),
    password VARCHAR(255),
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    ultimo_acceso DATETIME,
    INDEX (rut)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabla espejo
CREATE TABLE datos_personales_unicos (
    Rut VARCHAR(12) PRIMARY KEY,
    Nombre VARCHAR(50),
    Correo VARCHAR(100),
    Comuna VARCHAR(50),
    Celular VARCHAR(20),
    Profesion VARCHAR(50),
    Genero VARCHAR(20),
    Primera_Fecha DATETIME,
    Ultima_Fecha DATETIME,
    Cantidad_Ingresos INT DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE registro_accesos_usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rut_usuario VARCHAR(12) NOT NULL,
    fecha_acceso DATETIME DEFAULT CURRENT_TIMESTAMP,
    sesion_activa TINYINT(1) DEFAULT 1,
    INDEX (rut_usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Crear tabla para registrar las cargas de CSV
CREATE TABLE cargas_csv (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_admin INT NOT NULL,
    nombre_archivo VARCHAR(255) NOT NULL,
    registros_importados INT DEFAULT 0,
    fecha_carga DATETIME DEFAULT CURRENT_TIMESTAMP,
    ip VARCHAR(45),
    FOREIGN KEY (id_admin) REFERENCES administradores(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
ALTER TABLE registro_accesos_usuarios 
ADD COLUMN razon_visita VARCHAR(100) AFTER sesion_activa;