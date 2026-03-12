## 3.3. API Resources

Los API Resources son la capa de transformación entre los modelos Eloquent y el JSON que devuelve la API. Créalos todos antes de escribir los controladores.

```bash
php artisan make:resource ModuloResource
php artisan make:resource ModuloCollection
php artisan make:resource EcosistemaResource
php artisan make:resource SituacionCompetenciaResource
php artisan make:resource PerfilHabilitacionResource
php artisan make:resource SituacionConquistadaResource
php artisan make:resource ProgresoEstudianteResource
```

### 3.3.1. ModuloResource

```php
// app/Http/Resources/ModuloResource.php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ModuloResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'codigo'       => $this->codigo,
            'nombre'       => $this->nombre,
            'descripcion'  => $this->descripcion,
            'horas_totales' => $this->horas_totales,

            'ciclo_formativo' => [
                'id'     => $this->cicloFormativo->id,
                'codigo' => $this->cicloFormativo->codigo,
                'nombre' => $this->cicloFormativo->nombre,
                'grado'  => $this->cicloFormativo->grado,
                'familia_profesional' => [
                    'id'     => $this->cicloFormativo->familiaProfesional->id,
                    'codigo' => $this->cicloFormativo->familiaProfesional->codigo,
                    'nombre' => $this->cicloFormativo->familiaProfesional->nombre,
                ],
            ],

            // Ecosistema activo (puede ser null si el módulo no tiene ecosistema aún)
            'ecosistema_activo' => $this->whenLoaded('ecosistemasLaborales', function () {
                $ecosistema = $this->ecosistemasLaborales
                    ->where('activo', true)
                    ->first();

                return $ecosistema ? [
                    'id'     => $ecosistema->id,
                    'codigo' => $ecosistema->codigo,
                    'nombre' => $ecosistema->nombre,
                    'total_scs' => $ecosistema->situaciones_competencia_count ?? null,
                ] : null;
            }),

            // RA del módulo (trazabilidad curricular)
            'resultados_aprendizaje' => $this->whenLoaded('resultadosAprendizaje', function () {
                return $this->resultadosAprendizaje->map(fn($ra) => [
                    'id'               => $ra->id,
                    'codigo'           => $ra->codigo,
                    'descripcion'      => $ra->descripcion,
                    'peso_porcentaje'  => $ra->peso_porcentaje,
                ]);
            }),

            'links' => [
                'self'       => route('api.modulos.show', $this->id),
                'ecosistema' => $this->ecosistemasLaborales?->where('activo', true)->first()
                    ? route('api.ecosistemas.show',
                        $this->ecosistemasLaborales->where('activo', true)->first()->id)
                    : null,
            ],
        ];
    }
}
```

### 3.3.2. ModuloCollection

```php
// app/Http/Resources/ModuloCollection.php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class ModuloCollection extends ResourceCollection
{
    public $collects = ModuloResource::class;

    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection,
            'meta' => [
                'version'   => '1.0',
                'timestamp' => now()->toIso8601String(),
                'total'     => $this->total(),
                'per_page'  => $this->perPage(),
                'page'      => $this->currentPage(),
            ],
        ];
    }
}
```

### 3.3.3. EcosistemaResource

```php
// app/Http/Resources/EcosistemaResource.php

class EcosistemaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'codigo'      => $this->codigo,
            'nombre'      => $this->nombre,
            'descripcion' => $this->descripcion,
            'activo'      => $this->activo,

            'modulo' => [
                'id'     => $this->modulo->id,
                'codigo' => $this->modulo->codigo,
                'nombre' => $this->modulo->nombre,
            ],

            'situaciones_competencia' => $this->whenLoaded(
                'situacionesCompetencia',
                fn() => SituacionCompetenciaResource::collection($this->situacionesCompetencia)
            ),

            'meta' => [
                'version'   => '1.0',
                'timestamp' => now()->toIso8601String(),
            ],

            'links' => [
                'self'       => route('api.ecosistemas.show', $this->id),
                'situaciones' => route('api.ecosistemas.situaciones', $this->id),
                'modulo'     => route('api.modulos.show', $this->modulo_id),
            ],
        ];
    }
}
```

### 3.3.4. SituacionCompetenciaResource

