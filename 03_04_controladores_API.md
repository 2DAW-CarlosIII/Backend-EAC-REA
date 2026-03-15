## 3.4. Controladores de API

### 3.4.1. Públicos

```bash
php artisan make:controller Api/V1/ModuloController --api
php artisan make:controller Api/V1/EcosistemaController --api
```

#### 3.4.1.1. ModuloController (público)

```php
// app/Http/Controllers/Api/V1/ModuloController.php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ModuloCollection;
use App\Http\Resources\ModuloResource;
use App\Models\Modulo;
use Illuminate\Http\Request;

class ModuloController extends Controller
{
    /**
     * GET /api/v1/modulos
     * Catálogo paginado de módulos que tienen al menos un ecosistema activo.
     */
    public function index(Request $request): ModuloCollection
    {
        $modulos = Modulo::whereHas('ecosistemasLaborales', fn($q) => $q->where('activo', true))
            ->with([
                'cicloFormativo.familiaProfesional',
                'ecosistemasLaborales' => fn($q) => $q->where('activo', true)
                    ->withCount('situacionesCompetencia'),
                'resultadosAprendizaje',
            ])
            ->when($request->filled('buscar'), fn($q) =>
                $q->where('nombre', 'like', "%{$request->buscar}%")
                  ->orWhere('codigo', 'like', "%{$request->buscar}%")
            )
            ->when($request->filled('ciclo_id'), fn($q) =>
                $q->where('ciclo_formativo_id', $request->ciclo_id)
            )
            ->orderBy('nombre')
            ->paginate($request->integer('per_page', 12));

        return new ModuloCollection($modulos);
    }

    /**
     * GET /api/v1/modulos/{modulo}
     * Detalle completo: jerarquía curricular, RA, CE y ecosistema activo con SCs.
     */
    public function show(Modulo $modulo): ModuloResource
    {
        $modulo->load([
            'cicloFormativo.familiaProfesional',
            'resultadosAprendizaje.criteriosEvaluacion',
            'ecosistemasLaborales' => fn($q) => $q->where('activo', true)
                ->withCount('situacionesCompetencia'),
        ]);

        return new ModuloResource($modulo);
    }
}
```

#### 3.4.1.2. EcosistemaController (público)

```php
// app/Http/Controllers/Api/V1/EcosistemaController.php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\EcosistemaResource;
use App\Http\Resources\SituacionCompetenciaResource;
use App\Models\EcosistemaLaboral;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EcosistemaController extends Controller
{
    /**
     * GET /api/v1/ecosistemas/{ecosistema}
     * Detalle del ecosistema con el grafo completo de SCs.
     */
    public function show(EcosistemaLaboral $ecosistema): EcosistemaResource
    {
        $ecosistema->load([
            'modulo.cicloFormativo',
            'situacionesCompetencia.prerequisitos',
            'situacionesCompetencia.nodosRequisito',
            'situacionesCompetencia.criteriosEvaluacion',
        ]);

        return new EcosistemaResource($ecosistema);
    }

    /**
     * GET /api/v1/ecosistemas/{ecosistema}/situaciones
     * Lista plana de SCs con prerequisitos — útil para renderizar el grafo en el cliente.
     */
    public function situaciones(EcosistemaLaboral $ecosistema): AnonymousResourceCollection
    {
        $scs = $ecosistema->situacionesCompetencia()
            ->with(['prerequisitos', 'dependientes', 'nodosRequisito', 'criteriosEvaluacion'])
            ->orderBy('nivel_complejidad')
            ->orderBy('codigo')
            ->get();

        return SituacionCompetenciaResource::collection($scs)
            ->additional([
                'meta' => [
                    'version'        => '1.0',
                    'timestamp'      => now()->toIso8601String(),
                    'ecosistema_id'  => $ecosistema->id,
                    'total'          => $scs->count(),
                ],
            ]);
    }
}
```

---

## 3.4.2. Estudiante

```bash
php artisan make:controller Api/V1/Estudiante/PerfilController
php artisan make:controller Api/V1/Estudiante/MatriculaController --invokable
```

### 3.4.2.1. PerfilController

