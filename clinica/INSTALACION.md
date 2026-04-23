# Clínica de Especialidades — Instalación en XAMPP

## Estructura Final del Proyecto
```
htdocs/
└── clinica/
    ├── index.php           ← Router API (entrada de todas las peticiones)
    ├── public.html         ← Frontend SPA (renombrar a index.html si se desea)
    ├── .htaccess           ← Redirección al router
    ├── config/
    │   └── database.php
    ├── controllers/
    │   └── Controllers.php
    ├── models/
    │   └── Models.php
    ├── middlewares/
    │   └── Auth.php
    ├── helpers/
    │   ├── Response.php
    │   └── Log.php
    ├── views/
    │   └── assets/
    │       └── js/
    │           └── app.js
    └── sql/
        ├── 01_schema.sql
        └── 02_indices_vistas_sp_triggers_seeds.sql
```

## Pasos de Instalación

### 1. Copiar el proyecto
Copia toda la carpeta `clinica/` a `C:\xampp\htdocs\clinica\`

### 2. Crear la base de datos
1. Abre XAMPP → Start Apache + MySQL
2. Abre `http://localhost/phpmyadmin`
3. Ejecuta `sql/01_schema.sql`
4. Ejecuta `sql/02_indices_vistas_sp_triggers_seeds.sql`

### 3. Habilitar mod_rewrite en XAMPP
En `C:\xampp\apache\conf\extra\httpd-vhosts.conf` o en `httpd.conf`:
```apache
<Directory "C:/xampp/htdocs/clinica">
    AllowOverride All
    Require all granted
</Directory>
```

### 4. Ajustar el path de la API en app.js (si el proyecto no está en /clinica)
En `views/assets/js/app.js`, línea 5:
```js
const API = '/clinica';  // ← ajusta si está en otra ruta
```

### 5. Acceder al sistema
- Frontend: `http://localhost/clinica/public.html`
- API:      `http://localhost/clinica/`
- Usuario:  `admin` / Contraseña: `Admin1234`

## Módulos Implementados

| Módulo         | Endpoints                                    |
|----------------|----------------------------------------------|
| Auth           | login, logout, me, cambiar-password          |
| Médicos        | CRUD + búsqueda + horarios                   |
| Pacientes      | CRUD + búsqueda + historial de citas         |
| Citas          | CRUD + disponibilidad + filtros              |
| Consultas      | Registro + receta médica                     |
| Medicamentos   | Inventario + entradas de stock + caducos     |
| Pagos          | Listado + cobrar                             |
| Reportes       | Ingresos, género, consultas, bitácora        |
| Catálogos      | Especialidades, consultorios, tipos          |

## Roles del Sistema
- `administrador` — Acceso total
- `recepcionista` — Citas, pacientes, médicos, pagos
- `medico` — Consultas propias
- `paciente` — Sus citas

## Credenciales iniciales
- **Usuario:** admin
- **Contraseña:** Admin1234
