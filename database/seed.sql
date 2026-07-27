-- ============================================================
-- seed.sql - Datos de prueba
-- Ejecutar DESPUES de schema.sql
-- ============================================================
USE revistas_domicilio;

-- ------------------------------------------------------------
-- USUARIOS del panel
-- Contrasena de ambos: admin123  /  operador123
-- (hashes generados con password_hash de PHP, algoritmo bcrypt)
-- ------------------------------------------------------------
INSERT INTO usuario (nombre_usuario, password_hash, nombre_completo, rol) VALUES
('admin',    '$2y$10$5Kk1sZ8G3yQxJ9d0mYVOKuOMGqOwyRfE3sQeXH0VZ97cWZ0hL6oOm', 'Santiago Administrador', 'administrador'),
('operador', '$2y$10$eImiTXuWVxfM37uY4JANjQ0N6L1S7kZxRR2vYbqvVXHzGJ4y1a5aC', 'Operador de Turno',      'operador');
-- NOTA: si el login no acepta estas claves, ejecutar en PHP:
--   echo password_hash('admin123', PASSWORD_DEFAULT);
-- y reemplazar el hash con UPDATE usuario SET password_hash='...' WHERE nombre_usuario='admin';

-- ------------------------------------------------------------
-- PERSONAS
-- ------------------------------------------------------------
INSERT INTO persona (cedula, nombres, apellidos, direccion, telefono, email) VALUES
('1712345678', 'Maria Fernanda', 'Guerrero Paz',   'Av. Amazonas N34-120 y Rumipamba, Quito', '0991234567', 'maria.guerrero@mail.com'),
('1723456789', 'Carlos Andres',  'Molina Reyes',   'Calle Garcia Moreno Oe4-56, Centro Historico, Quito', '0982345678', 'carlos.molina@mail.com'),
('1734567890', 'Ana Lucia',      'Torres Vinueza', 'Av. 6 de Diciembre y Colon, Edif. Titanium, Quito', '0973456789', 'ana.torres@mail.com'),
('1745678901', 'Jorge Luis',     'Cevallos Mena',  'Sector La Armenia, Conjunto Los Pinos casa 12, Valle de los Chillos', '0964567890', 'jorge.cevallos@mail.com'),
('1756789012', 'Paola Elizabeth','Salazar Ortiz',  'Av. Interoceanica km 12, Cumbaya', '0955678901', 'paola.salazar@mail.com');

-- ------------------------------------------------------------
-- REVISTAS
-- ------------------------------------------------------------
INSERT INTO revista (nombre, categoria, periodicidad, precio_suscripcion, estado) VALUES
('Mundo Andino',        'Cultura y viajes',  'mensual',   12.50, 'activa'),
('TecnoVision',         'Tecnologia',        'quincenal',  9.90, 'activa'),
('Cocina Ecuatoriana',  'Gastronomia',       'mensual',    8.75, 'activa'),
('Deporte Total',       'Deportes',          'semanal',    6.50, 'activa'),
('Historia Viva',       'Historia',          'mensual',   11.00, 'descontinuada');

-- ------------------------------------------------------------
-- EJEMPLARES
-- ------------------------------------------------------------
INSERT INTO ejemplar (id_revista, numero_edicion, fecha_publicacion, stock_disponible, precio_unitario) VALUES
(1, 45, '2026-06-01', 120, 3.50),
(1, 46, '2026-07-01', 150, 3.50),
(2, 88, '2026-06-15',  80, 2.75),
(2, 89, '2026-07-01',  95, 2.75),
(3, 30, '2026-06-01',  60, 3.00),
(3, 31, '2026-07-01',  70, 3.00),
(4, 210,'2026-07-06', 200, 1.80),
(4, 211,'2026-07-13', 180, 1.80),
(5, 12, '2026-01-01',  10, 4.00);

-- ------------------------------------------------------------
-- AGENCIAS DE TRANSPORTE
-- ------------------------------------------------------------
INSERT INTO agencia_transporte (nombre, ruc, telefono, cobertura_zona, costo_base) VALUES
('Servientrega Ecuador', '1790012345001', '022345678', 'Nacional',            3.50),
('Urbano Express',       '1791123456001', '023456789', 'Quito urbano',        2.00),
('Tramaco Express',      '1792234567001', '024567890', 'Pichincha y valles',  2.75);

-- ------------------------------------------------------------
-- SUSCRIPCIONES
-- ------------------------------------------------------------
INSERT INTO suscripcion (id_persona, id_revista, fecha_inicio, fecha_fin, estado) VALUES
(1, 1, '2026-01-15', NULL,        'activa'),
(1, 3, '2026-02-01', NULL,        'activa'),
(2, 2, '2026-03-10', NULL,        'activa'),
(3, 4, '2026-01-05', '2026-06-05','vencida'),
(3, 1, '2026-06-20', NULL,        'activa'),
(4, 2, '2026-04-01', '2026-05-15','cancelada'),
(5, 3, '2026-05-01', NULL,        'activa');

-- ------------------------------------------------------------
-- ENVIOS
-- ------------------------------------------------------------
INSERT INTO envio (id_persona, id_agencia, id_usuario, fecha_envio, fecha_entrega_estimada, fecha_entrega_real, estado_envio, direccion_entrega, costo_total) VALUES
(1, 2, 1, '2026-07-02', '2026-07-04', '2026-07-03', 'entregado',   'Av. Amazonas N34-120 y Rumipamba, Quito', 9.00),
(2, 1, 2, '2026-07-05', '2026-07-08', NULL,         'en_transito', 'Calle Garcia Moreno Oe4-56, Centro Historico, Quito', 6.25),
(3, 3, 1, '2026-07-10', '2026-07-12', NULL,         'pendiente',   'Av. 6 de Diciembre y Colon, Edif. Titanium, Quito', 8.05),
(5, 3, 2, '2026-06-28', '2026-06-30', '2026-07-01', 'entregado',   'Av. Interoceanica km 12, Cumbaya', 5.75);

-- ------------------------------------------------------------
-- DETALLES DE ENVIO
-- ------------------------------------------------------------
INSERT INTO detalle_envio (id_envio, id_ejemplar, cantidad, subtotal) VALUES
(1, 2, 1, 3.50),  -- Mundo Andino #46
(1, 6, 1, 3.00),  -- Cocina Ecuatoriana #31  (+ costo_base 2.00 aprox -> 9.00)
(2, 4, 1, 2.75),  -- TecnoVision #89          (+ 3.50 -> 6.25)
(3, 2, 1, 3.50),  -- Mundo Andino #46
(3, 8, 1, 1.80),  -- Deporte Total #211       (+ 2.75 -> 8.05)
(4, 5, 1, 3.00);  -- Cocina Ecuatoriana #30   (+ 2.75 -> 5.75)