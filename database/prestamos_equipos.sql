-- =====================================================================
--  Sistema web de gestión de préstamos de equipos y recursos
--  Programación V · Entregable 2 · Base de datos MySQL / MariaDB
--  Importar en phpMyAdmin (pestaña "Importar") o con:
--      mysql -u root < prestamos_equipos.sql
-- =====================================================================

CREATE DATABASE IF NOT EXISTS prestamos_equipos
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE prestamos_equipos;

-- ---------------------------------------------------------------------
--  Limpieza (permite volver a ejecutar el script desde cero)
-- ---------------------------------------------------------------------
DROP VIEW  IF EXISTS v_equipos_mas_prestados;
DROP VIEW  IF EXISTS v_prestamos_vencidos;
DROP VIEW  IF EXISTS v_prestamos_detalle;
DROP TABLE IF EXISTS multas;
DROP TABLE IF EXISTS prestamos;
DROP TABLE IF EXISTS equipos;
DROP TABLE IF EXISTS categorias;
DROP TABLE IF EXISTS usuarios;
DROP TABLE IF EXISTS roles;

-- ---------------------------------------------------------------------
--  TABLAS
-- ---------------------------------------------------------------------

-- Roles del sistema: administrador, encargado y usuario
CREATE TABLE roles (
    id_rol       TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre       VARCHAR(20)      NOT NULL,
    descripcion  VARCHAR(120)     NOT NULL,
    PRIMARY KEY (id_rol),
    UNIQUE KEY uq_roles_nombre (nombre)
) ENGINE=InnoDB;

-- Personas que inician sesión y/o reciben equipos prestados
CREATE TABLE usuarios (
    id_usuario      INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    nombre          VARCHAR(60)      NOT NULL,
    apellidos       VARCHAR(80)      NOT NULL,
    cedula          VARCHAR(20)      NOT NULL,
    correo          VARCHAR(100)     NOT NULL,
    telefono        VARCHAR(20)      NULL,
    password_hash   VARCHAR(255)     NOT NULL,
    id_rol          TINYINT UNSIGNED NOT NULL,
    estado          ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
    fecha_registro  DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_usuario),
    UNIQUE KEY uq_usuarios_cedula (cedula),
    UNIQUE KEY uq_usuarios_correo (correo),
    KEY idx_usuarios_rol (id_rol),
    CONSTRAINT fk_usuarios_rol FOREIGN KEY (id_rol)
        REFERENCES roles (id_rol)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- Agrupa los equipos: laptops, proyectores, libros, etc.
CREATE TABLE categorias (
    id_categoria  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre        VARCHAR(60)  NOT NULL,
    descripcion   VARCHAR(200) NULL,
    PRIMARY KEY (id_categoria),
    UNIQUE KEY uq_categorias_nombre (nombre)
) ENGINE=InnoDB;

-- Inventario de equipos y recursos que se pueden prestar
CREATE TABLE equipos (
    id_equipo       INT UNSIGNED NOT NULL AUTO_INCREMENT,
    codigo          VARCHAR(30)  NOT NULL,
    nombre          VARCHAR(80)  NOT NULL,
    marca           VARCHAR(50)  NULL,
    modelo          VARCHAR(50)  NULL,
    descripcion     VARCHAR(255) NULL,
    id_categoria    INT UNSIGNED NOT NULL,
    estado          ENUM('disponible','prestado','mantenimiento','baja') NOT NULL DEFAULT 'disponible',
    fecha_registro  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_equipo),
    UNIQUE KEY uq_equipos_codigo (codigo),
    KEY idx_equipos_categoria (id_categoria),
    KEY idx_equipos_estado (estado),
    CONSTRAINT fk_equipos_categoria FOREIGN KEY (id_categoria)
        REFERENCES categorias (id_categoria)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- Cada préstamo guarda fecha de salida, fecha límite y fecha de devolución
