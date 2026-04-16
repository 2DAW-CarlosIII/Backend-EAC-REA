## 7.7. Registrar el guard en Laravel

### 7.7.1. Registrar el servicio JWKS

Para que el guard pueda resolver las claves públicas, registramos `VerifierJwksService` como singleton en el contenedor de servicios de Laravel. Esto asegura que se cachean correctamente y se comparte la misma instancia en toda la aplicación.

```php
// app/Providers/AppServiceProvider.php

use App\Services\VerifierJwksService;

public function register(): void
{
    // ... registros previos ...

    $this->app->singleton(VerifierJwksService::class);
}
```

### 7.7.2. Definir el driver de autenticación personalizado "verifier"

Para que Laravel use nuestro `VerifierGuard` cuando se invoque el guard `verifier`, lo registramos en el método `boot()` de `AppServiceProvider` usando `Auth::extend()`. Esto permite que los middlewares `auth:verifier` funcionen automáticamente sin necesidad de cambiar nada en los controladores.

```php
// app/Providers/AppServiceProvider.php

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Driver de autenticación personalizado "verifier" usando VerifierGuard
        Auth::extend('verifier', function ($app, $name, array $config) {
            return new VerifierGuard(
                Auth::createUserProvider($config['provider']),
                $app->make(\Illuminate\Http\Request::class),
                $app->make(VerifierJwksService::class)
            );
        });

        // Blade::if define una nueva directiva condicional @role(...) usable en vistas
        Blade::if...({
            ...
        });
    }
```

### 7.7.3. Configurar `config/auth.php`



```php
// config/auth.php

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],

        // Guard anterior con Sanctum (mantener para desarrollo local sin Connector)
        'sanctum' => [
            'driver'   => 'sanctum',
            'provider' => 'users',
        ],

        // Nuevo guard para producción con el FIWARE Dataspace Connector
        'verifier' => [
            'driver'   => 'verifier',
            'provider' => 'users',
        ],
    ],
```

---

## 7.8. Adaptar las rutas a los nuevos guards

### 7.8.1. API REST (`routes/api.php`)

Las rutas que usan `middleware('auth')` o `middleware('auth:sanctum')` las configuraremos como `middleware('auth:verifier,sanctum')` para que usen el nuevo guard. Esto no cambia nada en los controladores, que siguen usando `auth()->user()` con normalidad.

```php
// routes/api.php

// Antes:
Route::middleware('auth:sanctum')->group(function () { ... });

// Después (usa el guard configurado en API_AUTH_DRIVER):
Route::middleware('auth:verifier,sanctum')->group(function () { ... });
```

> Dejamos que Laravel soporte ambos simultáneamente (útil en el periodo de transición).Laravel probará cada _guard_ en orden y usará el primero que autentique al usuario.

### 7.8.2. Rutas web (`routes/web.php`)

Las vistas Blade usan el guard `web` (sesión). Las rutas que usan `middleware('auth')` o `middleware('auth:web')` las configuraremos como `middleware('auth:verifier,web')` para que usen el nuevo guard.

---

## 7.9. Middleware de rol: sin cambios

El middleware `CheckRole` y la directiva Blade `@role` que se crearon en la Unidad 2 funcionan llamando a `auth()->user()` y comprobando el rol del usuario devuelto. Como el `VerifierGuard` ya resuelve el usuario y sincroniza su rol, **no necesitas cambiar nada en esos middlewares**: simplemente pasan de recibir un usuario de Sanctum a recibir uno de Verifier. El código de tus controladores no se modifica.

---

**Unidad anterior ←** [Unidad 6: Visualización del espacio competencial](./06_visualizacion_espacio_competencial.md)

**Siguiente capítulo →** [7.10. Diagnóstico de la configuración del guard y el servicio JWKS](./07_10_diagnostico_guard_jwks.md)
