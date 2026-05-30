# CLAUDE.md — Plataforma de Preinscripciones IEM

Guía para trabajar en este repositorio (stack, convenciones, comandos y estado).

## Qué es
Plataforma web de **preinscripciones, inscripciones y matriculación** para un centro de
formación sanitaria (IEM). PHP autoinstalable, multiidioma, con instalador web paso a paso,
actualización OTA por Git y migración guiada entre servidores.

## Stack
- **PHP ≥ 8.2** (desarrollado y probado en 8.4), nativo, MVC ligero con router propio.
- **MySQL vía PDO** en producción; `pdo_sqlite` se usa solo para los tests locales.
- **Sin Composer en runtime**: autoloader PSR-4 propio (`app/bootstrap.php`). `vendor/` se
  commitea cuando se incorporen librerías de terceros (PHPMailer, Stripe, Dompdf…).
- **Frontend** server-side (plantillas PHP en `views/`) + CSS propio + JS vanilla. Sin build.
- **Auth:** sesiones PHP endurecidas + **argon2id**; CSRF, rate limiting, verificación de
  email, recuperación de contraseña y **2FA TOTP** (RFC 6238, implementación propia).
- **i18n** propio: `lang/{es,ca,en,pt}/*.php`. Toda cadena nueva debe existir en los 4 idiomas.

## Estructura
```
app/
  bootstrap.php        Autoloader, entorno, manejo de errores
  Core/                Router, Request/Response, Database(PDO), Auth, Rbac, Csrf, I18n,
                       Validator, Migrator, Mailer, Totp, Token, Audit, Flash, Shell, ...
  Controllers/         HomeController, AuthController, DashboardController, SystemController
  Models/              Model (base), User
config/                .env (generado, 0600), installed.lock
database/
  migrations/          NNN_*.php (devuelven ['up'=>fn, 'down'=>fn])
  seeds/               seed.php (datos de ejemplo)
lang/{es,ca,en,pt}/    common, auth, validation, emails, nav, dashboard, home, system
public/
  index.php            Front controller
  router.php           Router para `php -S` (solo desarrollo)
  install/             Instalador web aislado (PHP+PDO), con sus propios lang/ y assets/
  assets/              css/app.css (design tokens IEM), js/app.js (toasts), img/logo.svg
routes/web.php         Definición de rutas + middlewares
views/                 layouts/, partials/, home/, auth/, dashboard/, system/, errors/
cli/console.php        CLI: migrate, migrate:status, seed, cron, make:admin
storage/               logs/, cache/, backups/, tmp/ (no versionado salvo .gitkeep)
tests/run.php          Test runner ligero sin dependencias (SQLite en memoria)
```

## Comandos
```bash
# Servidor de desarrollo
php -S localhost:8000 -t public public/router.php

# Migraciones
php cli/console.php migrate
php cli/console.php migrate:status

# Datos de ejemplo (tras configurar .env y migrar)
php cli/console.php seed

# Tests (no requieren MySQL ni Composer)
php tests/run.php

# Lint de sintaxis
find app cli routes tests public/install views -name '*.php' -print0 | xargs -0 -n1 php -l
```

## Convenciones
- **Idiomas:** ninguna cadena literal en UI/emails/errores; usar `__('grupo.clave')`. Añadir
  la clave en `lang/es`, `lang/ca`, `lang/en` y `lang/pt` a la vez.
- **Escape:** `e()` para toda salida HTML. CSRF (`csrf_field()`) en todos los formularios POST.
- **Rutas protegidas:** middlewares `auth`, `guest`, `role:owner,admin,gestor`, `csrf`,
  `throttle:n,seg` en `routes/web.php`.
- **Auditoría:** `Audit::log()` en toda acción sensible (login, cambios de estado, pagos…).
- **Portabilidad:** usar `App\Core\Shell` para detectar `exec/git/mysqldump` y degradar a
  fallbacks PHP en hosting compartido.
- **Marca (design tokens en `public/assets/css/app.css`):** acento `#A11600`, texto `#525252`,
  secundario `#FFA6A6`, fondo blanco, tipografía Helvetica, esquinas sutiles, sin sombras.

## Roles (RBAC)
`owner` y `admin` → configuración de plataforma · `gestor` → gestión del proceso (sin
configuración) · `estudiante` → su panel.

## Estado actual
Ver `PLAN.md`. **Fase 1 (cimientos + instalador) completada.** Siguiente: Fase 2 del Bloque A
(campos dinámicos + configuración de plataforma).
