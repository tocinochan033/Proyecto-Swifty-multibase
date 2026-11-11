# Swift Invoice

**Swift Invoice** es una aplicación web para la gestión integral de ventas y facturación, enfocada en la administración eficiente y digital de clientes (personas y empresas), productos y comprobantes fiscales.  
Funciona en cualquier servidor local o web con PHP y utiliza MySQL para el almacenamiento seguro de la información.

---

## Estado y guía para continuar

Swift Invoice es una aplicación PHP; la web usa actualmente una sola conexión PDO a MySQL/MariaDB, configurada en [`config/database.php`](./config/database.php) mediante variables `DB_*`. Se añadieron el checker CLI [`scripts/check_database_connection.php`](./scripts/check_database_connection.php) y una receta para abrir con OpenSSH un túnel `-L` sobre Tailscale, que reenvía `localhost:3307` al puerto MySQL/MariaDB del servidor remoto. El código de la aplicación no se conecta a Tailscale ni ejecuta SSH. MongoDB se usa solo en scripts puntuales de respaldos y snapshots CLI: no hay sincronización ni consultas live, ni una segunda base usada por la web. Explorar el acceso a una base remota privada no equivale a tener una arquitectura multibase; esa arquitectura aún debe diseñarse e implementarse.

**Estado verificado:** todos los archivos PHP del proyecto fuera de `vendor/` pasaron `php -l`. El checker existe y es exclusivamente CLI; al solicitarlo por HTTP devolvió 404, sin conexión a la base de datos. No se ejecutó exitosamente el checker CLI ni se probó una conexión real al tailnet o a la base remota, por lo que la aplicación completa aún no está validada. En el entorno revisado —estos datos no describen necesariamente todos los despliegues— PHP era 8.5.4, faltaba `ext-mongodb ^2.5` y `composer.lock` mantiene mPDF 8.2.0, incompatible con PHP 8.5.

**Siguientes pasos (en orden):**

1. Levantar el proyecto localmente, verificar los prerequisitos y resolver/validar `composer.lock` y las dependencias con Composer.
2. Con el owner, validar acceso SSH/OpenSSH y Tailscale; confirmar que la base SQL remota está importada y que el usuario tiene los privilegios necesarios.
3. Abrir el túnel descrito más abajo y ejecutar [`scripts/check_database_connection.php`](./scripts/check_database_connection.php).
4. Decidir la arquitectura multibase, implementarla y validar los flujos que dependan de ella.
5. Dejar pruebas reproducibles y actualizar la documentación con los resultados.

**Checklist de handoff**

- [ ] Verificar prerequisitos y dependencias locales.
- [ ] Validar el túnel y la conexión a la base remota con el owner.
- [ ] Acordar, implementar y probar la arquitectura multibase.
- [ ] Documentar pruebas, límites y siguientes tareas.

---

## 🧩 **Características**

- Administración de clientes: personas físicas y empresas, con información fiscal.
- Registro y edición de ventas, asignando cliente y productos.
- Generación de facturas o comprobantes en PDF con diseño profesional.
- Control y configuración de impuestos por producto o venta.
- Catálogo de productos y servicios con precios y descripciones.
- Historial y búsqueda de ventas, clientes y productos.
- Reportes básicos para consulta y análisis.
- Enfoque **paperless**: reduce uso de papel y facilita la digitalización de procesos.
- Estructura modular, fácil de mantener y expandir.
- Compatible con cualquier entorno PHP/MySQL (XAMPP, WAMP, servidores en la nube).

---

## ⚙️ **Requisitos**

- PHP 8.5.x
- Extensiones PHP `pdo_mysql`, `mongodb` `^2.5`, `gd` y `mbstring`; para limpiar y usar las dependencias también se requieren `ctype`, `dom`, `fileinfo`, `iconv`, `libxml`, `simplexml`, `xml`, `xmlreader`, `xmlwriter`, `zip` y `zlib`
- MySQL o MariaDB
- Composer, para gestionar las dependencias si no está disponible el directorio `vendor/`
- `ext-mongodb ^2.5` es un requisito de plataforma de `mongodb/mongodb`, aunque la extensión no sea necesaria en runtime para las páginas web principales

