## 5.7. Endpoint de calificación para el docente

El docente puede consultar el desglose de calificación de cualquier estudiante de su ecosistema:

```bash
php artisan make:controller Api/V1/Docente/CalificacionController --invokable
```

```php
// app/Http/Controllers/Api/V1/Docente/CalificacionController.php

namespace App\Http\Controllers\Api\V1\Docente;

use App\Http\Controllers\Controller;
use App\Models\EcosistemaLaboral;
use App\Models\PerfilHabilitacion;
use App\Services\CalificacionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CalificacionController extends Controller
{
    public function __construct(
        private readonly CalificacionService $calificacionService,
    ) {}

    /**
     * GET /api/v1/docente/ecosistemas/{ecosistema}/calificacion/{estudiante_id}
     * Desglose completo de calificación de un estudiante.
     */
    public function __invoke(EcosistemaLaboral $ecosistema, int $estudianteId): JsonResponse
    {
        $this->autorizarDocente($ecosistema);

        $perfil = PerfilHabilitacion::where('estudiante_id', $estudianteId)
            ->where('ecosistema_laboral_id', $ecosistema->id)
            ->firstOrFail();

        $desglose = $this->calificacionService->desglose($perfil);

        return response()->json([
            'data' => [
                'estudiante_id'      => $estudianteId,
                'ecosistema_id'      => $ecosistema->id,
                'calificacion_total' => $desglose['calificacion_total'],
                'desglose_ra'        => $desglose['desglose_ra'],
            ],
            'meta' => [
                'version'   => '1.0',
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    }

    private function autorizarDocente(EcosistemaLaboral $ecosistema): void
    {
        abort_unless(
            auth()->user()
                ->userRoles()
                ->where('ecosistema_laboral_id', $ecosistema->id)
                ->whereHas('role', fn($q) => $q->where('name', 'docente'))
                ->exists(),
            403
        );
    }
}
```

Ruta en `routes/api.php`, dentro del grupo `docente`:

```php
Route::get(
    'ecosistemas/{ecosistema}/calificacion/{estudianteId}',
    V1\Docente\CalificacionController::class
)->name('api.docente.calificacion');
```

---

**Unidad anterior ←** [Unidad 4: Motor de navegación: ZDP y recomendación](./04_motor_navegacion.md)

**Siguiente capítulo →** [Unidad 5.8: Verificación con `curl` y Tinker](./05_08_comprobaciones_curl_tinker.md)

**Siguiente unidad →** [Unidad 6: Visualización del espacio competencial](./06_visualizacion_espacio_competencial.md)
