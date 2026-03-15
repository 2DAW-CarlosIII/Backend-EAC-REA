## 4.4. Integración en las vistas Blade

El controlador `EstudianteDashboardController@modulo` de la Unidad 2 ya clasificaba las SCs en el propio controlador web. Ahora que existe `GrafoService`, extrae esa lógica al servicio para no duplicarla:

```php
// app/Http/Controllers/Estudiante/EstudianteDashboardController.php
// Método modulo() — reemplazar la clasificación manual por el servicio

use App\Services\GrafoService;

public function modulo(Modulo $modulo, GrafoService $grafoService)
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

    // La clasificación ahora viene del servicio
    $clasificacion = $grafoService->clasificar($ecosistema, $codigosConquistados);

    return view('estudiante.modulo', compact(
        'modulo', 'ecosistema', 'perfil', 'clasificacion', 'codigosConquistados'
    ));
}
```

Actualiza la vista `estudiante/modulo.blade.php` para consumir la variable `$clasificacion` en lugar de `$scs`:

```html
{{-- resources/views/estudiante/modulo.blade.php  (fragmento de agrupación) --}}

@foreach([
    'conquistadas' => ['Conquistadas',  'bg-green-50 border-green-200'],
    'zdp'          => ['Disponibles',   'bg-eac-50 border-eac-200'],
    'bloqueadas'   => ['Bloqueadas',    'bg-gray-50 border-gray-200'],
] as $grupo => [$label, $estilos])

    @php $items = $clasificacion[$grupo]; @endphp

    @if($items->isNotEmpty())
        <section class="mb-8">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500 mb-3">
                {{ $label }} ({{ $items->count() }})
            </h2>
            <div class="space-y-2">
                @foreach($items as $sc)
                    <div class="border {{ $estilos }} rounded-lg p-4 flex items-start gap-3">
                        <span class="font-mono text-xs px-2 py-0.5 rounded bg-white
                                     border border-gray-200 text-gray-600 flex-shrink-0 mt-0.5">
                            {{ $sc->codigo }}
                        </span>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-800">{{ $sc->titulo }}</p>
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
                            @if($grupo === 'bloqueadas')
                                <p class="text-xs text-gray-400 mt-1">
                                    Requiere: {{ $sc->prerequisitos->pluck('codigo')->join(', ') }}
                                </p>
                            @endif
                        </div>
                        @if($grupo === 'conquistadas')
                            @php
                                $pivot = $perfil->situacionesConquistadas
                                    ->firstWhere('codigo', $sc->codigo)?->pivot;
                            @endphp
                            <x-sc-badge
                                :codigo="$sc->codigo"
                                :gradiente="$pivot?->gradiente_autonomia"
                            />
                        @endif
                    </div>
                @endforeach
            </div>
        </section>
    @endif
@endforeach
```

---

**Unidad anterior ←** [Unidad 3: API REST EAC](./03_api_rest_eac.md)

**Siguiente capítulo →** [Comprobaciones](./04_05_comprobaciones.md)

**Siguiente unidad →** [Unidad 5: Evaluación y seguimiento](./05_evaluacion_seguimiento.md)
