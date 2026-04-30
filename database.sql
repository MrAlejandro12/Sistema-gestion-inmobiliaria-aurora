-- ============================================================
-- SGI-AURORA — Base de datos MySQL
-- Bienes Raíces Aurora, Santa Cruz de la Sierra, Bolivia
-- Ejecutar en phpMyAdmin o MySQL CLI
-- ============================================================

CREATE DATABASE IF NOT EXISTS sgi_aurora CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sgi_aurora;

-- ── USUARIOS DEL SISTEMA ────────────────────────────────────
CREATE TABLE usuarios (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  nombre      VARCHAR(120) NOT NULL,
  email       VARCHAR(120) NOT NULL UNIQUE,
  password    VARCHAR(255) NOT NULL,   -- bcrypt hash
  rol         ENUM('cliente','asesor','legal','admin') NOT NULL DEFAULT 'cliente',
  estado      ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
  telefono    VARCHAR(30),
  ci          VARCHAR(30),
  created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
  ultimo_acceso DATETIME
);

-- ── ZONAS ───────────────────────────────────────────────────
CREATE TABLE zonas (
  id    INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL
);

-- ── LOTES ───────────────────────────────────────────────────
CREATE TABLE lotes (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  codigo      VARCHAR(20) NOT NULL UNIQUE,
  nombre      VARCHAR(150) NOT NULL,
  zona_id     INT NOT NULL,
  direccion   VARCHAR(200),
  superficie  DECIMAL(10,2) NOT NULL,
  precio      DECIMAL(12,2) NOT NULL,
  tipo        ENUM('Residencial','Comercial','Industrial') DEFAULT 'Residencial',
  estado      ENUM('disponible','apartado','vendido','revision') DEFAULT 'revision',
  verificado  TINYINT(1) DEFAULT 0,
  servicios   VARCHAR(200),            -- CSV: Agua,Luz,Gas,...
  emoji       VARCHAR(10) DEFAULT '🏡',
  asesor_id   INT,
  created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (zona_id)   REFERENCES zonas(id),
  FOREIGN KEY (asesor_id) REFERENCES usuarios(id) ON DELETE SET NULL
);

-- ── CLIENTES (perfil extendido del usuario cliente) ─────────
CREATE TABLE clientes (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id      INT NOT NULL UNIQUE,
  zona_preferida  INT,
  tipo_lote       ENUM('Residencial','Comercial','Industrial') DEFAULT 'Residencial',
  presupuesto_min DECIMAL(12,2) DEFAULT 0,
  presupuesto_max DECIMAL(12,2) DEFAULT 0,
  servicios_req   VARCHAR(200),
  asesor_id       INT,
  estado_crm      ENUM('prospecto','contactado','visita','interesado','propuesta','cerrado','descartado') DEFAULT 'prospecto',
  consentimiento_ley164 TINYINT(1) DEFAULT 0,
  created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (usuario_id)     REFERENCES usuarios(id) ON DELETE CASCADE,
  FOREIGN KEY (zona_preferida) REFERENCES zonas(id) ON DELETE SET NULL,
  FOREIGN KEY (asesor_id)      REFERENCES usuarios(id) ON DELETE SET NULL
);

-- ── SOLICITUDES ─────────────────────────────────────────────
CREATE TABLE solicitudes (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  cliente_id  INT NOT NULL,
  asesor_id   INT,
  lote_id     INT,
  zona_id     INT,
  presupuesto_max DECIMAL(12,2),
  estado      ENUM('pendiente','en_proceso','recomendacion_enviada','cerrada','cancelada') DEFAULT 'pendiente',
  notas       TEXT,
  created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (cliente_id) REFERENCES clientes(id),
  FOREIGN KEY (asesor_id)  REFERENCES usuarios(id) ON DELETE SET NULL,
  FOREIGN KEY (lote_id)    REFERENCES lotes(id) ON DELETE SET NULL,
  FOREIGN KEY (zona_id)    REFERENCES zonas(id) ON DELETE SET NULL
);

-- ── VENTAS ──────────────────────────────────────────────────
CREATE TABLE ventas (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  cliente_id  INT NOT NULL,
  lote_id     INT NOT NULL,
  asesor_id   INT,
  monto       DECIMAL(12,2) NOT NULL,
  modalidad   ENUM('Contado','Credito Bancario','Credito Directo','Anticretico') NOT NULL,
  estado      ENUM('pendiente','en_legal','contrato','cerrada','cancelada') DEFAULT 'pendiente',
  fecha_venta DATE,
  contrato_id INT,
  created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (cliente_id) REFERENCES clientes(id),
  FOREIGN KEY (lote_id)    REFERENCES lotes(id),
  FOREIGN KEY (asesor_id)  REFERENCES usuarios(id) ON DELETE SET NULL
);