---

## 🚀 **Instalación**

1. Clona o descarga el proyecto. El checkout actual incluye `vendor/`, pero sus binarios y versiones instaladas pueden no coincidir con la versión de PHP actual. Además, `composer.lock` todavía fija `mpdf/mpdf` 8.2.0, incompatible con PHP 8.5; por ello, `composer install` no es reproducible en PHP 8.5 hasta actualizar y validar el lock. Como tarea previa de mantenedor, en PHP 8.5 y con todos los requisitos de plataforma instalados, ejecuta desde la raíz:

   ```sh
   composer update mpdf/mpdf --with-dependencies
   composer install
   composer check-platform-reqs
   ```

   No se deben ignorar los requisitos de plataforma.
2. Crea la base de datos e importa el esquema. En Linux/macOS, desde el directorio padre del checkout (aquí se llama `Proyecto-Swifty-multibase`), ejecuta:

   ```sh
   mysql -u root -p -e "CREATE DATABASE swift_invoice_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   mysql -u root -p swift_invoice_db < Proyecto-Swifty-multibase/script.sql
   ```

   Si tu usuario MySQL no tiene contraseña, omite `-p`; si usas otras credenciales, sustituye `root` por tu usuario. La cuenta necesita privilegios para crear las tablas y la rutina. Crear los triggers requiere el privilegio `TRIGGER` sobre las tablas o la base de datos, según la versión y el motor MySQL/MariaDB. Los triggers de `script.sql` no declaran explícitamente un `DEFINER`, así que su definidor será la cuenta que los importe. Por separado, el procedimiento declara `DEFINER=root@localhost`: crearlo y atribuirlo a esa cuenta requiere los privilegios correspondientes, que varían según la versión de MySQL/MariaDB. Si ajustas ese `DEFINER` a otra cuenta, asegúrate de que tenga los permisos necesarios para ejecutar la consulta de la rutina. Para una instalación local, importa con la cuenta administrativa correspondiente o ajusta la definición del `DEFINER` apropiadamente. `script.sql` contiene el esquema y los catálogos, no cuentas ni transacciones de ejemplo. La configuración de desarrollo predeterminada usa `localhost:3306`, `swift_invoice_db` y `root` sin contraseña.
3. Las rutas de la aplicación están fijadas al prefijo `/swift_invoice`. No hace falta renombrar ni reemplazar el checkout. Si el directorio del repositorio tiene otro nombre, crea desde su directorio padre un enlace simbólico con el nombre requerido, comprobando antes que no exista un archivo, directorio o enlace llamado `swift_invoice`:

   ```sh
   cd /ruta/al/directorio-padre
   test -d Proyecto-Swifty-multibase
   test ! -e swift_invoice && test ! -L swift_invoice
   ln -s Proyecto-Swifty-multibase swift_invoice
   ```

   En XAMPP, el equivalente es publicar el checkout en `htdocs/swift_invoice` mediante un alias de Apache. Desde ese mismo directorio padre puedes iniciar el servidor integrado de PHP:

   ```sh
   php -S 127.0.0.1:8000
   ```

   La aplicación web queda en `http://127.0.0.1:8000/swift_invoice`. El servidor integrado es solo para uso local: no lee `.htaccess`, no procesa `mod_rewrite` y no debe exponerse a la red. Para API y uso en XAMPP, usa Apache con `mod_rewrite` activo y `AllowOverride All` habilitado para el directorio del proyecto; Apache aplica además las reglas de protección de archivos sensibles definidas en `.htaccess`.

