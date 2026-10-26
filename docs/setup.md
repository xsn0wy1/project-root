# Instalación con XAMPP

Todos los comandos se ejecutan desde la raíz del proyecto. Se comprobó PHP 8.0.28 y MariaDB 10.4.28 de XAMPP. No hay compilación de frontend ni dependencias Node para ejecutar la aplicación.

## Preparar PHP y MariaDB

Iniciá el servicio MySQL/MariaDB desde el administrador de XAMPP. Usá el PHP de esa instalación. En macOS podés seleccionarlo para esta terminal:

```sh
export PATH="/Applications/XAMPP/xamppfiles/bin:$PATH"
php --version
```

En Windows usá `C:\xampp\php\php.exe` o agregá `C:\xampp\php` al PATH. PHP necesita `pdo_mysql`, `mbstring`, `gd`, `zip`, `dom`, `curl` y `fileinfo`. El envío de recuperación de contraseña también necesita ejecutar el worker PHP CLI y acceder al almacenamiento privado.

## Base de datos y administrador

```sh
php backend/php/cli/setup.php
php backend/php/cli/users.php create-admin admin admin@hospital.example 99000001
php backend/php/cli/preflight.php
```

`setup.php` carga `db/sql/schema.sql`, aplica las migraciones pendientes de `db/sql/migrations/`, crea una cuenta SQL de aplicación con permisos de datos, genera contraseña y clave cuando faltan, y escribe `backend/php/config/local.php` con acceso sólo para su propietario. También prepara el almacenamiento privado. No crea cuentas web ni datos demo; `create-admin` solicita una contraseña de al menos 12 caracteres y aprueba al primer administrador. No existe una contraseña predeterminada.

La conexión administrativa usa `HOSPITAL_SETUP_DB_USER` (por defecto `root`) y `HOSPITAL_SETUP_DB_PASSWORD` (vacía por defecto). Si tu instalación usa otros valores, definilos en el entorno. `HOSPITAL_DB_HOST`, `HOSPITAL_DB_PORT` y `HOSPITAL_DB_SOCKET` seleccionan el servicio. En macOS, el socket habitual de XAMPP es `/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock`.

Antes del primer setup podés elegir `HOSPITAL_DB_NAME` y `HOSPITAL_DB_USER` para una instalación independiente. Usá una cuenta SQL dedicada, nunca `root` como usuario de aplicación. Una cuenta SQL preexistente necesita su contraseña correspondiente; setup no la reemplaza. La referencia completa es [backend/php/config/example.php](../backend/php/config/example.php); no reutilices sus secretos de ejemplo.

El archivo privado existente se conserva; las variables `HOSPITAL_*` tienen prioridad. `setup.php --no-config` evita escribir el archivo local; la carga conserva la prioridad habitual de las variables de entorno. Guardá copias de la base, `storage/` y la configuración con su `app_key`: cambiar la clave puede invalidar enlaces de acceso existentes. Nunca publiques estos archivos ni los incluyas en Git.

Para actualizar una instalación existente, con sus credenciales administrativas disponibles:

```sh
php backend/php/cli/migrate.php
php backend/php/cli/preflight.php
```

Las demás cuentas se registran en la web y quedan pendientes. El administrador puede aprobarlas en Cuentas o mediante:

```sh
php backend/php/cli/users.php pending
php backend/php/cli/users.php approve usuario staff
```

Los roles disponibles son `admin`, `dispatcher`, `records_manager` y `staff`; `admin` tiene todos los permisos. Las cuentas pertenecen al personal, no a los registros de pacientes o médicos.

## Servidor local

```sh
php -d opcache.enable_cli=1 -S 127.0.0.1:8091 -t public backend/php/dev-router.php
```

Abrí <http://127.0.0.1:8091/>. El router sirve `/api/v1/`, archivos estáticos y vistas PHP; un servidor estático no ejecuta la aplicación. Mantené la terminal abierta y usá Ctrl+C para detenerla. `public/` es el único directorio público.

Para Apache usá [backend/apache/hospital.conf.example](../backend/apache/hospital.conf.example), reemplazando `/ABSOLUTE/PATH/project-root` por la ruta de instalación. La raíz pública apunta a `public/` y el Alias `/api/v1/` a `backend/api/v1/`; el resto permanece privado. Apache necesita PHP, `mod_alias`, `mod_rewrite` y las reglas `.htaccess` permitidas por la plantilla. Verificá su configuración antes de reiniciar y los permisos de lectura/escritura bajo el usuario real de Apache. No expongas la raíz completa del repositorio desde otro sitio de `htdocs`.

Para montar en `/hospital`, configurá `base_path` como `/hospital`, `app_url` con esa raíz y ambos Alias (`/hospital/api/v1/` antes de `/hospital/`). Ajustá también los destinos de ErrorDocument y Location de la configuración Apache. Activá `secure_cookies` al usar HTTPS; el HTTP local requiere `false`.

## Correo y rutas

La recuperación usa Brevo: configurá `brevo_api_key`, `mail_from_email`, `mail_from_name` y una `app_url` correcta en privado, o sus variables `HOSPITAL_*`. El remitente debe estar autorizado por el proveedor. `mail_php_binary` permite elegir el ejecutable PHP CLI del worker. `preflight.php` comprueba sus capacidades locales; la recepción real se verifica con el proveedor. Los trabajos temporales quedan en `storage/mail/`; `php backend/php/cli/send-reset-mail.php` procesa los pendientes.

