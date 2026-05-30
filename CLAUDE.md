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
  Controllers/         Home, Auth, Dashboard, System, Fields, Settings, Legal, Courses,
                       Editions, Preinscription, Student, ManagePreinscriptions
  Models/              Model, User, FieldDefinition, LegalDocument, Course, CourseEdition,
                       Preinscription, DocumentRequirement
  Services/            FieldService, ConsentService, PreinscriptionService + Status,
                       DocumentService, Notifier
config/                .env (generado, 0600), installed.lock
database/
  migrations/          NNN_*.php (devuelven ['up'=>fn, 'down'=>fn])
  seeds/               seed.php (datos de ejemplo)
lang/{es,ca,en,pt}/    common, auth, validation, emails, nav, dashboard, home, system,
                       fields, settings, legal
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

## Campos dinámicos (Fase 2)
- `FieldDefinition` (tabla `field_definitions`): define campos por `form_key` (preinscription,
  profile, academic) con textos multiidioma (JSON), opciones, validaciones y orden.
- `FieldService`: `renderField()` (HTML con `name="field[clave]"`), `validate()`, `save()`,
  `values()`. Los valores se guardan en `field_values` por (entity_type, entity_id).
- Configuración en caliente: `App\Core\Settings` (tabla `settings`) para opciones; secretos de
  integraciones vía `App\Core\EnvWriter` (reescribe `config/.env`, 0600).
- Legal: `LegalDocument` + `consents` con `ConsentService` (consentimientos versionados).

## Catálogo y preinscripción (Bloque B)
- `Course` / `CourseEdition` (textos multiidioma JSON, aforo, periodo, formas de pago,
  prerrequisito). `DocumentRequirement` por curso/edición (obligatorio, caducidad).
- `Preinscription` + **máquina de estados** `PreinscriptionStatus` (borrador → preinscrito →
  documentacion_en_revision → aceptado/rechazado/en_lista_de_espera → pendiente_pago →
  pago_en_revision → matriculado/cancelado). `PreinscriptionService` aplica transiciones,
  aforo y promoción de lista de espera; registra historial + auditoría + email.
- `DocumentService`: subida fuera del webroot (`storage/uploads/preinscriptions/<id>/`),
  validación de mime/tamaño y descarga mediante endpoint autenticado.
- `Notifier`: emails multiidioma (grupo lang `notifications`) en el idioma del destinatario.
- Asistente multipaso con campos dinámicos, tutor (menor) y guardar/reanudar (borrador).

## Pagos y facturación (Bloque C)
- `Payment` (calendario de cobros: matrícula/plazos), `PaymentService` (genera pagos, aplica
  descuentos, valida justificantes, cobra → factura → matriculado, reembolsa con nota de crédito).
- `StripeGateway` (Checkout real si hay claves, simulado si no; webhook en `/webhooks/stripe`).
  Bizum/transferencia con justificante validado por el gestor.
- `Discount` (códigos/becas), `BillingProfile` (datos fiscales), `Invoice` + `InvoiceService`
  (PDF con **Dompdf**, numeración correlativa, IVA/exención, abonos). Datos FUNDAE.
- **Dependencias de terceros vendorizadas** en `vendor/` (Dompdf, Stripe SDK); el autoloader propio
  carga `vendor/autoload.php` si existe. `composer.json`/`composer.lock` versionados.

## Comunicación y datos (Bloque D)
- Mensajería: `MessageThread` + `MessageService` (hilos estudiante↔staff, no leídos por id de
  mensaje, notificación por email/toast). `EmailTemplate` + `Notifier` (plantilla de BD por
  evento/idioma con variables, o texto i18n por defecto).
- `ReminderService` (cron, `cli/cron.php`): recordatorios idempotentes (tabla `reminders_sent`).
- `ReportService`: KPIs y exportación CSV. `AuditController` (visor de auditoría, solo admin).

## Estado actual
Ver `PLAN.md`. **Bloques A, B, C y D completados (Fases 1-11).** Siguiente: Bloque E (certificados
PDF + AlexiaEdu + iCal, OTA por Git, migración guiada y pulido final).