```php
// app/Http/Controllers/Api/V1/Estudiante/PerfilController.php

namespace App\Http\Controllers\Api\V1\Estudiante;

use App\Http\Controllers\Controller;
use App\Http\Resources\PerfilHabilitacionResource;
use App\Models\EcosistemaLaboral;
use App\Models\PerfilHabilitacion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PerfilController extends Controller
{
    /**
     * GET /api/v1/estudiante/perfil
     * Lista todos los perfiles del estudiante autenticado (uno por ecosistema matriculado).
     */
    public function index(): AnonymousResourceCollection
    {
        $perfiles = PerfilHabilitacion::where('estudiante_id', auth()->id())
            ->with([
                'ecosistemaLaboral.modulo',
                'situacionesConquistadas',
            ])
            ->get();

        return PerfilHabilitacionResource::collection($perfiles)
            ->additional([
                'meta' => [
                    'version'   => '1.0',
                    'timestamp' => now()->toIso8601String(),
                ],
            ]);
    }

    /**
     * GET /api/v1/estudiante/perfil/{ecosistema}
     * Perfil completo del estudiante autenticado en un ecosistema concreto.
     * Esta respuesta es la que consumirá OrionSyncService en la Unidad 6.
     */
    public function show(EcosistemaLaboral $ecosistema): PerfilHabilitacionResource|JsonResponse
    {
        $perfil = PerfilHabilitacion::where('estudiante_id', auth()->id())
            ->where('ecosistema_laboral_id', $ecosistema->id)
            ->with([
                'ecosistemaLaboral',
                'situacionesConquistadas',
            ])
            ->first();

        if (! $perfil) {
            return response()->json([
                'type'   => 'https://backend-eac.test/errors/not-found',
                'title'  => 'Perfil no encontrado',
                'status' => 404,
                'detail' => 'No tienes perfil de habilitación en este ecosistema. Matricúlate primero.',
            ], 404);
        }

        return new PerfilHabilitacionResource($perfil);
    }
}
```

### 3.4.2.2. MatriculaController

```php
// app/Http/Controllers/Api/V1/Estudiante/MatriculaController.php

namespace App\Http\Controllers\Api\V1\Estudiante;

use App\Http\Controllers\Controller;
use App\Models\Modulo;
use App\Models\PerfilHabilitacion;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MatriculaController extends Controller
{
    /**
     * POST /api/v1/estudiante/matriculas
     * Body: { "modulo_id": 1 }
     *
     * Matricula al estudiante autenticado en el módulo indicado
     * y crea su perfil de habilitación en el ecosistema activo.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'modulo_id' => ['required', 'integer', 'exists:modulos,id'],
        ]);

        $user   = auth()->user();
        $modulo = Modulo::findOrFail($request->modulo_id);

        if ($user->matriculas()->where('modulo_id', $modulo->id)->exists()) {
            return response()->json([
                'type'   => 'https://backend-eac.test/errors/conflict',
                'title'  => 'Matrícula duplicada',
                'status' => 409,
                'detail' => 'Ya estás matriculado en este módulo.',
            ], 409);
        }

        $ecosistema = $modulo->ecosistemasLaborales()->where('activo', true)->first();

        if (! $ecosistema) {
            return response()->json([
                'type'   => 'https://backend-eac.test/errors/unavailable',
                'title'  => 'Módulo no disponible',
                'status' => 422,
                'detail' => 'Este módulo no tiene un ecosistema activo y no acepta matrículas.',
            ], 422);
        }

        DB::transaction(function () use ($user, $modulo, $ecosistema) {
            $user->matriculas()->create(['modulo_id' => $modulo->id]);

            $rolEstudiante = Role::where('name', 'estudiante')->first();
            if ($rolEstudiante) {
                DB::table('user_roles')->insertOrIgnore([
                    'user_id'               => $user->id,
                    'role_id'               => $rolEstudiante->id,
                    'ecosistema_laboral_id' => $ecosistema->id,
                    'created_at'            => now(),
                    'updated_at'            => now(),
                ]);
            }

            PerfilHabilitacion::firstOrCreate(
                [
                    'estudiante_id'         => $user->id,
                    'ecosistema_laboral_id' => $ecosistema->id,
                ],
                ['calificacion_actual' => 0.00]
            );
        });

        return response()->json([
            'data' => [
                'modulo_id'      => $modulo->id,
                'ecosistema_id'  => $ecosistema->id,
                'mensaje'        => 'Matrícula realizada correctamente.',
            ],
            'meta' => [
                'version'   => '1.0',
                'timestamp' => now()->toIso8601String(),
            ],
        ], 201);
    }
}
```

