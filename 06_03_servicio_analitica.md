## 6.3. El servicio de analítica: `EACAnalyticsService`

### 6.3.1. Responsabilidad del servicio

`EACAnalyticsService` centraliza toda la lógica de agregación de datos para las gráficas. Los controladores no hacen queries directamente: delegan en el servicio y reciben arrays listos para alimentar las instancias de chart.

```
Controller → EACAnalyticsService → Modelos Eloquent → Base de datos
                    ↓
            Array de datos para Chart
                    ↓
            ConsoleTVs\Charts\Chart → Vista Blade
```

### 6.3.2. Crear el servicio

```php
// app/Services/EACAnalyticsService.php

namespace App\Services;

use App\Models\EcosistemaLaboral;
use App\Models\PerfilHabilitacion;
use App\Models\SituacionCompetencia;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EACAnalyticsService
{
    public function __construct(
        private readonly CalificacionService $calificacionService
    ) {}

    // ─── GRÁFICA 1 — Ranking de SCs por número de conquistas ──────────────

    /**
     * Devuelve las SCs del ecosistema ordenadas por número de estudiantes
     * que las han conquistado (de mayor a menor).
     *
     * @return array{labels: string[], data: int[], colores: string[]}
     */
    public function rankingConquistas(EcosistemaLaboral $ecosistema): array
    {
        $resultados = SituacionCompetencia::query()
            ->where('ecosistema_laboral_id', $ecosistema->id)
            ->withCount(['perfilesHabilitacion as conquistas'])
            ->orderByDesc('conquistas')
            ->get();

        $labels  = $resultados->pluck('codigo')->toArray();
        $data    = $resultados->pluck('conquistas')->toArray();

        // Paleta de colores: verde → amarillo → rojo según posición
        $colores = $this->paletaRankingBarras(count($labels));

        return compact('labels', 'data', 'colores');
    }

    // ─── GRÁFICA 2 — Distribución del Gradiente de Autonomía ──────────────

    /**
     * Cuenta cuántas conquistas hay de cada nivel de gradiente
     * en el ecosistema dado.
     *
     * @return array{labels: string[], data: int[], colores: string[]}
     */
    public function distribucionGradiente(EcosistemaLaboral $ecosistema): array
    {
        $niveles = ['asistido', 'guiado', 'supervisado', 'autonomo'];
        $labels  = ['Asistido', 'Guiado', 'Supervisado', 'Autónomo'];
        $colores = ['#ef4444', '#f97316', '#eab308', '#22c55e'];

        $data = collect($niveles)->map(function (string $nivel) use ($ecosistema) {
            return DB::table('perfil_situacion')
                ->join('situaciones_competencia', 'perfil_situacion.situacion_competencia_id', '=', 'situaciones_competencia.id')
                ->where('situaciones_competencia.ecosistema_laboral_id', $ecosistema->id)
                ->where('perfil_situacion.gradiente_autonomia', $nivel)
                ->count();
        })->toArray();

        return compact('labels', 'data', 'colores');
    }

    // ─── GRÁFICA 3 — Evolución temporal (conquistas por semana) ───────────

    /**
     * Agrupa las conquistas por semana (lunes de cada semana ISO).
     * Devuelve las últimas $semanas semanas con actividad o vacías si no las hay.
     *
     * @return array{labels: string[], data: int[]}
     */
    public function evolucionTemporal(EcosistemaLaboral $ecosistema, int $semanas = 8): array
    {
        // Conquistas agrupadas por semana (formato YYYY-W)
        $registros = DB::table('perfil_situacion')
            ->join('situaciones_competencia', 'perfil_situacion.situacion_competencia_id', '=', 'situaciones_competencia.id')
            ->join('perfiles_habilitacion', 'perfil_situacion.perfil_habilitacion_id', '=', 'perfiles_habilitacion.id')
            ->where('situaciones_competencia.ecosistema_laboral_id', $ecosistema->id)
            ->whereNotNull('perfil_situacion.fecha_conquista')
            ->selectRaw("DATE_FORMAT(perfil_situacion.fecha_conquista, '%x-W%v') as semana, COUNT(*) as total")
            ->groupBy('semana')
            ->orderBy('semana')
            ->get()
            ->pluck('total', 'semana');

        // Construir el rango de las últimas $semanas semanas
        $labels = [];
        $data   = [];

        for ($i = $semanas - 1; $i >= 0; $i--) {
            $fecha  = now()->startOfWeek()->subWeeks($i);
            $clave  = $fecha->format('o-W\Wv'); // ISO year + ISO week
            $claveDB = $fecha->format('o-\WW');  // formato del DATE_FORMAT
            $labels[] = 'S' . $fecha->weekOfYear . ' (' . $fecha->format('d/m') . ')';
            $data[]   = (int) ($registros->get($claveDB, 0));
        }

        return compact('labels', 'data');
    }

    // ─── GRÁFICA 4 — Radar de Huella de Talento (estudiante individual) ───

    /**
     * Extrae las puntuaciones por RA del CalificacionService::desglose()
     * y las devuelve en formato para un gráfico radar.
     *
     * @return array{labels: string[], data: float[], max: int}
     */
    public function radarHuella(PerfilHabilitacion $perfil): array
    {
        $desglose = $this->calificacionService->desglose($perfil);

        $labels = [];
        $data   = [];

        foreach ($desglose['desglose_ra'] as $ra) {
            $labels[] = $ra['ra'];   // código del RA, ej. "RA1"
            $data[]   = round($ra['puntuacion'], 1);
        }

        return [
            'labels' => $labels,
            'data'   => $data,
            'max'    => 100,
        ];
    }

    // ─── Helpers privados ──────────────────────────────────────────────────

    /**
     * Genera una paleta de colores del verde al rojo para barras del ranking.
     * La primera barra (más conquistas) es verde; la última es roja.
     */
    private function paletaRankingBarras(int $n): array
    {
        if ($n === 0) {
            return [];
        }

        $paleta = [
            '#22c55e', '#84cc16', '#a3e635', '#facc15',
            '#fb923c', '#f87171', '#ef4444',
        ];

        $colores = [];
        for ($i = 0; $i < $n; $i++) {
            $indice    = (int) round($i / max($n - 1, 1) * (count($paleta) - 1));
            $colores[] = $paleta[$indice];
        }

        return $colores;
    }
}
```

### 6.3.3. Registrar el servicio en `AppServiceProvider`

```php
// app/Providers/AppServiceProvider.php

use App\Services\EACAnalyticsService;

public function register(): void
{
    // ... registros previos ...

    $this->app->singleton(EACAnalyticsService::class, function ($app) {
        return new EACAnalyticsService(
            $app->make(CalificacionService::class)
        );
    });
}
```

---

**Unidad anterior ←** [Unidad 5: Evaluación y Huella de Talento](./05_evaluacion_huella_talento.md)

**Siguiente capítulo →** [Unidad 6.4: Gráficas del docente](./06_04_graficas_docente.md)

**Siguiente unidad →** [Unidad 7: Autenticación con Sanctum + Keyrock](./07_autenticacion_sanctum_keyrock.md)
