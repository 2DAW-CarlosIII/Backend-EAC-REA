## 5.6. Endpoints de la huella de talento

### Controladores

```bash
php artisan make:controller Api/V1/Estudiante/HuellaController
```

```php
// app/Http/Controllers/Api/V1/Estudiante/HuellaController.php

namespace App\Http\Controllers\Api\V1\Estudiante;

use App\Http\Controllers\Controller;
use App\Models\EcosistemaLaboral;
use App\Models\HuellaTalento;
use App\Models\PerfilHabilitacion;
use App\Services\HuellaService;
use Illuminate\Http\JsonResponse;

class HuellaController extends Controller
{
    public function __construct(
        private readonly HuellaService $huellaService,
    ) {}

    /**
     * GET /api/v1/estudiante/perfil/{ecosistema}/huella
     * Devuelve la huella más reciente (o la genera si no existe).
     */
    public function show(EcosistemaLaboral $ecosistema): JsonResponse
    {
        $perfil = $this->perfilOFail($ecosistema);
        $huella = $this->huellaService->ultimaOGenerar($perfil);

        return response()->json([
            'data' => $huella->payload,
            'meta' => [
                'huella_id'   => $huella->id,
                'generada_en' => $huella->generada_en->toIso8601String(),
                'ngsi_ld_id'  => $huella->ngsi_ld_id,
                'version'     => '1.0',
                'timestamp'   => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * POST /api/v1/estudiante/perfil/{ecosistema}/huella
     * Genera una nueva huella (snapshot del estado actual).
     */
    public function store(EcosistemaLaboral $ecosistema): JsonResponse
    {
        $perfil = $this->perfilOFail($ecosistema);
        $huella = $this->huellaService->generar($perfil);

        return response()->json([
            'data' => $huella->payload,
            'meta' => [
                'huella_id'   => $huella->id,
                'generada_en' => $huella->generada_en->toIso8601String(),
                'ngsi_ld_id'  => $huella->ngsi_ld_id,
                'version'     => '1.0',
                'timestamp'   => now()->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * GET /api/v1/estudiante/perfil/{ecosistema}/huellas
     * Historial de huellas generadas.
     */
    public function index(EcosistemaLaboral $ecosistema): JsonResponse
    {
        $perfil = $this->perfilOFail($ecosistema);

        $huellas = HuellaTalento::where('estudiante_id', $perfil->estudiante_id)
            ->where('ecosistema_laboral_id', $ecosistema->id)
            ->orderByDesc('generada_en')
            ->get(['id', 'generada_en', 'ngsi_ld_id']);

        return response()->json([
            'data' => $huellas->map(fn($h) => [
                'id'          => $h->id,
                'generada_en' => $h->generada_en->toIso8601String(),
                'ngsi_ld_id'  => $h->ngsi_ld_id,
                'links'       => [
                    'self' => route('api.estudiante.huella.show', $ecosistema),
                ],
            ]),
            'meta' => [
                'version'   => '1.0',
                'timestamp' => now()->toIso8601String(),
                'total'     => $huellas->count(),
            ],
        ]);
    }

    // ─── Helper ─────────────────────────────────────────────────────────────

    private function perfilOFail(EcosistemaLaboral $ecosistema): PerfilHabilitacion
    {
        $perfil = PerfilHabilitacion::where('estudiante_id', auth()->id())
            ->where('ecosistema_laboral_id', $ecosistema->id)
            ->first();

        abort_if(
            is_null($perfil),
            404,
            'No tienes perfil en este ecosistema. Matricúlate primero.'
        );

        return $perfil;
    }
}
```

### Rutas

Adapta `routes/api.php`, dentro del grupo `auth:sanctum` → `estudiante`, para que se recojan las siguientes rutas:

```php
            Route::prefix('perfil/{ecosistema}')->group(function () {
                Route::get('/',   [V1\Estudiante\PerfilController::class, 'show'])
                    ->name('perfil.show');
                Route::get('huellas',  [V1\Estudiante\HuellaController::class, 'index']) ->name('huellas.index');
                Route::get('huella',   [V1\Estudiante\HuellaController::class, 'show'])  ->name('huella.show');
                Route::post('huella',  [V1\Estudiante\HuellaController::class, 'store']) ->name('huella.store');
            });
```

---

**Unidad anterior ←** [Unidad 4: Motor de navegación: ZDP y recomendación](./04_motor_navegacion.md)

**Siguiente capítulo →** [Unidad 5.7: Endpoint de calificación para el docente](./05_07_endpoints_calificacion_docente.md)

**Siguiente unidad →** [Unidad 6: Visualización del espacio competencial](./06_visualizacion_espacio_competencial.md)
