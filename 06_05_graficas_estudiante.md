## 6.5. Gráfica del estudiante: radar de la Huella de Talento

### 6.5.1. El controlador del estudiante

```bash
php artisan make:controller Estudiante/HuellaRadarController --invokable
```

```php
<?php
// app/Http/Controllers/Estudiante/HuellaRadarController.php

namespace App\Http\Controllers\Estudiante;

use App\Charts\RadarHuellaChart;
use App\Http\Controllers\Controller;
use App\Models\EcosistemaLaboral;
use App\Models\PerfilHabilitacion;
use App\Services\EACAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HuellaRadarController extends Controller
{
    public function __construct(
        private readonly EACAnalyticsService $analyticsService
    ) {}

    public function __invoke(Request $request, EcosistemaLaboral $ecosistema): View
    {
        $estudiante = $request->user();

        $perfil = PerfilHabilitacion::where('estudiante_id', $estudiante->id)
            ->where('ecosistema_laboral_id', $ecosistema->id)
            ->firstOrFail();

        $datosRadar = $this->analyticsService->radarHuella($perfil);

        $chartRadar = new RadarHuellaChart();
        $chartRadar
            ->labels($datosRadar['labels'])
            ->dataset('Mi cobertura competencial', 'radar', $datosRadar['data'])
                ->backgroundColor('rgba(99, 102, 241, 0.25)')
                ->borderColor('#6366f1')
                ->pointBackgroundColor('#6366f1')
                ->options([
                    'responsive' => true,
                    'plugins'    => [
                        'legend' => ['position' => 'top'],
                        'title'  => [
                            'display' => true,
                            'text'    => 'Huella de Talento — ' . $ecosistema->nombre,
                        ],
                    ],
                    'scales' => [
                        'r' => [
                            'min'        => 0,
                            'max'        => $datosRadar['max'],
                            'ticks'      => ['stepSize' => 20],
                            'pointLabels'=> ['font' => ['size' => 13]],
                        ],
                    ],
                ]);

        // Añadir el dataset de referencia (100 en cada RA) para contextualizar
        $chartRadar
            ->dataset('Máximo posible', 'radar', array_fill(0, count($datosRadar['labels']), 100))
                ->backgroundColor('rgba(229, 231, 235, 0.15)')
                ->borderColor('#d1d5db')
                ->borderDash([5, 5])
                ->pointRadius(0);

        $calificacion = $perfil->calificacion_actual;

        return view('estudiante.huella-radar', compact(
            'ecosistema',
            'perfil',
            'chartRadar',
            'calificacion',
        ));
    }
}
```

### 6.5.2. La vista Blade del estudiante

```bash
touch resources/views/estudiante/huella-radar.blade.php
```

```html
{{-- resources/views/estudiante/huella-radar.blade.php --}}
@extends('layouts.app')

@section('title', 'Mi Huella de Talento — ' . $ecosistema->nombre)

@section('content')
<div class="container py-4">

    {{-- Cabecera --}}
    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <h1 class="h3 mb-0">Mi Huella de Talento</h1>
            <p class="text-muted mb-0">{{ $ecosistema->nombre }}</p>
        </div>
        <a href="{{ route('estudiante.modulo', $ecosistema->modulo) }}"
           class="btn btn-outline-secondary btn-sm">
            ← Volver al módulo
        </a>
    </div>

    <div class="row g-4">

        {{-- Radar --}}
        <div class="col-lg-7">
            <div class="card">
                <div class="card-body" style="min-height: 400px; display:flex; align-items:center; justify-content:center;">
                    {!! $chartRadar->container() !!}
                </div>
            </div>
        </div>

        {{-- Panel lateral --}}
        <div class="col-lg-5 d-flex flex-column gap-3">

            {{-- Calificación actual --}}
            <div class="card text-center">
                <div class="card-body">
                    <div class="text-muted small mb-1">Calificación actual del módulo</div>
                    <div class="display-4 fw-bold
                        @if($calificacion >= 9) text-success
                        @elseif($calificacion >= 5) text-primary
                        @else text-danger
                        @endif">
                        {{ number_format($calificacion, 2) }}
                        <span class="fs-5 text-muted">/10</span>
                    </div>
                    <div class="text-muted small mt-2">
                        Calculada a partir de tus conquistas y Gradiente de Autonomía
                    </div>
                </div>
            </div>

            {{-- Explicación pedagógica --}}
            <div class="card border-info">
                <div class="card-body">
                    <h6 class="card-title text-info">¿Cómo se lee este gráfico?</h6>
                    <p class="card-text small text-muted mb-2">
                        Cada eje representa un <strong>Resultado de Aprendizaje</strong> del módulo.
                        La zona sombreada azul muestra tu cobertura real, ponderada por el Gradiente
                        de Autonomía de cada SC conquistada.
                    </p>
                    <p class="card-text small text-muted mb-0">
                        La línea gris discontinua es el máximo posible (100 en cada RA). Cuanto
                        más se acerque tu huella al contorno externo, mayor es tu dominio competencial.
                    </p>
                </div>
            </div>

            {{-- Enlace a la huella exportable --}}
            <div class="card">
                <div class="card-body">
                    <h6 class="card-title">Exportar Huella de Talento</h6>
                    <p class="card-text small text-muted mb-3">
                        Genera un snapshot JSON de tu estado competencial actual. Podrá publicarse
                        en el VFDS para que empresas y administraciones puedan consultarlo.
                    </p>
                    <button class="btn btn-outline-primary btn-sm w-100" id="btnGenerarHuella">
                        Generar snapshot JSON
                    </button>
                    <div id="huellaResultado" class="mt-3 d-none">
                        <div class="alert alert-success small mb-0">
                            ✅ Huella generada correctamente.
                            <a href="{{ route('estudiante.huellas', $ecosistema) }}" class="alert-link">
                                Ver historial
                            </a>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>
@endsection

@push('scripts')
    {!! $chartRadar->script() !!}

    <script>
    document.getElementById('btnGenerarHuella')?.addEventListener('click', function () {
        const btn = this;
        btn.disabled = true;
        btn.textContent = 'Generando…';

        fetch('{{ route("api.v1.estudiante.huella.store", $ecosistema) }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            },
        })
        .then(r => r.json())
        .then(() => {
            document.getElementById('huellaResultado').classList.remove('d-none');
            btn.textContent = 'Generar snapshot JSON';
            btn.disabled = false;
        })
        .catch(() => {
            btn.textContent = 'Error — inténtalo de nuevo';
            btn.disabled = false;
        });
    });
    </script>
@endpush
```

---

**Unidad anterior ←** [Unidad 5: Evaluación y Huella de Talento](./05_evaluacion_huella_talento.md)

**Siguiente capítulo →** [Unidad 6.6: Rutas para las gráficas](./06_06_rutas_graficas.md)

**Siguiente unidad →** [Unidad 7: Autenticación con Sanctum + Keyrock](./07_autenticacion_sanctum_keyrock.md)
