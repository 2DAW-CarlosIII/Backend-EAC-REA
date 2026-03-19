## 6.4. Gráficas del docente

### 6.4.1. Crear las clases de chart

Con `ConsoleTVs/Charts` cada gráfica es una clase PHP que extiende `Chart`. La instancia se crea en el controlador, se configura con los datos del servicio y se pasa a la vista.

Empieza creando las 4 clases de chart para el panel del docente:

```bash
php artisan make:chart BarrasConquistasChart     ## bar
php artisan make:chart DoughnutGradienteChart    ## doughnut
php artisan make:chart LineasEvolucionChart      ## line
php artisan make:chart RadarHuellaChart          ## radar
```

Esto crea cuatro archivos en `app/Charts/`. Ábrelos y comprueba que extienden la clase base correcta:

```php
// app/Charts/BarrasConquistasChart.php

namespace App\Charts;

use ConsoleTVs\Charts\Classes\Chartjs\Chart;

class BarrasConquistasChart extends Chart
{
    /**
     * Inicialización obligatoria.
     */
    public function __construct()
    {
        parent::__construct();
    }
}
```

Los otros tres siguen exactamente el mismo patrón cambiando el nombre de clase. No añadas nada más: la configuración se hace desde el controlador.

### 6.4.2. El controlador del docente

```bash
php artisan make:controller Docente/AnalyticsController --invokable
```

```php
// app/Http/Controllers/Docente/AnalyticsController.php

namespace App\Http\Controllers\Docente;

use App\Charts\BarrasConquistasChart;
use App\Charts\DoughnutGradienteChart;
use App\Charts\LineasEvolucionChart;
use App\Http\Controllers\Controller;
use App\Models\EcosistemaLaboral;
use App\Services\EACAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AnalyticsController extends Controller
{
    public function __construct(
        private readonly EACAnalyticsService $analyticsService
    ) {}

    public function __invoke(Request $request, EcosistemaLaboral $ecosistema): View
    {
        // ── Gráfica 1: Ranking de SCs ─────────────────────────────────────
        $datosRanking = $this->analyticsService->rankingConquistas($ecosistema);

        $chartRanking = new BarrasConquistasChart();
        $chartRanking
            ->labels($datosRanking['labels'])
            ->dataset('Nº de conquistas', 'bar', $datosRanking['data'])
                ->backgroundColor($datosRanking['colores'])
                ->options([
                    'indexAxis'  => 'y',       // barras horizontales en Chart.js v4
                    'responsive' => true,
                    'plugins'    => [
                        'legend' => ['display' => false],
                        'title'  => [
                            'display' => true,
                            'text'    => 'SCs más conquistadas',
                        ],
                    ],
                ]);

        // ── Gráfica 2: Distribución del Gradiente ─────────────────────────
        $datosGradiente = $this->analyticsService->distribucionGradiente($ecosistema);

        $chartGradiente = new DoughnutGradienteChart();
        $chartGradiente
            ->labels($datosGradiente['labels'])
            ->dataset('Conquistas', 'doughnut', $datosGradiente['data'])
                ->backgroundColor($datosGradiente['colores'])
                ->options([
                    'responsive' => true,
                    'plugins'    => [
                        'legend' => ['position' => 'bottom'],
                        'title'  => [
                            'display' => true,
                            'text'    => 'Distribución del Gradiente de Autonomía',
                        ],
                    ],
                ]);

        // ── Gráfica 3: Evolución temporal ─────────────────────────────────
        $datosEvolucion = $this->analyticsService->evolucionTemporal($ecosistema, semanas: 8);

        $chartEvolucion = new LineasEvolucionChart();
        $chartEvolucion
            ->labels($datosEvolucion['labels'])
            ->dataset('Conquistas por semana', 'line', $datosEvolucion['data'])
                ->options([
                    'responsive' => true,
                    'plugins'    => [
                        'legend' => ['display' => false],
                        'title'  => [
                            'display' => true,
                            'text'    => 'Evolución de conquistas (últimas 8 semanas)',
                        ],
                    ],
                    'scales' => [
                        'y' => [
                            'beginAtZero' => true,
                            'ticks'       => ['stepSize' => 1],
                        ],
                    ],
                    'backgroundColor' => 'rgba(99, 102, 241, 0.15)',
                    'borderColor'     => '#6366f1',
                    'pointBackgroundColor' => '#6366f1',
                    'fill'            => true,
                ]);

        // ── Estadísticas de resumen ────────────────────────────────────────
        $totalEstudiantes = $ecosistema->perfilesHabilitacion()->count();
        $totalConquistas  = $ecosistema->situacionesCompetencia()
            ->withCount('perfilesHabilitacion as conquistas')
            ->get()
            ->sum('conquistas');
        $mediaConquistas  = $totalEstudiantes > 0
            ? round($totalConquistas / $totalEstudiantes, 1)
            : 0;

        return view('docente.analytics.show', compact(
            'ecosistema',
            'chartRanking',
            'chartGradiente',
            'chartEvolucion',
            'totalEstudiantes',
            'totalConquistas',
            'mediaConquistas',
        ));
    }
}
```

