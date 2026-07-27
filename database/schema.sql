-- ============================================================
-- SISTEMA DE ENVIO DE REVISTAS A DOMICILIO
-- schema.sql - DDL completo (8 tablas, 3FN)
-- Motor: MySQL/MariaDB (XAMPP) | Charset: utf8mb4
-- ============================================================

DROP DATABASE IF EXISTS revistas_domicilio;
CREATE DATABASE revistas_domicilio
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE revistas_domicilio;

-- ------------------------------------------------------------
-- 1. PERSONA: suscriptores y destinatarios de envios
-- ------------------------------------------------------------
CREATE TABLE persona (
  id_persona      INT AUTO_INCREMENT PRIMARY KEY,
  cedula          VARCHAR(10)  NOT NULL UNIQUE,
  nombres         VARCHAR(100) NOT NULL,
  apellidos       VARCHAR(100) NOT NULL,
  direccion       VARCHAR(200) NOT NULL,
  telefono        VARCHAR(15)  NOT NULL,
  email           VARCHAR(100) NOT NULL UNIQUE,
  fecha_registro  DATE         NOT NULL DEFAULT (CURRENT_DATE),
  estado          ENUM('activo','inactivo') NOT NULL DEFAULT 'activo'
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 2. REVISTA: publicaciones periodicas ofrecidas
-- ------------------------------------------------------------
CREATE TABLE revista (
  id_revista          INT AUTO_INCREMENT PRIMARY KEY,
  nombre              VARCHAR(100) NOT NULL,
  categoria           VARCHAR(50)  NOT NULL,
  periodicidad        ENUM('semanal','quincenal','mensual') NOT NULL,
  precio_suscripcion  DECIMAL(8,2) NOT NULL,
  estado              ENUM('activa','descontinuada') NOT NULL DEFAULT 'activa',
  CONSTRAINT chk_revista_precio CHECK (precio_suscripcion > 0)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 3. EJEMPLAR: ediciones concretas de una revista
-- ------------------------------------------------------------
CREATE TABLE ejemplar (
  id_ejemplar        INT AUTO_INCREMENT PRIMARY KEY,
  id_revista         INT          NOT NULL,
  numero_edicion     INT          NOT NULL,
  fecha_publicacion  DATE         NOT NULL,
  stock_disponible   INT          NOT NULL DEFAULT 0,
  precio_unitario    DECIMAL(8,2) NOT NULL,
  CONSTRAINT fk_ejemplar_revista
    FOREIGN KEY (id_revista) REFERENCES revista(id_revista)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT uq_ejemplar_edicion UNIQUE (id_revista, numero_edicion),
  CONSTRAINT chk_ejemplar_stock CHECK (stock_disponible >= 0)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 4. AGENCIA_TRANSPORTE: empresas que realizan entregas
-- ------------------------------------------------------------
CREATE TABLE agencia_transporte (
  id_agencia      INT AUTO_INCREMENT PRIMARY KEY,
  nombre          VARCHAR(100) NOT NULL,
  ruc             VARCHAR(13)  NOT NULL UNIQUE,
  telefono        VARCHAR(15)  NOT NULL,
  cobertura_zona  VARCHAR(100) NOT NULL,
  costo_base      DECIMAL(8,2) NOT NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 5. SUSCRIPCION: N:M entre PERSONA y REVISTA
-- ------------------------------------------------------------
CREATE TABLE suscripcion (
  id_suscripcion  INT AUTO_INCREMENT PRIMARY KEY,
  id_persona      INT  NOT NULL,
  id_revista      INT  NOT NULL,
  fecha_inicio    DATE NOT NULL DEFAULT (CURRENT_DATE),
  fecha_fin       DATE NULL,
  estado          ENUM('activa','cancelada','vencida') NOT NULL DEFAULT 'activa',
  CONSTRAINT fk_suscripcion_persona
    FOREIGN KEY (id_persona) REFERENCES persona(id_persona)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_suscripcion_revista
    FOREIGN KEY (id_revista) REFERENCES revista(id_revista)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 6. USUARIO: cuentas del panel administrativo
-- ------------------------------------------------------------
CREATE TABLE usuario (
  id_usuario      INT AUTO_INCREMENT PRIMARY KEY,
  nombre_usuario  VARCHAR(50)  NOT NULL UNIQUE,
  password_hash   VARCHAR(255) NOT NULL,
  nombre_completo VARCHAR(100) NOT NULL,
  rol             ENUM('administrador','operador') NOT NULL DEFAULT 'operador',
  estado          ENUM('activo','inactivo') NOT NULL DEFAULT 'activo'
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 7. ENVIO: evento de entrega a una persona via una agencia
-- ------------------------------------------------------------
CREATE TABLE envio (
  id_envio                INT AUTO_INCREMENT PRIMARY KEY,
  id_persona              INT  NOT NULL,
  id_agencia              INT  NOT NULL,
  id_usuario              INT  NOT NULL,
  fecha_envio             DATE NOT NULL DEFAULT (CURRENT_DATE),
  fecha_entrega_estimada  DATE NULL,
  fecha_entrega_real      DATE NULL,
  estado_envio            ENUM('pendiente','en_transito','entregado','devuelto')
                          NOT NULL DEFAULT 'pendiente',
  direccion_entrega       VARCHAR(200) NOT NULL,
  costo_total             DECIMAL(8,2) NOT NULL,
  CONSTRAINT fk_envio_persona
    FOREIGN KEY (id_persona) REFERENCES persona(id_persona)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_envio_agencia
    FOREIGN KEY (id_agencia) REFERENCES agencia_transporte(id_agencia)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_envio_usuario
    FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 8. DETALLE_ENVIO: N:M entre ENVIO y EJEMPLAR
-- ------------------------------------------------------------
CREATE TABLE detalle_envio (
  id_detalle   INT AUTO_INCREMENT PRIMARY KEY,
  id_envio     INT NOT NULL,
  id_ejemplar  INT NOT NULL,
  cantidad     INT NOT NULL,
  subtotal     DECIMAL(8,2) NOT NULL,
  CONSTRAINT fk_detalle_envio
    FOREIGN KEY (id_envio) REFERENCES envio(id_envio)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_detalle_ejemplar
    FOREIGN KEY (id_ejemplar) REFERENCES ejemplar(id_ejemplar)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT uq_detalle_envio_ejemplar UNIQUE (id_envio, id_ejemplar),
  CONSTRAINT chk_detalle_cantidad CHECK (cantidad > 0)
) ENGINE=InnoDB;