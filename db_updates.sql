-- ============================================================
-- SGI-AURORA v2.0 — Actualizaciones de BD
-- Ejecutar DESPUÉS del database.sql original
-- ============================================================
USE sgi_aurora;

-- ── 1. Mejorar tabla auditoria (si no tiene columna nivel) ───
ALTER TABLE auditoria 
  ADD COLUMN IF NOT EXISTS nivel ENUM('info','warning','error','critico') DEFAULT 'info',
  ADD COLUMN IF NOT EXISTS modulo VARCHAR(60) DEFAULT '',
  ADD COLUMN IF NOT EXISTS detalle TEXT DEFAULT NULL;

-- ── 2. Tabla de consultas del cliente (solicitudes detalladas) ─
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

-- ── 3. Tabla de intentos de login (seguridad) ───────────────
CREATE TABLE IF NOT EXISTS login_intentos (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  email      VARCHAR(120),
  ip         VARCHAR(45) NOT NULL,
  exitoso    TINYINT(1) DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ip_time (ip, created_at)
);

-- ── 4. Índice en auditoria para polling eficiente ───────────
ALTER TABLE auditoria ADD INDEX IF NOT EXISTS idx_created (created_at);
ALTER TABLE auditoria ADD INDEX IF NOT EXISTS idx_nivel (nivel);

-- ── 5. Insertar datos de prueba de consultas ────────────────
-- (se ejecutan solo si hay clientes registrados)
INSERT IGNORE INTO consultas_cliente (cliente_id, asesor_id, lote_id, asunto, mensaje, estado, created_at)
SELECT c.id, c.asesor_id, 1,
  'Consulta sobre lote EQ-L001',
  'Hola, me interesa el lote de Equipetrol. ¿Están disponibles los documentos de escritura? ¿Se puede visitar esta semana?',
  'nueva',
  DATE_SUB(NOW(), INTERVAL 2 HOUR)
FROM clientes c WHERE c.id = 1 LIMIT 1;