-- ── PAGOS (inmutables — Ley 393) ────────────────────────────
CREATE TABLE pagos (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  venta_id     INT NOT NULL,
  cliente_id   INT NOT NULL,
  monto        DECIMAL(12,2) NOT NULL,
  metodo       ENUM('Transferencia','Efectivo','Cheque','QR') NOT NULL,
  comprobante  VARCHAR(100),
  fecha_pago   DATE NOT NULL,
  registrado_por INT,
  created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
  -- Sin UPDATE ni DELETE permitidos en lógica de negocio (Ley 393)
  FOREIGN KEY (venta_id)       REFERENCES ventas(id),
  FOREIGN KEY (cliente_id)     REFERENCES clientes(id),
  FOREIGN KEY (registrado_por) REFERENCES usuarios(id) ON DELETE SET NULL
);

-- ── TAREAS LEGALES ──────────────────────────────────────────
CREATE TABLE tareas_legales (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  lote_id      INT NOT NULL,
  venta_id     INT,
  solicitado_por INT NOT NULL,
  asignado_a   INT,
  estado       ENUM('pendiente','en_proceso','aprobado','observado','rechazado') DEFAULT 'pendiente',
  prioridad    ENUM('baja','normal','alta','urgente') DEFAULT 'normal',
  doc_escritura  ENUM('pendiente','verificado','rechazado') DEFAULT 'pendiente',
  doc_ci         ENUM('pendiente','verificado','rechazado') DEFAULT 'pendiente',
  doc_catastral  ENUM('pendiente','verificado','rechazado') DEFAULT 'pendiente',
  observaciones TEXT,
  fecha_solicitud DATE DEFAULT (CURRENT_DATE),
  fecha_resolucion DATE,
  created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (lote_id)        REFERENCES lotes(id),
  FOREIGN KEY (venta_id)       REFERENCES ventas(id) ON DELETE SET NULL,
  FOREIGN KEY (solicitado_por) REFERENCES usuarios(id),
  FOREIGN KEY (asignado_a)     REFERENCES usuarios(id) ON DELETE SET NULL
);

-- ── CONTRATOS ───────────────────────────────────────────────
CREATE TABLE contratos (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  venta_id     INT NOT NULL UNIQUE,
  tarea_id     INT,
  generado_por INT,
  estado       ENUM('borrador','generado','firmado','cancelado') DEFAULT 'generado',
  fecha_gen    DATE DEFAULT (CURRENT_DATE),
  fecha_firma  DATE,
  notario      VARCHAR(120),
  archivo_path VARCHAR(255),
  created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (venta_id)     REFERENCES ventas(id),
  FOREIGN KEY (tarea_id)     REFERENCES tareas_legales(id) ON DELETE SET NULL,
  FOREIGN KEY (generado_por) REFERENCES usuarios(id) ON DELETE SET NULL
);

-- ── AUDITORÍA (inmutable — Ley 164) ─────────────────────────
CREATE TABLE auditoria (
  id        INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT,
  email     VARCHAR(120),
  accion    VARCHAR(255) NOT NULL,
  tabla_afectada VARCHAR(60),
  registro_id INT,
  ip        VARCHAR(45),
  user_agent VARCHAR(255),
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
);

-- ============================================================
-- DATOS INICIALES
-- ============================================================

-- Zonas
INSERT INTO zonas (nombre) VALUES
  ('Equipetrol'),('Urbarí'),('Las Palmas'),('Sirari'),
  ('Las Brisas'),('Villa Verde'),('Norte Integrado'),('Cotoca');

