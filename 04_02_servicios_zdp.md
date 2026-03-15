## 4.2. Servicios de dominio: GrafoService y RecomendacionService

Los **servicios** en _Laravel_ son clases que encapsulan lógica de negocio compleja que no encaja naturalmente en un modelo Eloquent ni en un controlador. Son ideales para algoritmos como el cálculo de la ZDP o la generación de recomendaciones, que involucran múltiples entidades y reglas.

### 4.2.1. `GrafoService`

El servicio tiene dos responsabilidades: **validar** que el grafo no tiene ciclos al añadir una nueva arista, y **calcular** la ZDP dado un conjunto de códigos conquistados.

```bash
mkdir -p app/Services
```

```php
// app/Services/GrafoService.php

namespace App\Services;

use App\Models\EcosistemaLaboral;
use App\Models\SituacionCompetencia;
use Illuminate\Support\Collection;

class GrafoService
{
    /**
     * Calcula la Zona de Despliegue Proximal del estudiante.
     *
     * @param  EcosistemaLaboral  $ecosistema
     * @param  array<string>      $codigosConquistados  Códigos SC ya conquistados
     * @return Collection<SituacionCompetencia>
     */
    public function calcularZdp(
        EcosistemaLaboral $ecosistema,
        array $codigosConquistados
    ): Collection {
        $todas = $ecosistema->situacionesCompetencia()
            ->where('activa', true)
            ->with('prerequisitos:id,codigo')
            ->get();

        return $todas->filter(function (SituacionCompetencia $sc) use ($codigosConquistados) {
            // Condición 1: no conquistada
            if (in_array($sc->codigo, $codigosConquistados)) {
                return false;
            }

            // Condición 2: todos sus prerequisitos están conquistados
            $codigosRequisito = $sc->prerequisitos->pluck('codigo')->toArray();

            return empty($codigosRequisito)
                || count(array_diff($codigosRequisito, $codigosConquistados)) === 0;
        })->values();
    }

    /**
     * Devuelve las SCs bloqueadas: no conquistadas y con al menos un prerequisito
     * sin conquistar.
     *
     * @param  EcosistemaLaboral  $ecosistema
     * @param  array<string>      $codigosConquistados
     * @return Collection<SituacionCompetencia>
     */
    public function calcularBloqueadas(
        EcosistemaLaboral $ecosistema,
        array $codigosConquistados
    ): Collection {
        $todas = $ecosistema->situacionesCompetencia()
            ->where('activa', true)
            ->with('prerequisitos:id,codigo')
            ->get();

        return $todas->filter(function (SituacionCompetencia $sc) use ($codigosConquistados) {
            if (in_array($sc->codigo, $codigosConquistados)) {
                return false;
            }

            $codigosRequisito = $sc->prerequisitos->pluck('codigo')->toArray();

            return !empty($codigosRequisito)
                && count(array_diff($codigosRequisito, $codigosConquistados)) > 0;
        })->values();
    }

    /**
     * Clasifica todas las SCs del ecosistema en tres grupos:
     * 'conquistada', 'disponible' (ZDP) y 'bloqueada'.
     *
     * @param  EcosistemaLaboral  $ecosistema
     * @param  array<string>      $codigosConquistados
     * @return array{conquistadas: Collection, zdp: Collection, bloqueadas: Collection}
     */
    public function clasificar(
        EcosistemaLaboral $ecosistema,
        array $codigosConquistados
    ): array {
        $todas = $ecosistema->situacionesCompetencia()
            ->where('activa', true)
            ->with('prerequisitos:id,codigo', 'nodosRequisito')
            ->get();

        $conquistadas = $todas->filter(
            fn($sc) => in_array($sc->codigo, $codigosConquistados)
        )->values();

        $restantes = $todas->filter(
            fn($sc) => !in_array($sc->codigo, $codigosConquistados)
        );

        $zdp = $restantes->filter(function ($sc) use ($codigosConquistados) {
            $reqs = $sc->prerequisitos->pluck('codigo')->toArray();
            return empty($reqs)
                || count(array_diff($reqs, $codigosConquistados)) === 0;
        })->values();

        $bloqueadas = $restantes->filter(function ($sc) use ($codigosConquistados) {
            $reqs = $sc->prerequisitos->pluck('codigo')->toArray();
            return !empty($reqs)
                && count(array_diff($reqs, $codigosConquistados)) > 0;
        })->values();

        return compact('conquistadas', 'zdp', 'bloqueadas');
    }

    /**
     * Valida que añadir la arista (sc_id → sc_requisito_id) no introduce un ciclo
     * en el grafo. Usa DFS desde sc_requisito_id buscando sc_id.
     *
     * @throws \RuntimeException si la arista crea un ciclo
     */
    public function validarAristaAciclica(
        int $scId,
        int $scRequisitoId,
        EcosistemaLaboral $ecosistema
    ): void {
        if ($scId === $scRequisitoId) {
            throw new \RuntimeException(
                'Una SC no puede ser prerequisito de sí misma.'
            );
        }

        // Construir mapa de adyacencia actual (sin la nueva arista aún)
        $aristas = \DB::table('sc_precedencia')
            ->join('situaciones_competencia as sc', 'sc.id', '=', 'sc_precedencia.sc_id')
            ->where('sc.ecosistema_laboral_id', $ecosistema->id)
            ->pluck('sc_precedencia.sc_requisito_id', 'sc_precedencia.sc_id')
            ->toArray();

        // DFS desde $scRequisitoId: ¿podemos llegar a $scId?
        $visitados = [];
        $pila      = [$scRequisitoId];

        while (!empty($pila)) {
            $actual = array_pop($pila);

            if ($actual === $scId) {
                throw new \RuntimeException(
                    "La arista crea un ciclo: la SC #{$scId} ya es alcanzable "
                    . "desde la SC #{$scRequisitoId}."
                );
            }

            if (isset($visitados[$actual])) {
                continue;
            }

            $visitados[$actual] = true;

            foreach ($aristas as $origen => $destino) {
                if ($origen === $actual && !isset($visitados[$destino])) {
                    $pila[] = $destino;
                }
            }
        }
    }

    /**
     * Ordenación topológica del grafo (algoritmo de Kahn).
     * Devuelve las SCs en un orden de estudio válido (prerequisitos antes que dependientes).
     *
     * @param  EcosistemaLaboral  $ecosistema
     * @return Collection<SituacionCompetencia>   SCs ordenadas
     * @throws \RuntimeException si el grafo tiene ciclos
     */
    public function ordenTopologico(EcosistemaLaboral $ecosistema): Collection
    {
        $scs = $ecosistema->situacionesCompetencia()
            ->with('prerequisitos:id,codigo')
            ->get()
            ->keyBy('id');

        // Calcular grado de entrada de cada nodo
        $gradoEntrada = $scs->mapWithKeys(fn($sc) => [$sc->id => 0]);

        foreach ($scs as $sc) {
            foreach ($sc->prerequisitos as $pre) {
                $gradoEntrada[$sc->id]++;
            }
        }

        // Cola inicial: SCs sin prerequisitos
        $cola      = $gradoEntrada->filter(fn($grado) => $grado === 0)->keys()->toArray();
        $resultado = collect();

        while (!empty($cola)) {
            $id = array_shift($cola);
            $resultado->push($scs[$id]);

            // Reducir grado de entrada de los dependientes
            foreach ($scs[$id]->dependientes ?? [] as $dep) {
                $gradoEntrada[$dep->id]--;
                if ($gradoEntrada[$dep->id] === 0) {
                    $cola[] = $dep->id;
                }
            }
        }

        if ($resultado->count() !== $scs->count()) {
            throw new \RuntimeException(
                'El grafo de precedencia contiene ciclos. '
                . 'Revisa la tabla sc_precedencia del ecosistema #' . $ecosistema->id
            );
        }

        return $resultado;
    }
}
```