CREATE TABLE prestamos (
    id_prestamo       INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_usuario        INT UNSIGNED NOT NULL,   -- quien recibe el equipo
    id_equipo         INT UNSIGNED NOT NULL,
    id_encargado      INT UNSIGNED NOT NULL,   -- quien registra el préstamo
    fecha_salida      DATETIME     NOT NULL,
    fecha_limite      DATETIME     NOT NULL,
    fecha_devolucion  DATETIME     NULL,
    estado            ENUM('activo','devuelto') NOT NULL DEFAULT 'activo',
    observaciones     VARCHAR(255) NULL,
    PRIMARY KEY (id_prestamo),
    KEY idx_prestamos_usuario (id_usuario),
    KEY idx_prestamos_equipo (id_equipo),
    KEY idx_prestamos_encargado (id_encargado),
    KEY idx_prestamos_estado (estado, fecha_limite),
    CONSTRAINT fk_prestamos_usuario FOREIGN KEY (id_usuario)
        REFERENCES usuarios (id_usuario)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_prestamos_equipo FOREIGN KEY (id_equipo)
        REFERENCES equipos (id_equipo)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_prestamos_encargado FOREIGN KEY (id_encargado)
        REFERENCES usuarios (id_usuario)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT ck_prestamos_limite CHECK (fecha_limite > fecha_salida),
    CONSTRAINT ck_prestamos_devolucion CHECK (fecha_devolucion IS NULL OR fecha_devolucion >= fecha_salida)
) ENGINE=InnoDB;

