# PLAN.md — Fases del proyecto

Cadencia acordada: **trabajo por bloques** de fases. Al cerrar cada bloque se deja la app
arrancando, se actualiza la documentación, se hace commit/push y se espera confirmación.

Leyenda: ✅ hecho · 🚧 en curso · ⬜ pendiente

## Bloque A — Cimientos + Instalador (Fases 1-2)
### Fase 1 — Cimientos + Instalador ✅
- ✅ Estructura del proyecto, autoloader PSR-4 propio, bootstrap, manejo de errores.
- ✅ Router propio con parámetros, grupos y middlewares.
- ✅ Capa PDO (`Database`) MySQL + SQLite (tests), prefijo de tablas, transacciones.
- ✅ Sistema de migraciones versionado (`Migrator`, tabla `schema_migrations`).
- ✅ Autenticación por sesión + **argon2id**, login/logout.
- ✅ Registro de estudiante + **verificación de email** (tokens de un solo uso).
- ✅ **Recuperación de contraseña** (sin enumeración de usuarios).
- ✅ **2FA TOTP** opcional (implementación propia RFC 6238) integrada en el login.
- ✅ **RBAC** (owner/admin/gestor/estudiante) en backend (middlewares) y vistas.
- ✅ **CSRF**, **rate limiting** por archivo, validación cliente/servidor, escape XSS.
- ✅ **Auditoría inmutable** encadenada por hash (`audit_log`).
- ✅ **i18n** propio en **es/ca/en/pt** (toda cadena traducida).
- ✅ **Toasts** en zona superior central (mensajes flash) + diseño con design tokens IEM.
- ✅ Layout, página de inicio, paneles base (estudiante/gestión), `Ajustes → Sistema`.
- ✅ **Instalador web paso a paso** aislado (PHP+PDO): bienvenida/modo, requisitos,
  BD (probar conexión), migraciones, configuración, integraciones, admin, finalización;
  escribe `.env` (0600) e `installed.lock`; multilingüe; log en `storage/logs/install.log`.
- ✅ Rama **"Restaurar / Migrar desde paquete"** en el Paso 0 (importación básica de .zip).
- ✅ CLI (`migrate`, `seed`, `cron`, `make:admin`) y datos de ejemplo (seed).
- ✅ Tests de lógica crítica (24 casos, SQLite en memoria) y lint sin errores.

### Fase 2 — Campos dinámicos + configuración de plataforma ✅
- ✅ Motor de **definición de campos dinámicos** (`FieldDefinition` + `FieldService`):
  tipos text/textarea/email/tel/number/date/select/radio/checkbox, etiqueta/ayuda/placeholder
  multiidioma, validaciones (min/max/regex), orden, sección, requerido/activo, campos de
  sistema protegidos. Render a HTML, validación y almacenamiento de valores por entidad.
- ✅ CRUD de campos desde el panel (`FieldsController` + vistas, multiidioma con pestañas).
- ✅ Configuración en caliente (`Settings`, tabla `settings`): formas de pago (Stripe/Bizum/
  transferencia), idioma por defecto, retención de datos.
- ✅ Pantalla `Ajustes → Integraciones`: edición de SMTP/Stripe/Git escribiendo `config/.env`
  en caliente (`EnvWriter`), con secretos preservados y **email de prueba** (AJAX).
- ✅ **Textos legales versionados** (`LegalDocument`): privacidad/términos/cancelación, nueva
  versión publicada por idioma desde el panel.
- ✅ **Consentimientos versionados** (`ConsentService`, tabla `consents`) con timestamp e IP;
  registrados automáticamente en el alta; detección de "versión vigente no aceptada".
- ✅ Subnav de administración, seed de campos y texto legal de ejemplo, tests ampliados
  (41 casos en total) y verificación de render de todas las vistas nuevas.

## Bloque B — Catálogo + Preinscripción + Paneles (Fases 3-6) ⬜
- ⬜ Catálogo: cursos, convocatorias/ediciones, aforo, lista de espera, periodos,
  requisitos documentales, prerrequisitos.
- ⬜ Asistente de preinscripción multipaso (menor/tutor, guardar y reanudar).
- ⬜ Panel de Estudiante (preinscripciones, documentos, ficha, derechos RGPD).
- ⬜ Flujo de aprobación de gestores + máquina de estados + emails.

## Bloque C — Dinero (Fases 7-8) ⬜
- ⬜ Pagos: Stripe, Bizum, transferencia con validación, fraccionado, descuentos/becas, FUNDAE.
- ⬜ Facturación: datos fiscales, facturas/recibos PDF, cancelaciones y reembolsos.

## Bloque D — Comunicación + Datos (Fases 9-11) ⬜
- ⬜ Mensajería interna + editor visual de plantillas (GrapesJS + MJML).
- ⬜ Notificaciones y recordatorios (cron) multiidioma en todos los eventos.
- ⬜ Informes, KPIs, exportación CSV/Excel, visor de auditoría.

## Bloque E — Integraciones + Operaciones (Fases 12-15) ⬜
- ⬜ Certificados PDF + AlexiaEdu (CSV/API) + iCal.
- ⬜ Actualizaciones OTA por Git.
- ⬜ Migración guiada (exportar/importar paquete).
- ⬜ i18n completo, accesibilidad, pulido visual, tests ampliados, seguridad y seed.

---
**Estado:** **Bloque A completado** (Fases 1 y 2) y verificado. A la espera de confirmación
para continuar con el **Bloque B** (catálogo formativo + asistente de preinscripción +
paneles + flujo de aprobación).
