## 4.4. Integración en las vistas Blade

Vamos a enriquecer las vistas del estudiante con la información de la ZDP calculada por `GrafoService`. Esto permitirá que el estudiante vea claramente qué _SCs_ ha conquistado, cuáles tiene disponibles para acometer a continuación (ZDP) y cuáles están bloqueadas por requisitos pendientes.

### 4.4.1. Cambios en el controlador del dashboard

Para ello, vamos a modificar el controlador `app/Http/Controllers/Estudiante/DashboardController.php`, que creamos en la [Unidad 2](./02_07_controladores.md#272-controladores-del-estudiante) con los siguientes cambios:

```diff
 namespace App\Http\Controllers\Estudiante;
 
 use App\Http\Controllers\Controller;
+use App\Services\GrafoService;
 use Illuminate\Contracts\View\View;
 use Illuminate\Http\Request;
 
@@ -11,7 +12,7 @@ class DashboardController extends Controller
     /**
      * Handle the incoming request.
      */
-    public function __invoke(): View
+    public function __invoke(GrafoService $grafoService): View
     {
         $perfiles = auth()->user()
             ->perfilesHabilitacion()
@@ -22,6 +23,21 @@ public function __invoke(): View
             ])
             ->get();
 
+        // Añadir resumen ZDP a cada perfil para mostrarlo en las tarjetas del dashboard
+        $perfiles = $perfiles->map(function ($perfil) use ($grafoService) {
+            $codigosConquistados = $perfil->codigosConquistados();
+            $clasificacion       = $grafoService->clasificar(
+                $perfil->ecosistemaLaboral,
+                $codigosConquistados
+            );
+
+            $perfil->zdp_count       = $clasificacion['zdp']->count();
+            $perfil->completado      = $clasificacion['zdp']->isEmpty()
+                                    && $clasificacion['bloqueadas']->isEmpty();
+
+            return $perfil;
+        });
+
         return view('estudiante.dashboard', compact('perfiles'));
     }
 }
```

### 4.4.2 Cambios en la vista del dashboard

Ahora que cada perfil tiene la información de cuántas _SCs_ hay en su ZDP y si el ecosistema está completado, podemos mostrar esta información en la vista `estudiante/dashboard.blade.php`. Sustituye el contenido interior de la tarjeta de cada perfil (`<div class="card">`) por el siguiente código:

```html
            <div class="bg-white border border-gray-200 rounded-xl p-5 flex items-center gap-6 flex-wrap">

                {{-- Info del módulo --}}
                <div class="flex-1 min-w-[200px]">
                    <p class="font-mono text-xs text-gray-400">
                        {{ $perfil->ecosistemaLaboral->modulo->codigo }}
                    </p>
                    <h3 class="font-semibold text-gray-900 mt-0.5">
                        {{ $perfil->ecosistemaLaboral->modulo->nombre }}
                    </h3>
                    <p class="text-xs text-gray-400 mt-1">
                        {{ $perfil->ecosistemaLaboral->modulo->cicloFormativo->nombre }}
                    </p>
                </div>

                {{-- Barra de progreso --}}
                @php
                    $total        = $perfil->ecosistemaLaboral->situacionesCompetencia->count();
                    $conquistadas = $perfil->situacionesConquistadas->count();
                    $progreso     = $total > 0 ? round(($conquistadas / $total) * 100) : 0;
                @endphp

                <div class="flex-1 min-w-[160px]">
                    <div class="flex justify-between text-xs text-gray-500 mb-1">
                        <span>{{ $conquistadas }} / {{ $total }} SCs</span>
                        <span>{{ $progreso }}%</span>
                    </div>
                    <div class="w-full bg-gray-100 rounded-full h-2">
                        <div class="h-2 rounded-full transition-all
                                    {{ $perfil->completado ? 'bg-green-500' : 'bg-vfds-primary' }}"
                            style="width: {{ $progreso }}%">
                        </div>
                    </div>

                    {{-- Línea de estado: disponibles o completado --}}
                    <p class="text-xs mt-1
                            {{ $perfil->completado ? 'text-green-600 font-medium' : 'text-gray-400' }}">
                        @if($perfil->completado)
                            ✓ Ecosistema completado
                        @elseif($perfil->zdp_count > 0)
                            {{ $perfil->zdp_count }}
                            {{ Str::plural('SC disponible', $perfil->zdp_count) }} ahora
                        @else
                            Sin SCs disponibles en este momento
                        @endif
                    </p>
                </div>

                {{-- Calificación --}}
                <div class="text-center min-w-[60px]">
                    <p class="text-2xl font-bold
                            {{ $perfil->completado ? 'text-green-600' : 'text-vfds-primary' }}">
                        {{ number_format($perfil->calificacion_actual, 1) }}
                    </p>
                    <p class="text-xs text-gray-400">Calificación</p>
                </div>

                {{-- Acción --}}
                <a href="{{ route('estudiante.modulo', $perfil->ecosistemaLaboral->modulo) }}"
                class="bg-vfds-primary hover:bg-vfds-primary/80 text-sm font-medium
                        px-4 py-2 rounded-lg transition whitespace-nowrap">
                    {{ $perfil->completado ? 'Ver resumen' : 'Continuar' }}
                </a>

            </div>
```

### 4.4.3. Nuevo controlador del módulo del estudiante

Necesitamos crear un nuevo controlador para la vista del módulo, que se encargue de cargar el módulo, su ecosistema laboral asociado, el perfil del estudiante en ese ecosistema y la clasificación de las _SCs_ para mostrarla en la vista del módulo, así como la recomendación personalizada. Para ello, ejecuta el siguiente comando:

```bash
php artisan make:controller Estudiante/ModuloController
```

```php

namespace App\Http\Controllers\Estudiante;

use App\Http\Controllers\Controller;
use App\Models\FamiliaProfesional;
use App\Models\Modulo;
use App\Models\PerfilHabilitacion;
use App\Services\GrafoService;
use App\Services\RecomendacionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ModuloController extends Controller
{
    public function __construct(
        private readonly GrafoService         $grafoService,
        private readonly RecomendacionService $recomendacionService,
    ) {}

    /**
    * Handle the incoming request.
    */
    public function index(Request $request) : View
    {
        $familias = FamiliaProfesional::orderBy('nombre')->get();

        $modulos = Modulo::with([
            'cicloFormativo.familiaProfesional',
            'ecosistemasLaborales' => fn($q) => $q->where('activo', true),
        ])
        ->whereHas('ecosistemasLaborales', fn($q) => $q->where('activo', true))
        ->whereHas('matriculas', fn($q) => $q->where('estudiante_id', auth()->id()))
        ->when($request->filled('familia'), fn($q) =>
            $q->whereHas('cicloFormativo',
                fn($q2) => $q2->where('familia_profesional_id', $request->familia))
        )
        ->orderBy('codigo')
        ->paginate(15);

        return view('publico.modulos.index', compact('modulos', 'familias'));
    }

    public function show(Modulo $modulo): View
    {
        abort_unless(
            auth()->user()->matriculas()->where('modulo_id', $modulo->id)->exists(),
            403, 'No estás matriculado en este módulo.'
        );

        $ecosistema = $modulo->ecosistemasLaborales()
            ->where('activo', true)
            ->firstOrFail();

        $perfil = PerfilHabilitacion::where('estudiante_id', auth()->id())
            ->where('ecosistema_laboral_id', $ecosistema->id)
            ->with('situacionesConquistadas')
            ->first();

        $codigosConquistados = $perfil?->codigosConquistados() ?? [];

        $clasificacion = $this->grafoService->clasificar($ecosistema, $codigosConquistados);
        $recomendacion = $this->recomendacionService->recomendar($ecosistema, $codigosConquistados);

        return view('estudiante.modulo', compact(
            'modulo', 'ecosistema', 'perfil',
            'clasificacion', 'recomendacion', 'codigosConquistados'
        ));
    }
}
```

### 4.4.4. Rutas de módulos de estudiante

Añade las rutas _web_ que permitan alcanzar los métodos definidos en el controlador de _Modulo_ del estudiante:

```php
       Route::get('/modulos',         [Estudiante\ModuloController::class, 'index'])->name('modulos.index');
       Route::get('/modulos/{modulo}', [Estudiante\ModuloController::class, 'show'])->name('modulo');
```

### 4.4.5. Creación de la vista del módulo para el estudiante

Crearemos una vista `estudiante/modulo.blade.php` que muestre la información del módulo, el ecosistema laboral asociado, el perfil del estudiante y la clasificación de las _SCs_ (conquistadas, disponibles en ZDP y bloqueadas), así como la recomendación personalizada. Para crear la vista, ejecuta el siguiente comando:

```bash
php artisan make:view estudiante.modulo
```

cuyo contenido es:

```html
@extends('layouts.estudiante')

@section('title', $modulo->nombre)

@section('content')

    {{-- Breadcrumb --}}
    <nav class="text-sm text-gray-500 mb-6">
        <a href="{{ route('estudiante.dashboard') }}" class="hover:text-gray-700">Mi espacio</a>
        <span class="mx-2">›</span>
        <span class="text-gray-900">{{ $modulo->nombre }}</span>
    </nav>

    {{-- Cabecera del módulo --}}
    <div class="mb-8 flex items-start justify-between flex-wrap gap-4">
        <div>
            <p class="font-mono text-xs text-gray-400">{{ $modulo->codigo }}</p>
            <h1 class="text-2xl font-bold text-gray-900 mt-0.5">{{ $modulo->nombre }}</h1>
            @if($perfil)
                <p class="text-sm text-gray-500 mt-1">
                    Calificación actual:
                    <span class="font-semibold text-vfds-primary">
                        {{ number_format($perfil->calificacion_actual, 2) }}
                    </span>
                </p>
            @endif
        </div>
        <a href="{{ route('publico.modulos.show', $modulo) }}"
           class="text-sm text-gray-400 hover:text-gray-600 underline">
            Ver detalle del módulo
        </a>
    </div>

    {{-- Baner de recomendación (solo si la ZDP no está vacía) --}}
    @if($recomendacion)
        <div class="bg-vfds-primary/5 border border-vfds-primary/20 rounded-xl px-5 py-4 mb-8
                    flex items-start gap-4">
            <div class="flex-shrink-0 w-9 h-9 rounded-full bg-vfds-primary/10 flex items-center
                        justify-center text-vfds-primary font-bold text-sm">
                →
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-xs font-semibold uppercase tracking-wide text-vfds-primary mb-0.5">
                    Recomendación
                </p>
                <p class="text-sm font-medium text-gray-800">
                    <span class="font-mono text-vfds-primary mr-2">{{ $recomendacion->codigo }}</span>
                    {{ $recomendacion->titulo }}
                </p>
                <p class="text-xs text-gray-500 mt-1">
                    Nivel de complejidad {{ $recomendacion->nivel_complejidad }}/5
                    @if($recomendacion->prerequisitos->isEmpty())
                        · Sin prerequisitos
                    @else
                        · Requiere: {{ $recomendacion->prerequisitos->pluck('codigo')->join(', ') }}
                    @endif
                </p>
            </div>
        </div>
    @elseif($clasificacion['zdp']->isEmpty() && $clasificacion['bloqueadas']->isEmpty())
        <div class="bg-green-50 border border-green-200 rounded-xl px-5 py-4 mb-8 flex items-center gap-3">
            <span class="text-green-500 text-xl">✓</span>
            <p class="text-green-700 text-sm font-medium">
                Has completado todas las situaciones de competencia de este ecosistema.
            </p>
        </div>
    @endif

    {{-- Leyenda de estados --}}
    <div class="flex flex-wrap gap-3 mb-6 text-xs">
        @foreach([
            ['bg-green-100 text-green-700',             'Conquistadas',  $clasificacion['conquistadas']->count()],
            ['bg-vfds-primary/10 text-vfds-primary',    'Disponibles',   $clasificacion['zdp']->count()],
            ['bg-gray-100 text-gray-400',               'Bloqueadas',    $clasificacion['bloqueadas']->count()],
        ] as [$cls, $label, $count])
            <span class="flex items-center gap-1.5 px-3 py-1 rounded-full {{ $cls }}">
                {{ $label }}
                <span class="font-bold">{{ $count }}</span>
            </span>
        @endforeach
    </div>

    {{-- Grupos de SCs --}}
    @foreach([
        'zdp'          => ['Disponibles',  'border-vfds-primary/30 bg-vfds-primary/5'],
        'conquistadas' => ['Conquistadas', 'border-green-200 bg-green-50'],
        'bloqueadas'   => ['Bloqueadas',   'border-gray-200 bg-gray-50'],
    ] as $grupo => [$label, $estilos])

        @php $items = $clasificacion[$grupo]; @endphp

        @if($items->isNotEmpty())
            <section class="mb-8">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500 mb-3">
                    {{ $label }} ({{ $items->count() }})
                </h2>

                <div class="space-y-2">
                    @foreach($items as $sc)
                        <div class="border {{ $estilos }} rounded-xl p-4
                                    flex items-start gap-3
                                    {{ $recomendacion?->id === $sc->id ? 'ring-2 ring-vfds-primary' : '' }}">

                            {{-- Código SC --}}
                            <span class="font-mono text-xs px-2 py-0.5 rounded
                                         bg-white border border-gray-200 text-gray-600
                                         flex-shrink-0 mt-0.5">
                                {{ $sc->codigo }}
                            </span>

                            {{-- Título, nodos y prerequisitos faltantes --}}
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-800">
                                    {{ $sc->titulo }}
                                    @if($recomendacion?->id === $sc->id)
                                        <span class="ml-2 text-xs font-normal text-vfds-primary">
                                            ← recomendada
                                        </span>
                                    @endif
                                </p>

                                {{-- Nodos de requisito --}}
                                @if($sc->nodosRequisito->isNotEmpty())
                                    <div class="mt-2 flex flex-wrap gap-1">
                                        @foreach($sc->nodosRequisito as $nodo)
                                            <span class="text-xs bg-white border border-gray-200
                                                         rounded px-2 py-0.5 text-gray-500">
                                                {{ ucfirst($nodo->tipo) }}: {{ Str::limit($nodo->descripcion, 50) }}
                                            </span>
                                        @endforeach
                                    </div>
                                @endif

                                {{-- Prerequisitos pendientes (solo bloqueadas) --}}
                                @if($grupo === 'bloqueadas')
                                    @php
                                        $pendientes = $sc->prerequisitos
                                            ->pluck('codigo')
                                            ->filter(fn($c) => !in_array($c, $codigosConquistados));
                                    @endphp
                                    <p class="text-xs text-gray-400 mt-1">
                                        Pendiente de conquistar:
                                        <span class="font-medium text-gray-600">
                                            {{ $pendientes->join(', ') }}
                                        </span>
                                    </p>
                                @endif
                            </div>

                            {{-- Indicador de complejidad --}}
                            <div class="flex flex-col items-end gap-2 flex-shrink-0">
                                <div class="flex items-center gap-0.5">
                                    @for($i = 1; $i <= 5; $i++)
                                        <span class="w-1.5 h-1.5 rounded-full
                                            {{ $i <= $sc->nivel_complejidad
                                                ? 'bg-vfds-primary'
                                                : 'bg-gray-200' }}">
                                        </span>
                                    @endfor
                                </div>

                                {{-- Badge de gradiente (solo conquistadas) --}}
                                @if($grupo === 'conquistadas')
                                    @php
                                        $pivot = $perfil->situacionesConquistadas
                                            ->firstWhere('codigo', $sc->codigo)?->pivot;
                                    @endphp
                                    <x-gradiente-badge
                                        :codigo="$sc->codigo"
                                        :gradiente="$pivot?->gradiente_autonomia"
                                    />
                                @endif
                            </div>

                        </div>
                    @endforeach
                </div>
            </section>
        @endif

    @endforeach

@endsection
```

---

**Unidad anterior ←** [Unidad 3: API REST EAC](./03_api_rest_eac.md)

**Siguiente capítulo →** [Comprobaciones](./04_05_comprobaciones.md)

**Siguiente unidad →** [Unidad 5: Evaluación y seguimiento](./05_evaluacion_seguimiento.md)