-- Usuarios (passwords: aurora123 para todos — hash bcrypt)
INSERT INTO usuarios (nombre, email, password, rol, telefono, ci) VALUES
  ('Juan Pérez López',    'cliente@aurora.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'cliente', '+591 76543210', '5890123 SC'),
  ('Carlos López Vaca',   'asesor@aurora.com',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'asesor',  '+591 77654321', '6234789 SC'),
  ('Ana Gómez Beltrán',   'legal@aurora.com',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'legal',   '+591 71234567', '7102345 SC'),
  ('Alejandro Foronda',   'admin@aurora.com',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin',   '+591 76543000', '5000001 SC'),
  ('María Torres Vaca',   'maria@aurora.com',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'cliente', '+591 77891234', '6234790 SC'),
  ('Roberto Díaz Suárez', 'roberto@aurora.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'cliente', '+591 71234568', '7102346 SC');

-- Perfil cliente
INSERT INTO clientes (usuario_id, zona_preferida, tipo_lote, presupuesto_min, presupuesto_max, servicios_req, asesor_id, estado_crm, consentimiento_ley164) VALUES
  (1, 1, 'Residencial', 20000, 60000, 'Agua,Luz,Drenaje,Internet', 2, 'interesado', 1),
  (5, 2, 'Residencial', 15000, 35000, 'Agua,Luz', 2, 'contactado', 1),
  (6, 3, 'Comercial',   40000, 70000, 'Agua,Luz,Gas,Internet', 2, 'propuesta', 1);

-- Lotes
INSERT INTO lotes (codigo, nombre, zona_id, direccion, superficie, precio, tipo, estado, verificado, servicios, emoji, asesor_id) VALUES
  ('EQ-L001','Lote Residencial Premium',   1,'Calle 5, entre Av. Beni y Brasil', 450, 35000,'Residencial','disponible',1,'Agua,Luz,Drenaje,Internet,Seguridad','🏡',2),
  ('UR-L002','Lote Urbarí Norte',           2,'Av. Urbarí Km 2.5',               320, 28000,'Residencial','disponible',1,'Agua,Luz,Internet','🌿',2),
  ('LP-L003','Lote Comercial Las Palmas',   3,'Av. Las Palmas Nro. 120',          600, 65000,'Comercial',  'apartado',  1,'Agua,Luz,Gas,Drenaje,Internet,Seguridad','🏢',2),
  ('SR-L004','Lote Sirari Residencial',     4,'Calle Sirari 2do Anillo',          400, 38000,'Residencial','disponible',0,'Agua,Luz,Drenaje','🌳',NULL),
  ('LB-L005','Lote Las Brisas',             5,'Urb. Las Brisas, Mz. C Lote 8',   350, 29500,'Residencial','vendido',   1,'Agua,Luz,Internet','🏘️',2),
  ('VV-L006','Lote Villa Verde Premium',    6,'Villa Verde, Pasaje 3 Nro. 45',   480, 42000,'Residencial','disponible',1,'Agua,Luz,Gas,Drenaje,Internet,Seguridad','🌲',2);

-- Ventas
INSERT INTO ventas (cliente_id, lote_id, asesor_id, monto, modalidad, estado, fecha_venta) VALUES
  (3, 5, 2, 42000, 'Contado',         'cerrada',   '2026-04-05'),
  (2, 2, 2, 28000, 'Credito Bancario','en_legal',  '2026-04-01'),
  (1, 1, 2, 35000, 'Credito Directo', 'pendiente', '2026-04-11');

-- Pagos
INSERT INTO pagos (venta_id, cliente_id, monto, metodo, comprobante, fecha_pago, registrado_por) VALUES
  (1, 3, 42000, 'Transferencia', 'TRF-2026-04-001', '2026-04-05', 2),
  (2, 2,  5000, 'Transferencia', 'TRF-2026-04-002', '2026-04-01', 2);

-- Tareas legales
INSERT INTO tareas_legales (lote_id, venta_id, solicitado_por, asignado_a, estado, prioridad, doc_escritura, doc_ci, doc_catastral, fecha_solicitud) VALUES
  (1, 3, 2, 3, 'pendiente', 'alta',   'verificado','verificado','pendiente', '2026-04-11'),
  (5, 1, 2, 3, 'aprobado',  'normal', 'verificado','verificado','verificado','2026-04-01');

-- Contrato
INSERT INTO contratos (venta_id, tarea_id, generado_por, estado, fecha_gen, fecha_firma) VALUES
  (1, 2, 3, 'firmado', '2026-04-05', '2026-04-05');

-- Auditoría inicial
INSERT INTO auditoria (email, accion, ip) VALUES
  ('admin@aurora.com', 'Sistema inicializado — Base de datos creada', '127.0.0.1');

-- ============================================================
-- ACTUALIZACIÓN v2.0 — Bitácora y Consultas en tiempo real
-- ============================================================

-- Tabla de consultas del cliente → asesor
CREATE TABLE IF NOT EXISTS consultas_cliente (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  cliente_id  INT NOT NULL,
  asesor_id   INT,
  lote_id     INT,
  asunto      VARCHAR(200) NOT NULL,
  mensaje     TEXT NOT NULL,
  estado      ENUM('nueva','leida','respondida','cerrada') DEFAULT 'nueva',
  respuesta   TEXT,
  respondido_por INT,
  created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
  respondido_at DATETIME,
  FOREIGN KEY (cliente_id)     REFERENCES clientes(id),
  FOREIGN KEY (asesor_id)      REFERENCES usuarios(id) ON DELETE SET NULL,
  FOREIGN KEY (lote_id)        REFERENCES lotes(id) ON DELETE SET NULL,
  FOREIGN KEY (respondido_por) REFERENCES usuarios(id) ON DELETE SET NULL
);

-- Índices para polling eficiente
CREATE INDEX IF NOT EXISTS idx_audit_created ON auditoria(created_at);
CREATE INDEX IF NOT EXISTS idx_consult_asesor ON consultas_cliente(asesor_id, estado, created_at);

-- Dato de prueba de consulta
INSERT INTO consultas_cliente (cliente_id, asesor_id, asunto, mensaje, estado, created_at)
SELECT c.id, c.asesor_id,
  'Consulta de prueba — tiempo real',
  'Hola, estoy interesado en el lote de Equipetrol. ¿Tienen fotos adicionales? ¿Es posible una visita esta semana?',
  'nueva', NOW()
FROM clientes c WHERE c.id = 1 LIMIT 1
ON DUPLICATE KEY UPDATE id=id;