---

## 4.2.2. `RecomendacionService`

```php
// app/Services/RecomendacionService.php

namespace App\Services;

use App\Models\EcosistemaLaboral;
use App\Models\SituacionCompetencia;
use Illuminate\Support\Collection;

class RecomendacionService
{
    public function __construct(
        private readonly GrafoService $grafoService
    ) {}

    /**
     * Devuelve la SC más recomendada de la ZDP del estudiante,
     * o null si la ZDP está vacía (ecosistema completado).
     *
     * Criterios de ordenación (por prioridad):
     *   1. Menor nivel_complejidad
     *   2. Mayor número de CE pendientes que cubre
     *   3. Mayor número de SCs que desbloquearía su conquista
     */
    public function recomendar(
        EcosistemaLaboral $ecosistema,
        array $codigosConquistados
    ): ?SituacionCompetencia {
        $zdp = $this->grafoService->calcularZdp($ecosistema, $codigosConquistados);

        if ($zdp->isEmpty()) {
            return null;
        }

        // Cargar relaciones necesarias para los criterios 2 y 3
        $zdp->load([
            'criteriosEvaluacion',
            'dependientes.prerequisitos',
        ]);

        // CE ya cubiertos por las SCs conquistadas
        $ceYaCubiertos = $this->cesCubiertos($ecosistema, $codigosConquistados);

        return $zdp
            ->sortBy([
                // Criterio 1: menor complejidad primero
                fn($a, $b) => $a->nivel_complejidad <=> $b->nivel_complejidad,

                // Criterio 2: mayor cobertura de CE pendientes (descendente)
                fn($a, $b) => $this->cesPendientesCubiertos($b, $ceYaCubiertos)
                           <=> $this->cesPendientesCubiertos($a, $ceYaCubiertos),

                // Criterio 3: mayor número de SCs desbloqueadas (descendente)
                fn($a, $b) => $this->scsDesbloqueadas($b, $codigosConquistados)
                           <=> $this->scsDesbloqueadas($a, $codigosConquistados),
            ])
            ->first();
    }

    /**
     * Devuelve la ZDP ordenada con la puntuación de recomendación de cada SC.
     * Útil para mostrar al estudiante no solo la primera opción sino el ranking completo.
     *
     * @return Collection  Cada elemento: ['sc' => SituacionCompetencia, 'score' => int, 'motivos' => array]
     */
    public function rankingZdp(
        EcosistemaLaboral $ecosistema,
        array $codigosConquistados
    ): Collection {
        $zdp = $this->grafoService->calcularZdp($ecosistema, $codigosConquistados);

        if ($zdp->isEmpty()) {
            return collect();
        }

        $zdp->load(['criteriosEvaluacion', 'dependientes.prerequisitos']);

        $ceYaCubiertos = $this->cesCubiertos($ecosistema, $codigosConquistados);
        $maxComplejidad = $zdp->max('nivel_complejidad') ?: 1;

        return $zdp->map(function (SituacionCompetencia $sc) use ($codigosConquistados, $ceYaCubiertos, $maxComplejidad) {
            $puntosSencillez  = ($maxComplejidad - $sc->nivel_complejidad + 1) * 10;
            $puntosCe         = $this->cesPendientesCubiertos($sc, $ceYaCubiertos) * 5;
            $puntosDesbloqueo = $this->scsDesbloqueadas($sc, $codigosConquistados) * 3;
            $score            = $puntosSencillez + $puntosCe + $puntosDesbloqueo;

            return [
                'sc'      => $sc,
                'score'   => $score,
                'motivos' => [
                    'nivel_complejidad'   => $sc->nivel_complejidad,
                    'ce_pendientes'       => $this->cesPendientesCubiertos($sc, $ceYaCubiertos),
                    'scs_desbloqueadas'   => $this->scsDesbloqueadas($sc, $codigosConquistados),
                ],
            ];
        })
        ->sortByDesc('score')
        ->values();
    }

    // ─── Helpers privados ────────────────────────────────────────────────────

    /**
     * IDs de CE ya cubiertos por las SCs conquistadas.
     */
    private function cesCubiertos(
        EcosistemaLaboral $ecosistema,
        array $codigosConquistados
    ): array {
        if (empty($codigosConquistados)) {
            return [];
        }

        return $ecosistema->situacionesCompetencia()
            ->whereIn('codigo', $codigosConquistados)
            ->with('criteriosEvaluacion:id')
            ->get()
            ->flatMap(fn($sc) => $sc->criteriosEvaluacion->pluck('id'))
            ->unique()
            ->toArray();
    }

    /**
     * Número de CE que esta SC cubre y que aún no están cubiertos.
     */
    private function cesPendientesCubiertos(
        SituacionCompetencia $sc,
        array $ceYaCubiertos
    ): int {
        return $sc->criteriosEvaluacion
            ->filter(fn($ce) => !in_array($ce->id, $ceYaCubiertos))
            ->count();
    }

    /**
     * Número de SCs adicionales que quedarían en la ZDP si se conquistara esta SC.
     */
    private function scsDesbloqueadas(
        SituacionCompetencia $sc,
        array $codigosConquistados
    ): int {
        $hipotetico = array_merge($codigosConquistados, [$sc->codigo]);

        return $sc->dependientes->filter(function ($dep) use ($hipotetico) {
            $reqs = $dep->prerequisitos->pluck('codigo')->toArray();
            return count(array_diff($reqs, $hipotetico)) === 0;
        })->count();
    }
}
```

---

## 4.2.3. Registro de servicios

Registra ambos servicios en el contenedor para poder inyectarlos por tipo:

```php
// app/Providers/AppServiceProvider.php

use App\Services\GrafoService;
use App\Services\RecomendacionService;

public function register(): void
{
    $this->app->singleton(GrafoService::class);

    $this->app->singleton(RecomendacionService::class, function ($app) {
        return new RecomendacionService($app->make(GrafoService::class));
    });
}
```

---

**Unidad anterior ←** [Unidad 3: API REST EAC](./03_api_rest_eac.md)

**Siguiente capítulo →** [Endpoint API ZDP](./04_03_endpoint_api_zdp.md)

**Siguiente unidad →** [Unidad 5: Evaluación y seguimiento](./05_evaluacion_seguimiento.md)
