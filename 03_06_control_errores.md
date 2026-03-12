## 3.6. Manejo global de errores de la API

Registra un handler en `bootstrap/app.php` para que los errores de la API devuelvan siempre JSON con el formato Problem Details, en lugar de HTML:

```php
// bootstrap/app.php

->withExceptions(function (Exceptions $exceptions) {

    $exceptions->render(function (\Throwable $e, \Illuminate\Http\Request $request) {
        if (! $request->expectsJson() && ! $request->is('api/*')) {
            return null; // Dejar que el handler web actúe
        }

        $status = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;

        return response()->json([
            'type'   => "https://backend-eac.test/errors/{$status}",
            'title'  => $e->getMessage() ?: 'Error interno',
            'status' => $status,
        ], $status);
    });
})
```

---

**Unidad anterior ←** [Unidad 2: Frontend: layout y vistas Blade para el marketplace](./02_frontend_layout.md)

  **Siguiente capítulo →** [Verificación con curl](./03_07_verificacion_curl.md)

**Siguiente unidad →** [Unidad 4: Autenticación con Sanctum + Keyrock](./04_autenticacion_sanctum_keyrock.md)