4. Abre [http://127.0.0.1:8000/swift_invoice](http://127.0.0.1:8000/swift_invoice). Si usas un alias virtual, configúralo para que publique la aplicación bajo `/swift_invoice`.

## API REST

La especificación OpenAPI está en [`openapi.yaml`](./openapi.yaml). En XAMPP, ubica el proyecto en `htdocs/swift_invoice`. Con Apache, `mod_rewrite` activo y `AllowOverride All` habilitado para el directorio, la API se sirve bajo `/swift_invoice/api/v1`:

- `GET /auth/csrf` entrega un token CSRF y establece la cookie de sesión.
- `POST /auth/login` inicia sesión; envía JSON y el token en `X-CSRF-Token`.
- `POST /auth/logout` cierra la sesión; requiere cookie y token CSRF.
- `/clients`, `/companies` y `/products` tienen operaciones de lectura y CRUD protegidas por sesión; las mutaciones requieren CSRF.
- `/sales` permite listar, consultar, crear y eliminar ventas. Los precios se obtienen del catálogo del servidor.
- `/invoices` permite listar y consultar facturas.
- `/catalogs/business-types` y `/catalogs/tax-regimes` exponen catálogos de solo lectura.

Las listas aceptan `limit` (1–100, predeterminado 20) y `offset` (predeterminado 0). La paginación se informa en `X-Total-Count`, `X-Limit` y `X-Offset`. Los errores usan una respuesta JSON común y códigos HTTP específicos.

La autenticación de la API utiliza la sesión PHP de la aplicación; no es una API de tokens para clientes externos. No se habilita CORS. Las peticiones de escritura deben conservar la cookie y enviar el token CSRF recibido para esa sesión. Las sesiones vencen tras 30 minutos de inactividad y los cuerpos JSON están limitados a 1 MiB.

## Respaldos completos en MongoDB Atlas

### Arquitectura multibase actual

La aplicación web usa una base MySQL/MariaDB para sus operaciones. MongoDB se usa únicamente desde scripts CLI para respaldos y snapshots puntuales; no hay sincronización en tiempo real ni consultas web live a MongoDB.

### Practicar con MySQL/MariaDB remoto por Tailscale

Este procedimiento crea un túnel TCP local con OpenSSH. Tailscale proporciona la red privada entre los dispositivos: PHP no abre SSH ni necesita conectarse a Tailscale por sí mismo. No expongas MySQL a Internet; limita el firewall y las ACL/grants de Tailscale al acceso necesario.

1. Instala Tailscale en el equipo donde ejecutarás Swift Invoice y en el servidor que contiene MySQL/MariaDB, e inicia sesión en ambos con las cuentas autorizadas del mismo tailnet. En Linux, instala el paquete siguiendo la documentación oficial de Tailscale y ejecuta `sudo tailscale up`; en macOS, instala la aplicación oficial e inicia sesión desde ella. Confirma la conectividad con `tailscale status` en Linux/macOS. Autoriza los dispositivos/usuarios en el tailnet y revisa la política de acceso (ACL o grants) para permitir solo el tráfico requerido. Configura además la política/autoridad SSH pertinente si se usa Tailscale SSH. La pertenencia al tailnet no concede por sí sola acceso a MySQL ni a SSH.
2. En el servidor remoto, deja MySQL/MariaDB escuchando solo en `127.0.0.1:3306` (por ejemplo, con `bind-address=127.0.0.1`) y configura un usuario de base de datos con los permisos mínimos y acceso local desde el host remoto. No abras ni reenvíes el puerto 3306 en el router o firewall hacia Internet. Permite únicamente el SSH necesario desde el tailnet mediante el firewall y la política de acceso.
3. Asegura que el servidor remoto tenga un daemon OpenSSH accesible por la dirección/nombre Tailscale del host y que la cuenta de sistema tenga autorización. El SSH administrado de Tailscale puede no permitir el reenvío de puertos; para la opción `-L` usa el daemon OpenSSH del servidor accesible por tailnet y una política/firewall que autorice ese acceso. Desde el equipo de Swift Invoice, abre un terminal y ejecuta:

   ```sh
   ssh -N -L 127.0.0.1:3307:127.0.0.1:3306 usuario@host-tailscale
   ```

   Sustituye `usuario` y `host-tailscale` por la cuenta y el nombre/IP Tailscale correspondientes. Deja ese terminal abierto mientras uses la aplicación. La escucha local se limita intencionalmente a `127.0.0.1`.
4. En otro terminal, desde la raíz del checkout, configura las mismas variables de entorno que consume `config/database.php`. Usa los valores propios de tu entorno; no guardes contraseñas en el repositorio ni las escribas en estos ejemplos. Para evitar incluir la contraseña en el historial del shell, puedes solicitarla sin eco:

   ```sh
   export DB_HOST=127.0.0.1
   export DB_PORT=3307
   export DB_NAME='nombre_de_base'
   export DB_USER='usuario_de_base'
   read -r -s DB_PASSWORD
   export DB_PASSWORD
   printf '\n'
   php scripts/check_database_connection.php
   ```

   El script es solo CLI, reutiliza `Database`, ejecuta `SELECT VERSION(), DATABASE()` y no imprime credenciales. Si falla con PDO, informa un error entendible y termina con código distinto de cero.
5. Para iniciar la aplicación web con esas mismas variables, en esa misma sesión de shell, ve al directorio padre donde creaste el enlace `swift_invoice` y ejecuta `php -S 127.0.0.1:8000`. Los procesos PHP del servidor integrado heredan el entorno exportado; accede a `http://127.0.0.1:8000/swift_invoice`. El servidor integrado es únicamente para pruebas locales. Con Apache u otro gestor, configura esas variables en el entorno de los procesos PHP correspondientes: exportarlas en una terminal distinta no las transmite al servidor ya iniciado.

La conexión reutilizable [`getMongoDatabase`](./config/mongodb.php) usa la librería oficial `mongodb/mongodb`. La base de datos de Atlas predeterminada es `swift_invoice` y puede cambiarse con `MONGODB_DATABASE`. Para respaldar la base MySQL completa, el script CLI [`create_mongodb_backup.php`](./scripts/create_mongodb_backup.php) ejecuta `mysqldump` incluyendo estructura, datos, rutinas, triggers y eventos, y almacena el archivo `.sql` en MongoDB GridFS (`database_backups`, configurable con `MONGODB_BACKUP_BUCKET`). GridFS permite guardar respaldos mayores que el límite de 16 MB de un documento BSON. Es un respaldo manual puntual, no una réplica ni espejo en tiempo real: los cambios realizados después del respaldo no se reflejan en Atlas hasta la siguiente ejecución. El snapshot anterior de solo usuarios sigue disponible por separado en [`create_mongodb_snapshot.php`](./scripts/create_mongodb_snapshot.php); no lo uses como respaldo completo.

Habilita la extensión PHP `mongodb` compatible con tu versión de PHP e instala `mysqldump` (XAMPP incluye `C:\xampp\mysql\bin\mysqldump.exe`; el script lo detecta automáticamente en la instalación estándar). Configura las variables de MongoDB en el entorno de PHP o en el archivo `.env` de la raíz, y las variables de MySQL en el entorno del proceso PHP:

- `MONGODB_URI`: cadena de conexión de Atlas, incluyendo usuario y contraseña.
- `MONGODB_DATABASE`: base de datos de destino (opcional; predeterminada `swift_invoice`).
- `MONGODB_BACKUP_BUCKET`: bucket GridFS de respaldos (opcional; predeterminado `database_backups`).
- `MYSQLDUMP_BIN`: ruta al ejecutable `mysqldump` si no está en `PATH` (por ejemplo, `C:\xampp\mysql\bin\mysqldump.exe` en XAMPP).
- `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER` y `DB_PASSWORD`: configuración de la base MySQL que se respaldará.

La conexión MySQL usa `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER` y `DB_PASSWORD`; en desarrollo, si no se configuran, usa `localhost:3306`, `swift_invoice_db`, usuario `root` y contraseña vacía. Ejecuta el respaldo manual desde la raíz del proyecto:

```sh
php scripts/create_mongodb_backup.php
```

Cada ejecución agrega un respaldo nuevo e imprime su ID de GridFS. Descárgalo sustituyendo `<ID>` por ese identificador:

```sh
php scripts/download_mongodb_backup.php <ID> respaldo.sql
```

Para restaurarlo, usa una instancia MySQL/MariaDB de recuperación y el cliente de la misma instalación (por ejemplo, en `cmd.exe`):

```cmd
C:\xampp\mysql\bin\mysql.exe --user=USUARIO --password < respaldo.sql
```

El dump contiene la estructura, datos y rutinas de esa base; no incluye usuarios/permisos globales del servidor MySQL. Protege los permisos de Atlas: los respaldos contienen todos los datos, incluidas credenciales y hashes almacenados en MySQL. No publiques el `.env` ni la URI/contraseñas. El script usa un archivo temporal de credenciales para `mysqldump` y lo elimina al terminar. Ejecuta periódicamente el comando según la frecuencia de respaldo que necesites; no se configura ni ejecuta automáticamente.

## Configuración de producción y seguridad

- Configura `APP_ENV=production`, `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER` y `DB_PASSWORD` en el entorno de Apache/PHP. En producción, la aplicación no acepta las credenciales locales predeterminadas.
- Usa un usuario MySQL dedicado con los permisos mínimos necesarios; no uses `root`.
- Publica el sitio solo por HTTPS. Configura PHP para `display_errors=Off` y `log_errors=On`, y protege los archivos de log.
- Las cookies de sesión usan `HttpOnly` y `SameSite=Lax`; se marcan `Secure` y se envía HSTS cuando PHP detecta una conexión HTTPS.
- El registro público se desactiva por defecto cuando `APP_ENV=production`. Para crear la primera cuenta, habilita `ALLOW_REGISTRATION=true` solo durante una ventana controlada y vuelve a desactivarlo al terminar.
- El formulario de registro está en [`auth/register.php`](./auth/register.php) y solicita correo electrónico, contraseña, confirmación de contraseña y aceptación explícita del aviso. Valida el formato y la disponibilidad del correo, exige una contraseña de al menos 8 caracteres con mayúsculas, minúsculas, números y símbolos, y muestra un indicador de fuerza (débil, media o segura). La contraseña permanece oculta mientras se escribe. La casilla de privacidad está desmarcada por defecto y es obligatoria; enlaza al [`Aviso de Privacidad`](./auth/privacy.php), que se abre en otra pestaña. Configura `PRIVACY_CONTROLLER_NAME` y `PRIVACY_CONTACT_EMAIL` con la identidad y el contacto reales de quien administra cada instalación. Las obligaciones y los plazos de conservación deben ajustarse a la jurisdicción aplicable.
- El límite de intentos de inicio de sesión usa la tabla `auth_login_attempts`. En instalaciones existentes ejecuta una vez [`20261104_auth_login_attempts.sql`](./database/migrations/20261104_auth_login_attempts.sql); las instalaciones nuevas la crean al importar `script.sql`.
- Los archivos `.htaccess` protegen rutas y archivos en Apache. Si se usa Nginx u otro servidor, aplica reglas equivalentes y habilita el enrutamiento de `/api/v1`.
- Los usuarios autenticados comparten los mismos permisos; el esquema actual no implementa roles. Antes de ofrecer acceso a varios perfiles, agrega autorización por rol y propiedad de datos.
- El volcado ya no incluye registros de usuarios, clientes o transacciones, pero borrar esos registros del archivo actual no los elimina de commits anteriores. Si el repositorio estuvo compartido, limpia el historial de forma coordinada y gestiona las credenciales/datos expuestos.

Estas medidas reducen riesgos comunes, pero no permiten garantizar que una aplicación sea “inhackeable”. Mantén PHP, dependencias y servidor actualizados, y realiza pruebas de seguridad antes de publicar el sistema.
