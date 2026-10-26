# Hospital: documentos y traslados

Proyecto educativo con dos módulos: **carga y gestión de documentos hospitalarios** y **gestión y seguimiento de traslados en ambulancia**. Incluye catálogos de pacientes, médicos, personal, flota, destinos y rutas; encuestas; cuentas y preferencias. El seguimiento usa una simulación compartida, sin GPS real. Interfaz en español e inglés, con temas claro y oscuro.

## Requisitos

XAMPP con PHP 8.0 o superior y MariaDB (comprobado con PHP 8.0.28 y MariaDB 10.4.28). Usá el ejecutable `php` de XAMPP; la aplicación no necesita Node ni compilación. Activá las extensiones indicadas en la [guía de instalación](docs/setup.md).

## Instalación y servidor

Iniciá MySQL/MariaDB desde XAMPP y ejecutá desde la raíz del proyecto:

```sh
php backend/php/cli/setup.php
php backend/php/cli/users.php create-admin admin admin@hospital.example 99000001
php backend/php/cli/preflight.php
php -d opcache.enable_cli=1 -S 127.0.0.1:8091 -t public backend/php/dev-router.php
```

La creación del administrador solicita una contraseña propia. Abrí <http://127.0.0.1:8091/>. `setup.php` crea la base, aplica migraciones y genera `backend/php/config/local.php`; conservá ese archivo y su clave en privado. La [guía](docs/setup.md) explica la configuración, Apache, correo y actualizaciones.

## Datos de demostración y simulación

Configurá el enrutador y el geocodificador HTTPS de confianza y activá `route_mapping_enabled` en la configuración privada. Luego, en una base local/de desarrollo:

```sh
php backend/php/cli/demo-data.php --reset
php backend/php/cli/simulate-transfers.php start
php backend/php/cli/simulate-transfers.php worker
```

`--reset` pide confirmación y elimina los datos y archivos anteriores, conservando al administrador principal. Crea personas ficticias, flota, hospitales de Montevideo, rutas, traslados, seis PDF y encuestas con respuestas. Las contraseñas de las cuentas demo se guardan únicamente en `tests/.runtime/demo-data/<base>/credentials.json`, ignorado por Git. El worker se mantiene abierto en otra terminal; detenelo antes de repetir el reset. Estos comandos nunca se ejecutan durante la instalación.

## Roles

Todas las cuentas activas comparten documentos, preferencias y encuestas propias. Los administradores también gestionan encuestas ajenas. Los pacientes y médicos son registros, no cuentas de acceso.

| Rol | Acceso adicional |
| --- | --- |
| `admin` | Todos los módulos y administración de cuentas; hereda todos los permisos. |
| `dispatcher` | Todos los traslados; ambulancias, personal, destinos y rutas. |
| `records_manager` | Pacientes y médicos; consulta de traslados donde integra la tripulación. |
| `staff` | Consulta de traslados donde integra la tripulación. |

El registro queda pendiente hasta su aprobación. Los contratos están en [docs/api-contracts.md](docs/api-contracts.md).

## Pruebas

Sólo para las pruebas: Python 3.10+, Node 20+, npm y Google Chrome. Instalá las herramientas de navegador y seleccioná tu Node:

```sh
npm ci --prefix tests
export HOSPITAL_TEST_NODE="$(command -v node)"
python3 tests/verify_project.py --list
python3 tests/verify_project.py --fleet-browser
```

Las suites usan bases y almacenamiento desechables; requieren permisos SQL de instalación. Credenciales y resultados quedan en `tests/.runtime/`, sin modificar los datos de presentación. La [guía](docs/setup.md#pruebas) incluye instalación limpia, recuperación, documentos, idiomas, temas, demo y permisos.

## Estructura

```text
public/       Páginas PHP, CSS, JavaScript, imágenes y fuentes
backend/      API en api/v1/; lógica, configuración y CLI en php/
db/           Esquema, migraciones SQL y diccionarios JSON
storage/      Archivos privados generados, fuera del directorio público
logs/         Registros locales ignorados
tests/        Suites de producto y fixtures; .runtime/ es privado
docs/         Contratos API, instalación y licencia
```
