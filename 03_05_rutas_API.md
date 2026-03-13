
## 3.5. Rutas de la API

```php
// routes/api.php

use App\Http\Controllers\Api\V1;

Route::prefix('v1')->name('api.v1.')->group(function () {

    // ── Públicos ────────────────────────────────────────────────────────────────
    Route::get('modulos',         [V1\ModuloController::class, 'index'])->name('modulos.index');
    Route::get('modulos/{modulo}', [V1\ModuloController::class, 'show'])->name('modulos.show');

    Route::get('ecosistemas/{ecosistema}',              [V1\EcosistemaController::class, 'show'])
        ->name('ecosistemas.show');
    Route::get('ecosistemas/{ecosistema}/situaciones',  [V1\EcosistemaController::class, 'situaciones'])
        ->name('ecosistemas.situaciones');

    // ── Autenticados (Sanctum) ───────────────────────────────────────────────────
    Route::middleware('auth:sanctum')->group(function () {

        // Estudiante
        Route::prefix('estudiante')->name('estudiante.')->group(function () {
            Route::get('perfil',                [V1\Estudiante\PerfilController::class, 'index'])
                ->name('perfil.index');
            Route::get('perfil/{ecosistema}',   [V1\Estudiante\PerfilController::class, 'show'])
                ->name('perfil.show');
            Route::post('matriculas',           V1\Estudiante\MatriculaController::class)
                ->name('matriculas.store');
        });

        // Docente
        Route::prefix('docente')->name('docente.')->group(function () {
            Route::get('ecosistemas/{ecosistema}/progreso',
                V1\Docente\ProgresoController::class)->name('progreso');
            Route::post('ecosistemas/{ecosistema}/conquistas',
                V1\Docente\ConquistaController::class)->name('conquistas');
        });
    });
});
```

---

**Unidad anterior ←** [Unidad 2: Frontend: layout y vistas Blade para el marketplace](./02_frontend_layout.md)

  **Siguiente capítulo →** [Control de errores](./03_06_control_errores.md)

**Siguiente unidad →** [Unidad 4: Autenticación con Sanctum + Keyrock](./04_autenticacion_sanctum_keyrock.md)
