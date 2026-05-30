# IEM Preinscripciones

Plataforma web de **preinscripciones, inscripciones y matriculación** para un centro de
formación sanitaria (Institut d'Estudis Mèdics). PHP autoinstalable, multiidioma
(es/ca/en/pt), con instalador web paso a paso, actualización OTA por Git y migración guiada
entre servidores.

> Estado: **Fase 1 (cimientos + instalador) completada.** Ver `PLAN.md` para el roadmap y
> `CLAUDE.md` para el detalle técnico.

## Requisitos
- PHP ≥ 8.2 con extensiones `pdo_mysql, mbstring, openssl, json, curl, fileinfo, zip,
  gd|imagick, xml, intl`.
- MySQL 5.7+/MariaDB.
- (Opcional) `git` y `mysqldump` para OTA y migración; si no están, la plataforma usa
  fallbacks 100% PHP (apta para hosting compartido).

## Instalación
1. Sube los archivos al servidor (apunta el DocumentRoot a `public/`; en hosting compartido
   el `.htaccess` de la raíz reenvía a `public/`).
2. Asegura permisos de escritura en `storage/`, `public/uploads/` y `config/`.
3. Abre la web en el navegador: serás redirigido al **instalador** (`/install/`).
4. Sigue los pasos (idioma, requisitos, base de datos, migraciones, configuración,
   integraciones, usuario administrador) y finaliza.
5. Por seguridad, elimina o bloquea la carpeta `public/install/`.

## Desarrollo
```bash
php -S localhost:8000 -t public public/router.php   # servidor de desarrollo
php cli/console.php migrate                          # migraciones
php cli/console.php seed                             # datos de ejemplo
php tests/run.php                                    # tests (sin MySQL ni Composer)
```

## Licencia
Software propietario — Institut d'Estudis Mèdics. Ver `LICENSE`.