---

## 3.4.3. Docente

```bash
php artisan make:controller Api/V1/Docente/ProgresoController --invokable
php artisan make:controller Api/V1/Docente/ConquistaController --invokable
```

### 3.4.3.1. ProgresoController

```php
// app/Http/Controllers/Api/V1/Docente/ProgresoController.php

namespace App\Http\Controllers\Api\V1\Docente;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProgresoEstudianteResource;
use App\Models\EcosistemaLaboral;
use App\Models\Matricula;
use App\Models\PerfilHabilitacion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProgresoController extends Controller
{
    /**
     * GET /api/v1/docente/ecosistemas/{ecosistema}/progreso
     * Progreso de todos los estudiantes matriculados en el módulo del ecosistema.
     */
    public function __invoke(EcosistemaLaboral $ecosistema): AnonymousResourceCollection|JsonResponse
    {
        $this->autorizarDocente($ecosistema);

        $totalScs = $ecosistema->situacionesCompetencia()->count();

        $progreso = Matricula::where('modulo_id', $ecosistema->modulo_id)
            ->with('estudiante')
            ->get()
            ->map(function ($matricula) use ($ecosistema, $totalScs) {
                $perfil = PerfilHabilitacion::where('estudiante_id', $matricula->estudiante_id)
                    ->where('ecosistema_laboral_id', $ecosistema->id)
                    ->withCount('situacionesConquistadas')
                    ->with('situacionesConquistadas:id,codigo,titulo')
                    ->first();

                return new ProgresoEstudianteResource([
                    'estudiante_id' => $matricula->estudiante_id,
                    'conquistadas'  => $perfil?->situaciones_conquistadas_count ?? 0,
                    'total_scs'     => $totalScs,
                    'calificacion'  => $perfil?->calificacion_actual ?? 0,
                    'detalle'       => $perfil?->situacionesConquistadas
                        ->map(fn($sc) => [
                            'codigo'              => $sc->codigo,
                            'gradiente_autonomia' => $sc->pivot->gradiente_autonomia,
                            'fecha_conquista'     => $sc->pivot->fecha_conquista,
                        ]) ?? [],
                ]);
            });

        return ProgresoEstudianteResource::collection($progreso)
            ->additional([
                'meta' => [
                    'version'        => '1.0',
                    'timestamp'      => now()->toIso8601String(),
                    'ecosistema_id'  => $ecosistema->id,
                    'total_scs'      => $totalScs,
                    'total_matriculados' => $progreso->count(),
                ],
            ]);
    }

    private function autorizarDocente(EcosistemaLaboral $ecosistema): void
    {
        $esDocente = auth()->user()
            ->userRoles()
            ->where('ecosistema_laboral_id', $ecosistema->id)
            ->where('name', 'docente')
            ->exists();

        abort_unless($esDocente, 403, 'No tienes rol de docente en este ecosistema.');
    }
}
```

### 3.4.3.2. ConquistaController

El docente registra la conquista de una SC por un estudiante tras evaluar su desempeño presencial o mediante la plataforma del centro. El resultado es el `gradiente_autonomia` y la `puntuacion_conquista`.

