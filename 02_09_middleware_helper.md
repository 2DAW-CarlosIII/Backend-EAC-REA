## 2.9. Middleware de rol y helper Blade

### Objetivo

Este documento explica cómo controlar el acceso por roles en la aplicación Laravel mediante:

- un middleware (`EnsureUserHasRole`) que protege rutas y verifica el rol del usuario autenticado;
- un método helper `hasRole()` en el modelo `User` para consultar roles desde código PHP;
- una directiva Blade `@role` que permite condicionales en vistas.

### Middleware

El middleware se encarga de **interceptar la petición antes de que llegue al controlador** y comprobar si el usuario autenticado dispone del rol requerido. Si no lo tiene, devuelve un error 403.

```bash
php artisan make:middleware EnsureUserHasRole
```

```php
// app/Http/Middleware/EnsureUserHasRole.php

    // El handle recibe la petición, el siguiente middleware y el nombre del rol a comprobar
    public function handle(Request $request, Closure $next, string $role): Response
    {
        if (! $request->user()?->userRoles()->where('name', $role)->exists()) {
            abort(403, 'Acceso no autorizado para este rol.');
        }

        return $next($request);
    }
```

Regístralo en `bootstrap/app.php` (Laravel 12). El registro crea un alias ('role') que puedes usar en rutas o controladores.

```php
// En bootstrap/app.php, dentro del builder de la app
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureUserHasRole::class,
        ]);
    })
```

### Helper `hasRole()` en el modelo User

Propósito: proporcionar una forma clara y reutilizable de comprobar si un usuario tiene un rol concreto desde cualquier parte del código PHP (controladores, servicios, middleware adicional, tests...).

```php
// app/Models/User.php  (añadir)

// Método helper que consulta la relación roles y devuelve true/false
public function hasRole(string $role): bool
{
    // Se usa la relación 'roles' definida en el modelo User
    return $this->userRoles()->where('name', $role)->exists();
}
```

Uso típico en código PHP:

```php
if (auth()->user() && auth()->user()->hasRole('docente')) {
    // lógica accesible solo a docentes
}
```

### Directiva Blade `@role`

Propósito: permitir usar una sintaxis limpia y legible en las plantillas Blade para mostrar o ocultar fragmentos de UI según el rol del usuario.

```php
// app/Providers/AppServiceProvider.php

use Illuminate\Support\Facades\Blade;

public function boot(): void
{
    // Blade::if define una nueva directiva condicional @role(...) usable en vistas
    Blade::if('role', function (string $role): bool {
        // auth()->check() comprueba que hay un usuario autenticado
        // auth()->user()->hasRole($role) reutiliza el helper definido en User
        return auth()->check() && auth()->user()->hasRole($role);
    });
}
```

### Uso en vistas (Blade):

Ahora puedes usar la directiva `@role` en cualquier plantilla Blade para mostrar contenido solo a usuarios con un rol específico. Por ejemplo, si observas el contenido del fichero `resources/views/components/navbar.blade.php`, verás el siguiente contenido:

```blade
                @auth
                    @role('estudiante')
                        <a href="{{ route('estudiante.dashboard') }}"
                           class="text-gray-300 hover:text-white text-sm transition-colors
                                  {{ request()->routeIs('estudiante.*') ? 'text-white font-semibold' : '' }}">
                            Mi espacio
                        </a>
                    @endrole
                    @role('docente')
                        <a href="{{ route('docente.dashboard') }}"
                           class="text-gray-300 hover:text-white text-sm transition-colors
                                  {{ request()->routeIs('docente.*') ? 'text-white font-semibold' : '' }}">
                            Mi docencia
                        </a>
                    @endrole
                @endauth
```

En función de si te autenticas como docente o como estudiante, verás el enlace **Mi docencia** o **Mi espacio** respectivamente. Si no estás autenticado, no se mostrará ninguno de los dos.

### Visualización de las páginas de destino

En este momento, no se visualiza correctamente el contenido de los enlaces **Mi docencia** o **Mi espacio**, ya que generan errores.

**Sin modificar el código de la vista del docente**, debes modificar el mínimo código posible **en los modelos** para que la vista del docente no genere errores y se visualice correctamente. (solución)[./02_11_soluciones.md#vista-docente]

También, **sin modificar el código de la vista del estudiante**, debes posibilitar que se visualice la vista del estudiante y que funcione el enlace a **Mis módulos** que se muestra en esa vista. (solución)[./02_11_soluciones.md#vista-estudiante]


### Consideraciones y buenas prácticas

- Centraliza la lógica de comprobación de roles en el método `hasRole()` del modelo `User` para evitar duplicidad.
- Usa `upsert` o validaciones cuando asignes roles en seeders/tests para evitar inconsistencias.
- Si necesitas comprobar múltiples roles, extiende `hasRole()` o añade `hasAnyRole(array $roles)` para mayor flexibilidad.
- Para control de permisos más fino (acciones específicas), valora integrar políticas (Policies) o paquetes como Spatie Laravel-Permission.

---

**Unidad anterior ←** [Unidad 1: Modelado de datos EAC en Laravel](./01_modelado_datos_eac.md)

  **Siguiente capítulo →** [Verificación final de la Unidad 2](./02_10_verificación_final.md)

**Siguiente unidad →** [Unidad 3: API REST EAC](./03_api_rest_eac.md)
