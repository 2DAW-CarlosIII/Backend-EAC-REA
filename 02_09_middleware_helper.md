## 2.9. Middleware de rol y helper Blade

### Middleware

```bash
php artisan make:middleware EnsureUserHasRole
```

```php
// app/Http/Middleware/EnsureUserHasRole.php

class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        if (! $request->user()?->roles()->where('name', $role)->exists()) {
            abort(403, 'Acceso no autorizado para este rol.');
        }
        return $next($request);
    }
}
```

Regístralo en `bootstrap/app.php` (Laravel 11):

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'role' => \App\Http\Middleware\EnsureUserHasRole::class,
    ]);
})
```

### Helper `hasRole()` en el modelo User

```php
// app/Models/User.php  (añadir)
public function hasRole(string $role): bool
{
    return $this->roles()->where('name', $role)->exists();
}
```

### Directiva Blade `@role`

```php
// app/Providers/AppServiceProvider.php

use Illuminate\Support\Facades\Blade;

public function boot(): void
{
    Blade::if('role', function (string $role): bool {
        return auth()->check() && auth()->user()->hasRole($role);
    });
}
```

Uso en cualquier vista:

```blade
@role('docente')
    <p>Solo visible para docentes</p>
@endrole
```

---

**Unidad anterior ←** [Unidad 1: Modelado de datos EAC en Laravel](./01_modelado_datos_eac.md)

  **Siguiente capítulo →** [Verificación final de la Unidad 2](./02_10_verificación_final.md)

**Siguiente unidad →** [Unidad 3: API REST EAC](./03_api_rest_eac.md)