```php
// app/Http/Controllers/Api/V1/Docente/ConquistaController.php

namespace App\Http\Controllers\Api\V1\Docente;

use App\Http\Controllers\Controller;
use App\Models\EcosistemaLaboral;
use App\Models\PerfilHabilitacion;
use App\Models\SituacionCompetencia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ConquistaController extends Controller
{
    /**
     * POST /api/v1/docente/ecosistemas/{ecosistema}/conquistas
     *
     * Body:
     * {
     *   "estudiante_id": 5,
     *   "sc_codigo": "SC-01",
     *   "gradiente_autonomia": "supervisado",
     *   "puntuacion_conquista": 82.5
     * }
     */
    public function __invoke(Request $request, EcosistemaLaboral $ecosistema): JsonResponse
    {
        $this->autorizarDocente($ecosistema);

        $data = $request->validate([
            'estudiante_id'       => ['required', 'integer', 'exists:users,id'],
            'sc_codigo'           => ['required', 'string'],
            'gradiente_autonomia' => ['required', Rule::in(['asistido','guiado','supervisado','autonomo'])],
            'puntuacion_conquista' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        // Verificar que la SC pertenece a este ecosistema
        $sc = SituacionCompetencia::where('ecosistema_laboral_id', $ecosistema->id)
            ->where('codigo', $data['sc_codigo'])
            ->firstOrFail();

        // Verificar que la puntuación supera el umbral de maestría
        if ($data['puntuacion_conquista'] < $sc->umbral_maestria) {
            return response()->json([
                'type'   => 'https://backend-eac.test/errors/umbral-no-alcanzado',
                'title'  => 'Umbral de maestría no alcanzado',
                'status' => 422,
                'detail' => "La puntuación {$data['puntuacion_conquista']} no supera el umbral "
                          . "de maestría de la SC {$sc->codigo} ({$sc->umbral_maestria}%).",
            ], 422);
        }

        // Verificar que el estudiante está matriculado en el módulo
        $matriculado = \App\Models\Matricula::where('modulo_id', $ecosistema->modulo_id)
            ->where('estudiante_id', $data['estudiante_id'])
            ->exists();

        abort_unless($matriculado, 422, 'El estudiante no está matriculado en este módulo.');

        $perfil = PerfilHabilitacion::where('estudiante_id', $data['estudiante_id'])
            ->where('ecosistema_laboral_id', $ecosistema->id)
            ->firstOrFail();

        DB::transaction(function () use ($perfil, $sc, $data) {
            $yaConquistada = $perfil->situacionesConquistadas()
                ->where('situacion_competencia_id', $sc->id)
                ->exists();

            if ($yaConquistada) {
                // Actualizar el gradiente si mejora
                $perfil->situacionesConquistadas()->updateExistingPivot($sc->id, [
                    'gradiente_autonomia'  => $data['gradiente_autonomia'],
                    'puntuacion_conquista' => $data['puntuacion_conquista'],
                    'intentos'             => DB::raw('intentos + 1'),
                    'fecha_conquista'      => now(),
                ]);
            } else {
                $perfil->situacionesConquistadas()->attach($sc->id, [
                    'gradiente_autonomia'  => $data['gradiente_autonomia'],
                    'puntuacion_conquista' => $data['puntuacion_conquista'],
                    'intentos'             => 1,
                    'fecha_conquista'      => now(),
                ]);
            }

            // Recalcular calificación actual del perfil
            // (lógica completa en Unidad 7; aquí usamos media ponderada simple)
            $nuevaCalificacion = $perfil->situacionesConquistadas()
                ->avg('perfil_situacion.puntuacion_conquista');

            $perfil->update(['calificacion_actual' => round($nuevaCalificacion, 2)]);
        });

        return response()->json([
            'data' => [
                'perfil_habilitacion_id' => $perfil->id,
                'sc_codigo'              => $sc->codigo,
                'gradiente_autonomia'    => $data['gradiente_autonomia'],
                'puntuacion_conquista'   => $data['puntuacion_conquista'],
                'mensaje'                => "SC {$sc->codigo} registrada correctamente.",
            ],
            'meta' => [
                'version'   => '1.0',
                'timestamp' => now()->toIso8601String(),
            ],
        ], 201);
    }

    private function autorizarDocente(EcosistemaLaboral $ecosistema): void
    {
        $esDocente = auth()->user()
            ->userRoles()
            ->where('ecosistema_laboral_id', $ecosistema->id)
            ->where('name', 'docente')
            ->exists();

        abort_unless($esDocente, 403, 'No tienes rol de docente en este ecosistema.');
    }
}
```

---

**Unidad anterior ←** [Unidad 2: Frontend: layout y vistas Blade para el marketplace](./02_frontend_layout.md)

  **Siguiente capítulo →** [Rutas API](./03_05_rutas_API.md)
  
**Siguiente unidad →** [Unidad 4: Motor de navegación: ZDP y recomendación](./04_motor_navegacion.md)
