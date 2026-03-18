## 6.6. Rutas

Añade las rutas de visualización al fichero `routes/web.php`:

```php
// routes/web.php

use App\Http\Controllers\Docente\AnalyticsController;
use App\Http\Controllers\Estudiante\HuellaRadarController;

// ─── Docente ───────────────────────────────────────────────────────────────
Route::middleware(['auth', 'role:docente'])
    ->prefix('docente')
    ->name('docente.')
    ->group(function () {

        // ... rutas previas ...

        Route::get(
            'ecosistemas/{ecosistema}/analytics',
            AnalyticsController::class
        )->name('ecosistemas.analytics');
    });

// ─── Estudiante ────────────────────────────────────────────────────────────
Route::middleware(['auth', 'role:estudiante'])
    ->prefix('estudiante')
    ->name('estudiante.')
    ->group(function () {

        // ... rutas previas ...

        Route::get(
            'perfil/{ecosistema}/huella-radar',
            HuellaRadarController::class
        )->name('huella-radar');

        Route::get(
            'perfil/{ecosistema}/huellas',
            // Reutilizamos el controlador de la Unidad 5
            App\Http\Controllers\Estudiante\HuellaController::class . '@index'
        )->name('huellas');
    });
```

---

**Unidad anterior ←** [Unidad 5: Evaluación y Huella de Talento](./05_evaluacion_huella_talento.md)

**Siguiente capítulo →** [Unidad 6.7: Enlaces en las vistas](./06_07_enlaces_vistas.md)

**Siguiente unidad →** [Unidad 7: Autenticación con Sanctum + Keyrock](./07_autenticacion_sanctum_keyrock.md)