> **Nota sobre `indexAxis => 'y'`:** Chart.js v4 eliminó el tipo `horizontalBar` y lo unificó con `bar` usando la opción `indexAxis`. El paquete `ConsoleTVs/Charts` sigue exponiendo `horizontalBar` como alias por compatibilidad, pero la opción correcta para v4 es pasar `indexAxis: 'y'` dentro del bloque `options`.

### 6.4.3. La vista Blade del docente

```bash
php artisan make:view docente.analytics.show
```

```html
{{-- resources/views/docente/analytics/show.blade.php --}}
@extends('layouts.eac')

@section('title', 'Analítica — ' . $ecosistema->nombre)

@section('content')
<div class="container py-4">

    {{-- Cabecera --}}
    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <h1 class="h3 mb-0">Analítica del ecosistema</h1>
            <p class="text-muted mb-0">{{ $ecosistema->nombre }}</p>
        </div>
        <a href="{{ route('docente.ecosistemas.show', $ecosistema) }}"
           class="btn btn-outline-secondary btn-sm">
            ← Volver al ecosistema
        </a>
    </div>

    {{-- Tarjetas de resumen --}}
    <div class="row g-3 mb-4">
        <div class="col-sm-4">
            <div class="card text-center h-100">
                <div class="card-body">
                    <div class="display-6 fw-bold text-primary">{{ $totalEstudiantes }}</div>
                    <div class="text-muted small mt-1">Estudiantes matriculados</div>
                </div>
            </div>
        </div>
        <div class="col-sm-4">
            <div class="card text-center h-100">
                <div class="card-body">
                    <div class="display-6 fw-bold text-success">{{ $totalConquistas }}</div>
                    <div class="text-muted small mt-1">Conquistas totales</div>
                </div>
            </div>
        </div>
        <div class="col-sm-4">
            <div class="card text-center h-100">
                <div class="card-body">
                    <div class="display-6 fw-bold text-info">{{ $mediaConquistas }}</div>
                    <div class="text-muted small mt-1">Media de SCs por estudiante</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Fila 1: Ranking + Gradiente --}}
    <div class="row g-4 mb-4">
        <div class="col-lg-7">
            <div class="card h-100">
                <div class="card-body">
                    {!! $chartRanking->container() !!}
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card h-100">
                <div class="card-body d-flex align-items-center justify-content-center">
                    {!! $chartGradiente->container() !!}
                </div>
            </div>
        </div>
    </div>

    {{-- Fila 2: Evolución temporal --}}
    <div class="row g-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    {!! $chartEvolucion->container() !!}
                </div>
            </div>
        </div>
    </div>

</div>
@endsection

@push('scripts')
    {!! $chartRanking->script() !!}
    {!! $chartGradiente->script() !!}
    {!! $chartEvolucion->script() !!}
    {{-- Chart.js — necesario para ConsoleTVs/Charts --}}
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.5.1/dist/chart.umd.min.js"></script>
@endpush
```

> **¿Por qué `@push('scripts')` y no `@section('scripts')`?**
> `@push` acumula contenido en una pila: si varias vistas hijas hacen `@push('scripts')`, todos los bloques se añaden en orden. `@section` sobreescribiría el anterior. El layout base usa `@stack('scripts')` que vuelca la pila justo antes del `</body>`.

---

**Unidad anterior ←** [Unidad 5: Evaluación y Huella de Talento](./05_evaluacion_huella_talento.md)

**Siguiente capítulo →** [Unidad 6.5: Gráficas para el estudiante](./06_05_graficas_estudiante.md)

**Siguiente unidad →** [Unidad 7: Autenticación con Sanctum + Keyrock](./07_autenticacion_sanctum_keyrock.md)