-- Multa automática cuando la devolución supera la fecha límite (una por préstamo)
CREATE TABLE multas (
    id_multa        INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    id_prestamo     INT UNSIGNED  NOT NULL,
    id_usuario      INT UNSIGNED  NOT NULL,
    dias_retraso    SMALLINT UNSIGNED NOT NULL,
    monto           DECIMAL(10,2) NOT NULL,
    estado          ENUM('pendiente','pagada') NOT NULL DEFAULT 'pendiente',
    fecha_generada  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_pago      DATETIME      NULL,
    PRIMARY KEY (id_multa),
    UNIQUE KEY uq_multas_prestamo (id_prestamo),
    KEY idx_multas_usuario (id_usuario, estado),
    CONSTRAINT fk_multas_prestamo FOREIGN KEY (id_prestamo)
        REFERENCES prestamos (id_prestamo)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_multas_usuario FOREIGN KEY (id_usuario)
        REFERENCES usuarios (id_usuario)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT ck_multas_monto CHECK (monto >= 0)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
--  VISTAS DE CONSULTA (las usan los reportes y el panel)
-- ---------------------------------------------------------------------

-- Préstamos con datos del usuario y del equipo.
-- "estado" es el estado real: activo, vencido (activo y pasada la fecha límite) o devuelto.
CREATE VIEW v_prestamos_detalle AS
SELECT
    p.id_prestamo,
    p.id_usuario,
    CONCAT(u.nombre, ' ', u.apellidos) AS usuario,
    u.cedula,
    u.correo,
    u.telefono,
    p.id_equipo,
    e.codigo AS equipo_codigo,
    e.nombre AS equipo_nombre,
    c.nombre AS categoria,
    p.id_encargado,
    CONCAT(enc.nombre, ' ', enc.apellidos) AS encargado,
    p.fecha_salida,
    p.fecha_limite,
    p.fecha_devolucion,
    p.estado AS estado_registro,
    CASE
        WHEN p.estado = 'devuelto'    THEN 'devuelto'
        WHEN p.fecha_limite < NOW()   THEN 'vencido'
        ELSE 'activo'
    END AS estado,
    GREATEST(0, CEIL(TIMESTAMPDIFF(MINUTE, p.fecha_limite, COALESCE(p.fecha_devolucion, NOW())) / 1440)) AS dias_retraso,
    p.observaciones
FROM prestamos p
JOIN usuarios   u   ON u.id_usuario   = p.id_usuario
JOIN usuarios   enc ON enc.id_usuario = p.id_encargado
JOIN equipos    e   ON e.id_equipo    = p.id_equipo
JOIN categorias c   ON c.id_categoria = e.id_categoria;

CREATE VIEW v_prestamos_vencidos AS
SELECT * FROM v_prestamos_detalle WHERE estado = 'vencido';

CREATE VIEW v_equipos_mas_prestados AS
SELECT
    e.id_equipo,
    e.codigo,
    e.nombre,
    c.nombre AS categoria,
    COUNT(p.id_prestamo) AS total_prestamos,
    MAX(p.fecha_salida)  AS ultimo_prestamo
FROM equipos e
JOIN categorias c ON c.id_categoria = e.id_categoria
LEFT JOIN prestamos p ON p.id_equipo = e.id_equipo
GROUP BY e.id_equipo, e.codigo, e.nombre, c.nombre;

-- ---------------------------------------------------------------------
--  DATOS DE PRUEBA
--  Contraseñas:  admin → Admin123*   encargado → Encargado123*   usuarios → Usuario123*
--  Las fechas son relativas a NOW() para que siempre haya un préstamo vencido de ejemplo.
-- ---------------------------------------------------------------------

INSERT INTO roles (id_rol, nombre, descripcion) VALUES
(1, 'administrador', 'Control total del sistema'),
(2, 'encargado',     'Registra préstamos, devoluciones y multas'),
(3, 'usuario',       'Consulta sus préstamos y multas');

INSERT INTO usuarios (id_usuario, nombre, apellidos, cedula, correo, telefono, password_hash, id_rol, estado) VALUES
(1, 'Ana',    'Rojas Mora',     '1-1111-1111', 'admin@prestamos.test',        '8888-0001', '$2y$10$oehtVQnapBnOOVWDLMgOg.PdNg48CVisMJ3lLNJ1DtJKa.0M7MToC', 1, 'activo'),
(2, 'Luis',   'Vargas Solís',   '2-2222-2222', 'encargado@prestamos.test',    '8888-0002', '$2y$10$Nrlrq9WJeGs7uMGxBWM83epOEv8JCKFCpKKgR4qVfX8nx2eSPw0Uy', 2, 'activo'),
(3, 'María',  'Jiménez Araya',  '3-0333-0333', 'maria.jimenez@prestamos.test','8888-0003', '$2y$10$r1hOYxBSy054btSSxBoHtu3KLipXq6rP52T8RX/NTI6o33wcJb1W6', 3, 'activo'),
(4, 'Carlos', 'Salas Quesada',  '4-0444-0444', 'carlos.salas@prestamos.test', '8888-0004', '$2y$10$r1hOYxBSy054btSSxBoHtu3KLipXq6rP52T8RX/NTI6o33wcJb1W6', 3, 'activo'),
(5, 'Sofía',  'Mora Chaves',    '5-0555-0555', 'sofia.mora@prestamos.test',   '8888-0005', '$2y$10$r1hOYxBSy054btSSxBoHtu3KLipXq6rP52T8RX/NTI6o33wcJb1W6', 3, 'activo'),
(6, 'Diego',  'Alvarado Pérez', '6-0666-0666', 'diego.alvarado@prestamos.test','8888-0006','$2y$10$r1hOYxBSy054btSSxBoHtu3KLipXq6rP52T8RX/NTI6o33wcJb1W6', 3, 'activo');

INSERT INTO categorias (id_categoria, nombre, descripcion) VALUES
(1, 'Computadoras portátiles', 'Laptops para clases y proyectos'),
(2, 'Proyectores',             'Proyectores para aulas y auditorios'),
(3, 'Audio y video',           'Parlantes, cámaras web y micrófonos'),
(4, 'Libros y material',       'Libros de texto y material de consulta'),
(5, 'Redes y accesorios',      'Switches, routers y cables');

INSERT INTO equipos (id_equipo, codigo, nombre, marca, modelo, descripcion, id_categoria, estado) VALUES
(1,  'LAP-001', 'Laptop Latitude 3420',   'Dell',     'Latitude 3420', '14", 16 GB RAM, 512 GB SSD', 1, 'prestado'),
(2,  'LAP-002', 'Laptop ThinkPad E14',    'Lenovo',   'ThinkPad E14',  '14", 8 GB RAM, 256 GB SSD',  1, 'disponible'),
(3,  'LAP-003', 'Laptop ProBook 450',     'HP',       'ProBook 450',   '15.6", 16 GB RAM, 512 GB SSD', 1, 'prestado'),
(4,  'PRO-001', 'Proyector EB-X06',       'Epson',    'EB-X06',        'HDMI y VGA, 3600 lúmenes',   2, 'disponible'),
(5,  'PRO-002', 'Proyector VS260',        'ViewSonic','VS260',         'HDMI, 3500 lúmenes',         2, 'prestado'),
(6,  'AUD-001', 'Parlante portátil',      'JBL',      'Charge 5',      'Bluetooth, batería 20 h',    3, 'disponible'),
(7,  'AUD-002', 'Cámara web',             'Logitech', 'C920',          'Full HD 1080p',              3, 'mantenimiento'),
(8,  'LIB-001', 'Fundamentos de bases de datos', 'McGraw-Hill', 'Silberschatz', 'Libro de texto, 7.ª edición', 4, 'disponible'),
(9,  'LIB-002', 'Clean Code',             'Prentice Hall', 'Robert C. Martin', 'Libro de consulta',  4, 'disponible'),
(10, 'RED-001', 'Switch 8 puertos',       'TP-Link',  'TL-SG108',      'Gigabit, sin administración',5, 'baja');

INSERT INTO prestamos (id_prestamo, id_usuario, id_equipo, id_encargado, fecha_salida, fecha_limite, fecha_devolucion, estado, observaciones) VALUES
-- En curso y vencido: María tiene LAP-001 desde hace 5 días, debía devolverlo hace 2
(1, 3, 1, 2, DATE_SUB(NOW(), INTERVAL 5 DAY),  DATE_SUB(NOW(), INTERVAL 2 DAY),  NULL, 'activo',   'Para práctica de laboratorio'),
-- En curso dentro del plazo
(2, 4, 3, 2, DATE_SUB(NOW(), INTERVAL 1 DAY),  DATE_ADD(NOW(), INTERVAL 2 DAY),  NULL, 'activo',   NULL),
(3, 6, 5, 2, DATE_SUB(NOW(), INTERVAL 3 HOUR), DATE_ADD(NOW(), INTERVAL 1 DAY),  NULL, 'activo',   'Presentación de proyecto final'),
-- Devuelto a tiempo
(4, 3, 2, 2, DATE_SUB(NOW(), INTERVAL 12 DAY), DATE_SUB(NOW(), INTERVAL 9 DAY),  DATE_SUB(NOW(), INTERVAL 9 DAY), 'devuelto', NULL),
-- Devueltos con retraso (generaron multa)
(5, 4, 4, 2, DATE_SUB(NOW(), INTERVAL 20 DAY), DATE_SUB(NOW(), INTERVAL 17 DAY), DATE_SUB(NOW(), INTERVAL 14 DAY), 'devuelto', NULL),
(6, 5, 6, 2, DATE_SUB(NOW(), INTERVAL 15 DAY), DATE_SUB(NOW(), INTERVAL 12 DAY), DATE_SUB(NOW(), INTERVAL 10 DAY), 'devuelto', NULL);

INSERT INTO multas (id_multa, id_prestamo, id_usuario, dias_retraso, monto, estado, fecha_generada, fecha_pago) VALUES
(1, 5, 4, 3, 1500.00, 'pagada',    DATE_SUB(NOW(), INTERVAL 14 DAY), DATE_SUB(NOW(), INTERVAL 13 DAY)),
(2, 6, 5, 2, 1000.00, 'pendiente', DATE_SUB(NOW(), INTERVAL 10 DAY), NULL);