```php
// app/Http/Resources/SituacionCompetenciaResource.php

class SituacionCompetenciaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'codigo'            => $this->codigo,
            'titulo'            => $this->titulo,
            'descripcion'       => $this->descripcion,
            'umbral_maestria'   => (float) $this->umbral_maestria,
            'nivel_complejidad' => $this->nivel_complejidad,
            'activa'            => $this->activa,

            // Prerequisitos del grafo (solo códigos, para no crear recursión)
            'prerequisitos' => $this->whenLoaded(
                'prerequisitos',
                fn() => $this->prerequisitos->map(fn($pre) => [
                    'id'     => $pre->id,
                    'codigo' => $pre->codigo,
                ])
            ),

            // Nodos de requisito (conocimientos y habilidades previos)
            'nodos_requisito' => $this->whenLoaded(
                'nodosRequisito',
                fn() => $this->nodosRequisito->map(fn($nodo) => [
                    'tipo'        => $nodo->tipo,
                    'descripcion' => $nodo->descripcion,
                ])
            ),

            // CE del currículo que cubre (trazabilidad)
            'criterios_evaluacion' => $this->whenLoaded(
                'criteriosEvaluacion',
                fn() => $this->criteriosEvaluacion->map(fn($ce) => [
                    'codigo'      => $ce->codigo,
                    'descripcion' => $ce->descripcion,
                    'peso_en_sc'  => (float) $ce->pivot->peso_en_sc,
                ])
            ),
        ];
    }
}
```

### 3.3.5. PerfilHabilitacionResource

Este resource es el más importante de la API: su estructura está diseñada para ser directamente mapeada a la entidad NGSI-LD `PerfilHabilitacion` en la Unidad 6.

```php
// app/Http/Resources/PerfilHabilitacionResource.php

class PerfilHabilitacionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $conquistadas = $this->whenLoaded('situacionesConquistadas');

        return [
            'id'          => $this->id,

            // Campos que mapean directamente a propiedades NGSI-LD
            'estudiante'  => [
                'id'     => $this->estudiante_id,
                // Sin name ni email: privacidad por diseño
            ],
            'ecosistema'  => [
                'id'     => $this->ecosistema_laboral_id,
                'codigo' => $this->whenLoaded('ecosistemaLaboral',
                    fn() => $this->ecosistemaLaboral->codigo),
            ],

            'calificacion_actual'       => (float) $this->calificacion_actual,

            // Situaciones conquistadas con su gradiente de autonomía
            'situaciones_conquistadas'  => $this->whenLoaded(
                'situacionesConquistadas',
                fn() => SituacionConquistadaResource::collection($this->situacionesConquistadas)
            ),

            // Códigos para el motor ZDP (se expande en Unidad 5)
            'codigos_conquistados'      => $this->whenLoaded(
                'situacionesConquistadas',
                fn() => $this->situacionesConquistadas->pluck('codigo')
            ),

            'ngsi_ld_id' => sprintf(
                'urn:ngsi-ld:PerfilHabilitacion:estudiante-%d-ecosistema-%d',
                $this->estudiante_id,
                $this->ecosistema_laboral_id
            ),

            'meta' => [
                'version'      => '1.0',
                'timestamp'    => now()->toIso8601String(),
                'updated_at'   => $this->updated_at->toIso8601String(),
            ],
        ];
    }
}
```

### 3.3.6. SituacionConquistadaResource

```php
// app/Http/Resources/SituacionConquistadaResource.php

class SituacionConquistadaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'codigo'             => $this->codigo,
            'titulo'             => $this->titulo,
            'gradiente_autonomia' => $this->pivot->gradiente_autonomia,
            'puntuacion_conquista' => (float) $this->pivot->puntuacion_conquista,
            'intentos'           => $this->pivot->intentos,
            'fecha_conquista'    => $this->pivot->fecha_conquista,
        ];
    }
}
```

### 3.3.7. ProgresoEstudianteResource

```php
// app/Http/Resources/ProgresoEstudianteResource.php

class ProgresoEstudianteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $total        = $this->resource['total_scs'];
        $conquistadas = $this->resource['conquistadas'];

        return [
            'estudiante_id'  => $this->resource['estudiante_id'],
            'conquistadas'   => $conquistadas,
            'total_scs'      => $total,
            'progreso_pct'   => $total > 0 ? round(($conquistadas / $total) * 100, 1) : 0,
            'calificacion'   => (float) $this->resource['calificacion'],
            'detalle'        => $this->resource['detalle'],
        ];
    }
}
```

---

**Unidad anterior ←** [Unidad 2: Frontend: layout y vistas Blade para el marketplace](./02_frontend_layout.md)

  **Siguiente capítulo →** [Controladores API](./03_04_controladores_API.md)

**Siguiente unidad →** [Unidad 4: Autenticación con Sanctum + Keyrock](./04_autenticacion_sanctum_keyrock.md)
