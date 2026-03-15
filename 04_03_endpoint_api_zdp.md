## 4.3. Endpoint de la API: ZDP del estudiante

Añade un controlador invocable al grupo de rutas del estudiante de la Unidad 3:

```bash
php artisan make:controller Api/V1/Estudiante/ZdpController --invokable
```

```php
// app/Http/Controllers/Api/V1/Estudiante/ZdpController.php

namespace App\Http\Controllers\Api\V1\Estudiante;

use App\Http\Controllers\Controller;
use App\Http\Resources\SituacionCompetenciaResource;
use App\Models\EcosistemaLaboral;
use App\Models\PerfilHabilitacion;
use App\Services\GrafoService;
use App\Services\RecomendacionService;
use Illuminate\Http\JsonResponse;

class ZdpController extends Controller
{
    public function __construct(
        private readonly GrafoService       $grafoService,
        private readonly RecomendacionService $recomendacionService,
    ) {}

    /**
     * GET /api/v1/estudiante/perfil/{ecosistema}/zdp
     *
     * Devuelve:
     *  - zdp:           SCs accesibles ahora
     *  - bloqueadas:    SCs aún sin prerequisitos completos
     *  - conquistadas:  SCs ya superadas
     *  - recomendacion: la SC sugerida para acometer a continuación
     *  - ranking_zdp:   todas las SCs de la ZDP con su puntuación
     */
    public function __invoke(EcosistemaLaboral $ecosistema): JsonResponse
    {
        $perfil = PerfilHabilitacion::where('estudiante_id', auth()->id())
            ->where('ecosistema_laboral_id', $ecosistema->id)
            ->with('situacionesConquistadas:id,codigo')
            ->first();

        if (! $perfil) {
            return response()->json([
                'title'  => 'Perfil no encontrado',
                'status' => 404,
                'detail' => 'No tienes perfil en este ecosistema. Matricúlate primero.',
            ], 404);
        }

        $codigosConquistados = $perfil->codigosConquistados();

        $clasificacion = $this->grafoService->clasificar($ecosistema, $codigosConquistados);
        $recomendacion = $this->recomendacionService->recomendar($ecosistema, $codigosConquistados);
        $rankingZdp    = $this->recomendacionService->rankingZdp($ecosistema, $codigosConquistados);

        return response()->json([
            'data' => [
                'ecosistema_id'   => $ecosistema->id,
                'conquistadas'    => SituacionCompetenciaResource::collection(
                    $clasificacion['conquistadas']
                ),
                'zdp'             => SituacionCompetenciaResource::collection(
                    $clasificacion['zdp']
                ),
                'bloqueadas'      => SituacionCompetenciaResource::collection(
                    $clasificacion['bloqueadas']
                ),
                'recomendacion'   => $recomendacion
                    ? new SituacionCompetenciaResource($recomendacion)
                    : null,
                'ranking_zdp'     => $rankingZdp->map(fn($item) => [
                    'sc'      => new SituacionCompetenciaResource($item['sc']),
                    'score'   => $item['score'],
                    'motivos' => $item['motivos'],
                ]),
                'completado'      => $clasificacion['zdp']->isEmpty()
                    && $clasificacion['bloqueadas']->isEmpty(),
            ],
            'meta' => [
                'version'              => '1.0',
                'timestamp'            => now()->toIso8601String(),
                'total_conquistadas'   => $clasificacion['conquistadas']->count(),
                'total_zdp'            => $clasificacion['zdp']->count(),
                'total_bloqueadas'     => $clasificacion['bloqueadas']->count(),
            ],
        ]);
    }
}
```

Añade la ruta en `routes/api.php`, dentro del grupo `auth:sanctum` → `estudiante`:

```php
Route::get('perfil/{ecosistema}/zdp', V1\Estudiante\ZdpController::class)
    ->name('api.estudiante.zdp');
```

---

**Unidad anterior ←** [Unidad 3: API REST EAC](./03_api_rest_eac.md)

**Siguiente capítulo →** [Integración en las vistas Blade](./04_04_vistas_zdp.md)

**Siguiente unidad →** [Unidad 5: Evaluación y seguimiento](./05_evaluacion_seguimiento.md)
