# SGI Aurora v2.0 — Actualización de Seguridad y Tiempo Real

## CAMBIOS IMPLEMENTADOS

### 1. Ciberseguridad para todos los roles
- **Login sin autocompletado de contraseña** — el botón de acceso rápido solo rellena el email, el campo contraseña queda vacío y enfocado
- **Bloqueo por fuerza bruta** — tras 5 intentos fallidos en 15 minutos, la IP queda bloqueada automáticamente
- **Niveles de bitácora** — INFO / WARNING / ERROR / CRÍTICO detectados automáticamente según la acción
- **ACCESO_DENEGADO** registrado en bitácora cuando alguien intenta acceder a un módulo sin permiso
- **Badge de seguridad** visible en el login ("Conexión segura · Sesión cifrada · Ley 164")
- **auditLog mejorado** — detecta automáticamente el nivel según palabras clave en la acción

### 2. Contraseñas (aurora123 para todos los roles de demo)
| Rol       | Email                  | Contraseña |
|-----------|------------------------|------------|
| Cliente   | cliente@aurora.com     | aurora123  |
| Asesor    | asesor@aurora.com      | aurora123  |
| Legal     | legal@aurora.com       | aurora123  |
| Admin     | admin@aurora.com       | aurora123  |
| Cliente 2 | maria@aurora.com       | aurora123  |
| Cliente 3 | roberto@aurora.com     | aurora123  |

### 3. Consultas en tiempo real (Cliente → Asesor)
- **Cliente** → menú "💬 Consultas" → formulario con asunto, mensaje y lote opcional
- **Asesor** → menú "💬 Consultas" → bandeja en vivo, actualización cada **5 segundos** sin recargar
- Toast de notificación cuando llega consulta nueva
- Badge numérico en el menú lateral con cantidad de consultas sin leer
- Asesor puede responder con botón "💬 Responder" → modal
- Cliente ve la respuesta en su historial al instante

### 4. Bitácora en tiempo real (Admin)
- **Admin** → menú "📜 Bitácora" → tabla actualizada cada **5 segundos**
- KPIs en vivo: acciones hoy / errores / logins / intentos fallidos
- Filtros: por fecha, email, nivel (INFO/WARNING/ERROR/CRÍTICO), texto de acción
- Click en fila → panel lateral con todos los detalles del evento
- Exportar a CSV con todos los filtros aplicados
- Filas nuevas destacadas con animación flash azul
- Dot verde parpadeante indica conexión activa; rojo si hay error de red

## INSTALACIÓN
1. Copiar `sgi_aurora/` a `C:\xampp\htdocs\sgi_aurora\`
2. phpMyAdmin → importar `database.sql` completo
3. Abrir `http://localhost/sgi_aurora/`
4. La columna `nivel` en `auditoria` se agrega automáticamente — si hay error, ejecutar: 
   `ALTER TABLE auditoria ADD COLUMN nivel ENUM('info','warning','error','critico') DEFAULT 'info';`

## ARCHIVOS NUEVOS
- `index.php` — login renovado con seguridad
- `config/db.php` — auditLog v2 con niveles
- `api_realtime.php` — API de polling para tiempo real
- `admin/bitacora.php` — bitácora en vivo (reemplaza auditoria.php)
- `asesor/consultas.php` — bandeja de consultas en vivo
- `cliente/consultas.php` — enviar consultas al asesor
- `cliente/consultas_list.php` — endpoint interno de la lista
