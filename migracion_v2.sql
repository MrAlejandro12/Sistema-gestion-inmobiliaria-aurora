-- ============================================================
-- SGI-AURORA v2.0 — Script de MIGRACIÓN
-- Ejecutar en phpMyAdmin DESPUÉS del database.sql original
-- Solo agrega lo nuevo, no rompe nada existente
-- ============================================================
USE sgi_aurora;

-- 1. Tabla de consultas cliente → asesor
CREATE TABLE IF NOT EXISTS consultas_cliente (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  cliente_id     INT NOT NULL,
  asesor_id      INT,
  lote_id        INT,
  asunto         VARCHAR(200) NOT NULL,
  mensaje        TEXT NOT NULL,
  estado         ENUM('nueva','leida','respondida','cerrada') DEFAULT 'nueva',
  respuesta      TEXT,
  respondido_por INT,
  created_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
  respondido_at  DATETIME,
  FOREIGN KEY (cliente_id)     REFERENCES clientes(id) ON DELETE CASCADE,
  FOREIGN KEY (asesor_id)      REFERENCES usuarios(id) ON DELETE SET NULL,
  FOREIGN KEY (lote_id)        REFERENCES lotes(id)    ON DELETE SET NULL,
  FOREIGN KEY (respondido_por) REFERENCES usuarios(id) ON DELETE SET NULL
);

-- 2. Columna nivel en auditoria (si no existe)
ALTER TABLE auditoria
  ADD COLUMN IF NOT EXISTS nivel
    ENUM('info','warning','error','critico') NOT NULL DEFAULT 'info';

-- 3. Índices de rendimiento
ALTER TABLE auditoria      ADD INDEX IF NOT EXISTS idx_audit_created  (created_at);
ALTER TABLE auditoria      ADD INDEX IF NOT EXISTS idx_audit_nivel     (nivel);
ALTER TABLE consultas_cliente ADD INDEX IF NOT EXISTS idx_cc_asesor    (asesor_id, estado);
ALTER TABLE consultas_cliente ADD INDEX IF NOT EXISTS idx_cc_cliente   (cliente_id, created_at);

-- 4. Dato de prueba (solo si no existe ya)
INSERT INTO consultas_cliente (cliente_id, asesor_id, asunto, mensaje, estado, created_at)
SELECT c.id, c.asesor_id,
  'Consulta de ejemplo — sistema en vivo',
  'Hola, estoy interesado en conocer más sobre los lotes disponibles en Equipetrol. ¿Tienen disponibilidad para una visita esta semana?',
  'nueva',
  NOW()
FROM clientes c
WHERE c.id = 1
  AND NOT EXISTS (SELECT 1 FROM consultas_cliente WHERE cliente_id = 1 LIMIT 1)
LIMIT 1;

SELECT '✅ Migración v2.0 completada correctamente.' AS resultado;
