## 2.8. Rutas

Genera el resto de las rutas de la aplicación, organizándolas en grupos con middleware de autenticación y nombrado consistente. Puedes usar el siguiente código como referencia, adaptándolo a los controladores y vistas que hayas creado en esta unidad:

```php
// routes/web.php

use App\Http\Controllers\Publico;
use App\Http\Controllers\Estudiante;
use App\Http\Controllers\Docente;

// ─── Rutas públicas ───────────────────────────────────────────────────────────
Route::get('/', Publico\PortadaController::class)
    ->name('publico.portada');

Route::prefix('modulos')->name('publico.modulos.')->group(function () {
    Route::get('/',         [Publico\ModuloController::class, 'index'])->name('index');
    Route::get('/{modulo}', [Publico\ModuloController::class, 'show'])->name('show');
});

Route::get('/ecosistemas/{ecosistema}', Publico\EcosistemaController::class)
    ->name('publico.ecosistemas.show');

// ─── Rutas del estudiante ─────────────────────────────────────────────────────
Route::middleware(['auth', 'role:estudiante'])
    ->prefix('estudiante')
    ->name('estudiante.')
    ->group(function () {
        Route::get('/dashboard',          Estudiante\DashboardController::class)->name('dashboard');
        Route::get('/perfil/{perfil}',    Estudiante\PerfilController::class)->name('perfil.show');
    });

// ─── Rutas del docente ────────────────────────────────────────────────────────
Route::middleware(['auth', 'role:docente'])
    ->prefix('docente')
    ->name('docente.')
    ->group(function () {
        Route::get('/dashboard',                Docente\DashboardController::class)->name('dashboard');
        Route::get('/ecosistemas/{ecosistema}', Docente\EcosistemaController::class)->name('ecosistemas.show');
        Route::get('/progreso/{ecosistema}',    Docente\ProgresoController::class)->name('progreso.show');
    });

// Rutas de autenticación (generadas por Breeze)

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
```

---

**Unidad anterior ←** [Unidad 1: Modelado de datos EAC en Laravel](./01_modelado_datos_eac.md)

  **Siguiente capítulo →** [Middleware y helpers](./02_09_middleware_helper.md)

**Siguiente unidad →** [Unidad 3: API REST EAC](./03_api_rest_eac.md)
