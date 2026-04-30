# SGI Aurora — Sistema de Gestión Inmobiliaria
## Bienes Raíces Aurora · Santa Cruz de la Sierra, Bolivia

---

## INSTALACIÓN EN XAMPP (5 pasos)

### 1. Copiar el proyecto
Copia la carpeta `sgi_aurora` completa dentro de:
```
C:\xampp\htdocs\sgi_aurora\
```

### 2. Iniciar XAMPP
Abre XAMPP Control Panel y haz clic en **Start** para:
- **Apache**
- **MySQL**

### 3. Crear la base de datos en phpMyAdmin
1. Abre tu navegador y ve a: `http://localhost/phpmyadmin`
2. Haz clic en **Nueva** (panel izquierdo)
3. Escribe el nombre: `sgi_aurora` → clic en **Crear**
4. Haz clic en la pestaña **SQL**
5. Abre el archivo `sgi_aurora/database.sql` con el Bloc de notas
6. Copia todo el contenido y pégalo en phpMyAdmin
7. Clic en **Continuar** (botón azul)

### 4. Verificar la conexión
Abre `sgi_aurora/config/db.php` y verifica:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');   // Vacío en XAMPP por defecto
```
Si tu MySQL tiene contraseña, cámbiala aquí.

### 5. Abrir el sistema
Ve a: **http://localhost/sgi_aurora/**

---

## CREDENCIALES DE ACCESO

| Rol       | Email                    | Contraseña  |
|-----------|--------------------------|-------------|
| Cliente   | cliente@aurora.com       | aurora123   |
| Asesor    | asesor@aurora.com        | aurora123   |
| Legal     | legal@aurora.com         | aurora123   |
| Admin     | admin@aurora.com         | aurora123   |

También disponibles: `maria@aurora.com` y `roberto@aurora.com` (clientes)

---

## ESTRUCTURA DE ARCHIVOS

```
sgi_aurora/
├── index.php               ← Login
├── dashboard.php           ← Redirige según rol
├── logout.php              ← Cierra sesión
├── database.sql            ← SQL para phpMyAdmin
├── config/
│   └── db.php              ← Conexión MySQL + helpers
├── includes/
│   ├── layout.php          ← Sidebar, topbar (inicio del layout)
│   └── layout_end.php      ← Toast, modal JS (fin del layout)
├── admin/
│   ├── dashboard.php       ← KPIs, ventas por zona, últimas ops
│   ├── lotes.php           ← CRUD completo de lotes
│   ├── usuarios.php        ← Alta/baja de usuarios
│   ├── reportes.php        ← Reportes con filtros
│   ├── documentos.php      ← Marco legal y plantillas
│   ├── parametros.php      ← Config del sistema
│   └── auditoria.php       ← Log inmutable (Ley 164)
├── asesor/
│   ├── dashboard.php       ← KPIs del asesor
│   ├── solicitudes.php     ← Lista de solicitudes
│   ├── clientes.php        ← CRUD clientes (Ley 164)
│   ├── lotes.php           ← Ver lotes disponibles
│   ├── ventas.php          ← Ventas + Pagos (Ley 393)
│   └── contratos.php       ← Ver contratos generados
├── legal/
│   ├── dashboard.php       ← Panel legal
│   ├── tareas.php          ← Verificación DDRR (aprobar/rechazar)
│   ├── contratos.php       ← Ver contratos
│   └── trazabilidad.php    ← Historial Ley 393
└── cliente/
    ├── dashboard.php       ← Panel del cliente
    ├── catalogo.php        ← Búsqueda de lotes con filtros
    ├── asesor.php          ← Info del asesor asignado
    └── cuenta.php          ← Estado de cuenta y pagos
```

---

## FLUJO DEL SISTEMA

```
Cliente crea solicitud
        ↓
Asesor registra cliente y asigna lote
        ↓
Asesor registra venta → Lote pasa a "apartado"
        ↓
Asesor envía a verificación Legal
        ↓
Legal verifica DDRR → Aprueba o Rechaza
        ↓
Si aprueba: Contrato generado automáticamente → Lote "disponible"
        ↓
Asesor registra pago (Ley 393 — comprobante obligatorio >$1,000)
        ↓
Admin supervisa KPIs, reportes y auditoría
```

---

## CUMPLIMIENTO LEGAL

| Ley | Implementación en el sistema |
|-----|------------------------------|
| **Ley 164** | Consentimiento obligatorio al registrar clientes · Log de auditoría inmutable · Acceso por roles (no se puede acceder a módulos de otro rol) |
| **Ley 393** | Tabla `pagos` con solo INSERT (sin UPDATE/DELETE) · Comprobante obligatorio para montos >USD 1,000 · Historial de trazabilidad exportable |
| **Ley 247** | Verificación DDRR obligatoria antes de comercializar lotes · Lote no pasa a DISPONIBLE sin aprobación del rol LEGAL |

---

## TECNOLOGÍAS

- **Backend**: PHP 8.x con PDO (prepared statements)
- **Base de datos**: MySQL 8.x vía phpMyAdmin
- **Frontend**: HTML5 + CSS3 + JavaScript vanilla
- **Fuente**: Google Fonts — Poppins
- **Sin frameworks externos** — funciona con XAMPP estándar

---

Proyecto académico — Bienes Raíces Aurora · Marzo 2026
Alejandro Foronda · Rosario Durán · Mateo Saucedo