La consulta de direcciones y rutas está desactivada por defecto. Para usarla, configurá `route_mapping_enabled=true`, `geocoder_url` y `router_url` con endpoints HTTPS de confianza, y el país `UY`. Las variables equivalentes son `HOSPITAL_ROUTE_MAPPING_ENABLED`, `HOSPITAL_GEOCODER_URL` y `HOSPITAL_ROUTER_URL`. El router espera la API de rutas de OSRM. La carga demo exige que el router responda y que cada ruta tenga más de 20 puntos; los destinos ya llevan coordenadas, por lo que no necesitan geocodificación.

## Demostración

Con el administrador creado, las rutas configuradas y una base cuyo nombre identifique un entorno local/dev/demo/test:

```sh
php backend/php/cli/demo-data.php --reset
```

El reset requiere confirmación (o `--yes` explícito), conserva al administrador principal y elimina el resto de los datos y sus archivos. Crea 15 pacientes, 8 médicos, 6 conductores, 4 copilotos, 7 ambulancias, 6 hospitales reales de Montevideo, rutas, 12 traslados, 6 PDF ficticios y 3 encuestas; dos publicadas tienen respuestas sintéticas. También crea un dispatcher y un records manager. Las personas y documentos son ficticios.

Las contraseñas generadas se escriben en `tests/.runtime/demo-data/<nombre-de-base>/credentials.json` con acceso privado. Cada reset las reemplaza; no se imprimen ni se ejecuta el seeder durante setup o migración. Para empezar sin eliminar registros, omití `--reset`; se rechaza una base ya poblada.

En otra terminal, con la misma configuración:

```sh
php backend/php/cli/simulate-transfers.php start
php backend/php/cli/simulate-transfers.php worker
```

El worker comparte posiciones simuladas entre todos los navegadores y no usa GPS. Mantenelo abierto durante la presentación y detenelo con Ctrl+C antes de un reset. `php backend/php/cli/simulate-transfers.php status` consulta su estado.

## Pruebas

Se necesitan Python 3.10+, Node 20+, npm, Google Chrome y privilegios SQL para crear y eliminar bases/usuarios desechables. Usá PHP de XAMPP; `HOSPITAL_TEST_PHP` permite indicar su ruta. Las pruebas no modifican la configuración ni los datos normales.

```sh
npm ci --prefix tests
export HOSPITAL_TEST_NODE="$(command -v node)"
python3 tests/verify_project.py --list
python3 tests/verify_project.py --fleet-browser
```

`--modules auth,settings` selecciona módulos; sin ese filtro se ejecutan las 16 suites registradas. `--fleet-browser` añade la suite de ambulancias antes de limpiar su entorno. `--output tests/.runtime/mi-ejecucion` conserva evidencia privada. En PowerShell usá `$env:HOSPITAL_TEST_NODE = (Get-Command node).Source` para seleccionar Node.

Otras suites de producto con preparación y limpieza propias:

```sh
python3 tests/install_integration.py --browser
python3 tests/document_links_integration.py --browser
python3 tests/password_recovery_integration.py --browser
```

Las suites de navegador restantes comparten los fixtures de `theme_environment.py` e `i18n_browser_fixtures.py`. Prepará un entorno privado para probar temas y las correcciones de permisos/interfaz:

```sh
export HOSPITAL_THEME_TEST_DIR="$PWD/tests/.runtime/browser/environment"
python3 tests/theme_environment.py prepare
python3 tests/error_pages_integration.py --html
node tests/theme_browser.cjs --baseline
node tests/theme_browser.cjs
node tests/permissions_ui_checks.cjs
python3 tests/permissions_fixtures.py
HOSPITAL_DEMO_BROWSER_FIXTURE="$PWD/tests/.runtime/browser/role-fixture.json" node tests/demo_data_browser.cjs
python3 tests/theme_environment.py cleanup
```

Los dos pasos de `theme_browser.cjs` comparan la geometría de los temas de la aplicación actual. `permissions_fixtures.py` comprueba accesos y prepara cuatro roles; `demo_data_browser.cjs` recorre las vistas para cada uno. Eliminá también `tests/.runtime/browser/role-fixture.json` tras la ejecución. Las fixtures de demo se verifican antes de limpiar con `python3 tests/demo_data.py --environment "$HOSPITAL_THEME_TEST_DIR/env.json"`; esa prueba usa OSRM por HTTPS, exclusivamente sobre la base desechable, y ejecuta resets reales. Nunca le pases el entorno de presentación.

Los demás navegadores cubren idiomas/primer pintado, dropdowns, preferencias/notificaciones, mapas, documentos y recorridos de traslados/encuestas; sus cabeceras indican las variables de fixtures. `ui_browser.cjs` genera `rendered-pages.json` para `python3 tests/i18n_coverage.py --rendered-pages RUTA`. Las credenciales, tokens, capturas y logs quedan en `tests/.runtime/`, ignorado por Git. Los tests no forman parte del funcionamiento de la web.
