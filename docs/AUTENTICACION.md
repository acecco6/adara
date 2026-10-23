# Acceso a Adara Gestión

Adara Gestión usa el guard `web` de Laravel y sesiones con almacenamiento en base de datos. La primera cuenta es la administradora de la instalación. Hay una pantalla pública de registro; cada cuenta nueva queda pendiente y solo se habilita modificando `users.is_active` en la base de datos. No hay recuperación por correo ni acceso de terceros.

## Crear la primera cuenta

Ejecutá el comando desde una terminal interactiva dentro del entorno que apunta a la base de datos correcta. Solo funciona mientras la tabla `users` esté vacía. Solicita nombre, email y contraseña; las dos entradas de contraseña se ocultan.

En Windows PowerShell:

```powershell
php artisan adara:admin:create
```

En macOS Terminal:

```bash
php artisan adara:admin:create
```

La contraseña requiere al menos 12 caracteres, mayúsculas y minúsculas, un número y un símbolo. No se imprime ni se guarda en los logs del comando.

## Registro y activación manual

Desde **Crear cuenta**, una persona puede ingresar nombre, email y contraseña. El registro guarda `is_active = 0` y no inicia sesión. La pantalla de login muestra que la cuenta quedó pendiente.

La activación se realiza únicamente desde la base de datos, por ejemplo editando la fila correspondiente en DBeaver: en la tabla `users`, identificá la cuenta por su email y cambiá `is_active` de `0` a `1`. No hay botón, ruta ni comando de activación en la aplicación. Cambiarlo de `1` a `0` impide nuevos inicios de sesión y cierra una sesión activa cuando la persona vuelve a acceder.

Todas las cuentas activadas tienen acceso a las mismas pantallas; esta versión no define roles. Si alguien se registra antes de crear la cuenta inicial, `adara:admin:create` ya no podrá usarse porque la tabla tendrá un usuario: activá esa cuenta desde la base de datos.

La migración que agrega `is_active` conserva activas las cuentas que ya existían; las cuentas registradas después comienzan pendientes. Aplicá la migración normal de Laravel en el entorno correspondiente antes de usar la pantalla de registro.

## Restablecer una contraseña

En una terminal interactiva del entorno configurado:

```text
php artisan adara:admin:reset-password
```

Si hay una única cuenta, se selecciona automáticamente. Si hay más de una, indicá el email:

```text
php artisan adara:admin:reset-password nombre@ejemplo.com
```

El comando exige `SESSION_DRIVER=database`, cambia la contraseña y el token de “recordarme”, y revoca las sesiones activas de esa cuenta. Si la base de datos no tiene la tabla configurada para sesiones, se detiene sin cambiar la contraseña.

## Elegir el entorno con cuidado

Ambos comandos actúan sobre la base de datos configurada para el proceso que los ejecuta. Verificá primero el entorno y la conexión. En particular, no ejecutes estos comandos desde una copia local que tenga configurada la base de producción, salvo que quieras operar esa cuenta de producción intencionalmente.

Para Railway, ejecutá el comando en la terminal del servicio de la aplicación una vez desplegada la versión que incluye estos comandos. No lo ejecutes en la base de producción desde una terminal local por accidente.

## Uso

- Abrí `/login` y usá el email y la contraseña de una cuenta activada.
- Después de iniciar sesión, las páginas de negocio y las acciones Livewire requieren una sesión autenticada.
- Elegí **Cerrar sesión** en la navegación para salir; las sesiones se invalidan al cerrar sesión.
- El límite es de cinco intentos fallidos por email y dirección IP durante un minuto. Los errores de credenciales no revelan si existe una cuenta.
