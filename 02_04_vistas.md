## 2.4. Vistas públicas: catálogo del marketplace

Las vistas públicas son la cara del backend-EAC en el VFDS. No requieren autenticación y su contenido puede ser indexado por el catálogo federado.

### 2.4.1. Portada

```blade
{{-- resources/views/publico/portada.blade.php --}}
@extends('layouts.eac')
@section('title', 'Inicio')

@section('content')
<div class="bg-vfds-primary">
    <div class="max-w-7xl mx-auto px-4 py-20 text-center">
        <h1 class="text-4xl font-bold text-white leading-tight">
            Vocational Federated<br>
            <span class="text-vfds-accent">Data Space</span>
        </h1>
        <p class="mt-4 text-lg text-gray-300 max-w-2xl mx-auto">
            Espacio competencial para la Formación Profesional.
            Consulta los módulos disponibles y los ecosistemas de aprendizaje activos.
        </p>
        <div class="mt-8 flex justify-center gap-4">
            <a href="{{ route('publico.modulos.index') }}" class="btn-primary text-base px-6 py-3">
                Ver catálogo de módulos
            </a>
        </div>
    </div>
</div>

<div class="max-w-7xl mx-auto px-4 py-16">
    <h2 class="text-2xl font-bold text-vfds-primary mb-8">Módulos con ecosistema activo</h2>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
        @foreach($modulos as $modulo)
            <a href="{{ route('publico.modulos.show', $modulo) }}"
               class="card hover:shadow-md transition-shadow group">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-xs font-mono text-vfds-secondary font-semibold">
                        {{ $modulo->codigo }}
                    </span>
                    <span class="text-xs text-gray-400">
                        {{ ucfirst($modulo->cicloFormativo->grado) }}
                    </span>
                </div>
                <h3 class="text-base font-semibold text-gray-800 group-hover:text-vfds-primary
                           transition-colors leading-snug">
                    {{ $modulo->nombre }}
                </h3>
                <p class="text-xs text-gray-500 mt-1">
                    {{ $modulo->cicloFormativo->familiaProfesional->nombre }}
                    · {{ $modulo->cicloFormativo->nombre }}
                </p>
                <div class="mt-4 flex items-center justify-between">
                    <span class="text-xs text-gray-400">
                        {{ $modulo->ecosistemasLaborales->count() }} ecosistema(s) activo(s)
                    </span>
                    <span class="text-vfds-accent text-sm">→</span>
                </div>
            </a>
        @endforeach
    </div>
</div>
@endsection
```

### 2.4.2. Catálogo de módulos (`index`)

```blade
{{-- resources/views/publico/modulos/index.blade.php --}}
@extends('layouts.eac')
@section('title', 'Catálogo de módulos')

@section('content')
<div class="max-w-7xl mx-auto px-4 py-10">

    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-2xl font-bold text-vfds-primary">Catálogo de módulos</h1>
            <p class="text-gray-500 text-sm mt-1">
                Módulos formativos con ecosistema de aprendizaje competencial disponible.
            </p>
        </div>
        <form method="GET" action="{{ route('publico.modulos.index') }}"
              class="flex items-center gap-2">
            <select name="familia" onchange="this.form.submit()"
                    class="text-sm border border-gray-300 rounded-lg px-3 py-2
                           focus:ring-vfds-accent focus:border-vfds-accent">
                <option value="">Todas las familias</option>
                @foreach($familias as $familia)
                    <option value="{{ $familia->id }}"
                            {{ request('familia') == $familia->id ? 'selected' : '' }}>
                        {{ $familia->nombre }}
                    </option>
                @endforeach
            </select>
        </form>
    </div>

    <div class="space-y-4">
        @forelse($modulos as $modulo)
            <div class="card flex items-center justify-between">
                <div class="flex-1">
                    <div class="flex items-center gap-3">
                        <span class="font-mono text-sm text-vfds-secondary font-semibold">
                            {{ $modulo->codigo }}
                        </span>
                        <span class="text-base font-semibold text-gray-800">
                            {{ $modulo->nombre }}
                        </span>
                    </div>
                    <p class="text-sm text-gray-500 mt-1">
                        {{ $modulo->cicloFormativo->familiaProfesional->nombre }}
                        · {{ $modulo->cicloFormativo->nombre }}
                        · {{ $modulo->horas_totales }}h
                    </p>
                    <div class="flex flex-wrap gap-2 mt-2">
                        @foreach($modulo->ecosistemasLaborales->where('activo', true) as $eco)
                            <a href="{{ route('publico.ecosistemas.show', $eco) }}"
                               class="text-xs px-2 py-1 rounded-full bg-vfds-surface
                                      text-vfds-primary hover:bg-vfds-secondary hover:text-white
                                      transition-colors border border-vfds-primary/20">
                                {{ $eco->codigo }}
                            </a>
                        @endforeach
                    </div>
                </div>
                <a href="{{ route('publico.modulos.show', $modulo) }}"
                   class="btn-secondary ml-4 shrink-0">
                    Ver ficha
                </a>
            </div>
        @empty
            <p class="text-center text-gray-400 py-12">
                No hay módulos disponibles para los criterios seleccionados.
            </p>
        @endforelse
    </div>

    <div class="mt-6">{{ $modulos->withQueryString()->links() }}</div>
</div>
@endsection
```

### 2.4.3. Ficha del módulo (`show`)

```blade
{{-- resources/views/publico/modulos/show.blade.php --}}
@extends('layouts.eac')
@section('title', $modulo->nombre)

@section('content')
<div class="max-w-5xl mx-auto px-4 py-10">

    <div class="mb-6">
        <a href="{{ route('publico.modulos.index') }}"
           class="text-sm text-gray-400 hover:text-vfds-primary">← Catálogo</a>
    </div>

    <div class="card mb-8">
        <div>
            <span class="font-mono text-vfds-secondary text-sm font-semibold">
                {{ $modulo->codigo }}
            </span>
            <h1 class="text-2xl font-bold text-vfds-primary mt-1">{{ $modulo->nombre }}</h1>
            <p class="text-gray-500 text-sm mt-1">
                {{ $modulo->cicloFormativo->familiaProfesional->nombre }}
                · {{ $modulo->cicloFormativo->nombre }}
                ({{ ucfirst($modulo->cicloFormativo->grado) }})
                · {{ $modulo->horas_totales }} horas
            </p>
            @if($modulo->descripcion)
                <p class="text-gray-600 mt-3 text-sm leading-relaxed">
                    {{ $modulo->descripcion }}
                </p>
            @endif
        </div>
    </div>

    {{-- Resultados de Aprendizaje (del módulo, no del ecosistema) --}}
    <section class="mb-8">
        <h2 class="text-lg font-bold text-vfds-primary mb-4">Resultados de Aprendizaje</h2>
        <div class="space-y-4">
            @foreach($modulo->resultadosAprendizaje->sortBy('orden') as $ra)
                <div class="card">
                    <div class="flex items-center gap-3 mb-2">
                        <span class="font-mono text-sm font-bold text-vfds-accent">
                            {{ $ra->codigo }}
                        </span>
                        <span class="text-sm font-semibold text-gray-700">
                            {{ $ra->descripcion }}
                        </span>
                        <span class="ml-auto text-xs text-gray-400">
                            {{ $ra->peso_porcentaje }}%
                        </span>
                    </div>
                    <ul class="mt-2 space-y-1 pl-4 border-l-2 border-vfds-surface">
                        @foreach($ra->criteriosEvaluacion->sortBy('orden') as $ce)
                            <li class="text-xs text-gray-600">
                                <span class="font-mono text-vfds-secondary font-semibold">
                                    {{ $ce->codigo }}
                                </span>
                                · {{ $ce->descripcion }}
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
    </section>

    {{-- Ecosistemas activos del módulo --}}
    <section>
        <h2 class="text-lg font-bold text-vfds-primary mb-4">
            Ecosistemas de aprendizaje disponibles
        </h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            @foreach($modulo->ecosistemasLaborales->where('activo', true) as $eco)
                <a href="{{ route('publico.ecosistemas.show', $eco) }}"
                   class="card hover:shadow-md transition-shadow group">
                    <div class="flex items-center justify-between mb-2">
                        <span class="font-mono text-sm text-vfds-secondary font-semibold">
                            {{ $eco->codigo }}
                        </span>
                        <span class="text-xs text-green-600 font-semibold">Activo</span>
                    </div>
                    <p class="text-sm font-semibold text-gray-800 group-hover:text-vfds-primary">
                        {{ $eco->nombre }}
                    </p>
                    <p class="text-xs text-gray-400 mt-2">
                        {{ $eco->situacionesCompetencia->count() }} situaciones de competencia
                    </p>
                </a>
            @endforeach
        </div>
    </section>

</div>
@endsection
```

### 2.4.4. Ficha del ecosistema (`show` público)

```blade
{{-- resources/views/publico/ecosistemas/show.blade.php --}}
@extends('layouts.eac')
@section('title', $ecosistema->nombre)

@section('content')
<div class="max-w-5xl mx-auto px-4 py-10">

    <div class="mb-6">
        <a href="{{ route('publico.modulos.show', $ecosistema->modulo) }}"
           class="text-sm text-gray-400 hover:text-vfds-primary">
            ← {{ $ecosistema->modulo->nombre }}
        </a>
    </div>

    <div class="card mb-8">
        <span class="font-mono text-vfds-secondary text-sm font-semibold">
            {{ $ecosistema->codigo }}
        </span>
        <h1 class="text-2xl font-bold text-vfds-primary mt-1">{{ $ecosistema->nombre }}</h1>
        @if($ecosistema->descripcion)
            <p class="text-gray-600 mt-3 text-sm leading-relaxed">{{ $ecosistema->descripcion }}</p>
        @endif
    </div>

    <section>
        <h2 class="text-lg font-bold text-vfds-primary mb-4">
            Situaciones de Competencia
            <span class="text-base font-normal text-gray-400">
                ({{ $ecosistema->situacionesCompetencia->count() }} en total)
            </span>
        </h2>
        <div class="space-y-3">
            @foreach($ecosistema->situacionesCompetencia->sortBy('codigo') as $sc)
                <div class="card">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex-1">
                            <div class="flex items-center gap-3">
                                <span class="font-mono text-sm font-bold text-vfds-accent">
                                    {{ $sc->codigo }}
                                </span>
                                <span class="text-sm font-semibold text-gray-800">
                                    {{ $sc->titulo }}
                                </span>
                            </div>
                            <p class="text-xs text-gray-500 mt-1 leading-relaxed">
                                {{ $sc->descripcion }}
                            </p>
                            @if($sc->prerequisitos->count() > 0)
                                <p class="text-xs text-gray-400 mt-2">
                                    Requiere:
                                    @foreach($sc->prerequisitos as $pre)
                                        <span class="font-mono text-vfds-secondary">
                                            {{ $pre->codigo }}
                                        </span>{{ !$loop->last ? ',' : '' }}
                                    @endforeach
                                </p>
                            @endif
                        </div>
                        <div class="shrink-0 text-right">
                            <span class="text-xs text-gray-400 block">Umbral</span>
                            <span class="text-sm font-bold text-vfds-primary">
                                {{ $sc->umbral_maestria }}%
                            </span>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </section>

</div>
@endsection
```

---

## 2.5. Panel del estudiante

### 2.5.1. Dashboard

```blade
{{-- resources/views/estudiante/dashboard.blade.php --}}
@extends('layouts.estudiante')
@section('title', 'Mi espacio')

@section('panel')
<div class="space-y-8">

    <div>
        <h1 class="text-xl font-bold text-vfds-primary">
            Bienvenido/a, {{ auth()->user()->name }}
        </h1>
        <p class="text-sm text-gray-500 mt-1">Resumen de tu progreso competencial</p>
    </div>

    @forelse($perfiles as $perfil)
        <div class="card">
            <div class="flex items-start justify-between mb-4">
                <div>
                    <span class="text-xs font-mono text-vfds-secondary font-semibold">
                        {{ $perfil->ecosistemaLaboral->modulo->codigo }}
                    </span>
                    <h2 class="text-base font-bold text-vfds-primary mt-0.5">
                        {{ $perfil->ecosistemaLaboral->nombre }}
                    </h2>
                    <p class="text-xs text-gray-400">
                        {{ $perfil->ecosistemaLaboral->modulo->nombre }}
                    </p>
                </div>
                <div class="text-right">
                    <span class="text-2xl font-bold text-vfds-primary">
                        {{ number_format($perfil->calificacion_actual, 1) }}
                    </span>
                    <span class="text-xs text-gray-400 block">calificación actual</span>
                </div>
            </div>

            @php
                $total        = $perfil->ecosistemaLaboral->situacionesCompetencia->count();
                $conquistadas = $perfil->situacionesConquistadas->count();
                $pct          = $total > 0 ? round($conquistadas / $total * 100) : 0;
            @endphp
            <div class="flex items-center gap-3">
                <div class="flex-1 bg-gray-100 rounded-full h-2">
                    <div class="bg-vfds-accent h-2 rounded-full transition-all duration-500"
                         style="width: {{ $pct }}%"></div>
                </div>
                <span class="text-xs text-gray-500 whitespace-nowrap">
                    {{ $conquistadas }}/{{ $total }} SCs ({{ $pct }}%)
                </span>
            </div>

            @if($perfil->situacionesConquistadas->count() > 0)
                <div class="mt-4 flex flex-wrap gap-2">
                    @foreach($perfil->situacionesConquistadas->sortBy('codigo') as $sc)
                        <span class="font-mono text-xs px-2 py-1 rounded-full
                                     bg-green-50 text-green-700 border border-green-200
                                     flex items-center gap-1">
                            {{ $sc->codigo }}
                            <x-gradiente-badge :gradiente="$sc->pivot->gradiente_autonomia" />
                        </span>
                    @endforeach
                </div>
            @endif

            <div class="mt-4">
                <a href="{{ route('estudiante.perfil.show', $perfil->id) }}"
                   class="btn-secondary text-xs">
                    Ver detalle →
                </a>
            </div>
        </div>
    @empty
        <div class="card text-center py-12">
            <p class="text-gray-400">Todavía no tienes módulos matriculados.</p>
            <a href="{{ route('publico.modulos.index') }}" class="btn-primary mt-4 inline-flex">
                Explorar catálogo
            </a>
        </div>
    @endforelse

</div>
@endsection
```

### 2.5.2. Vista de perfil de habilitación

```blade
{{-- resources/views/estudiante/perfil/show.blade.php --}}
@extends('layouts.estudiante')
@section('title', $perfil->ecosistemaLaboral->nombre)

@section('panel')
<div class="space-y-6">

    <div>
        <a href="{{ route('estudiante.dashboard') }}"
           class="text-sm text-gray-400 hover:text-vfds-primary">← Panel</a>
        <h1 class="text-xl font-bold text-vfds-primary mt-2">
            {{ $perfil->ecosistemaLaboral->nombre }}
        </h1>
        <p class="text-xs text-gray-400 mt-0.5">
            Módulo: {{ $perfil->ecosistemaLaboral->modulo->codigo }}
            · {{ $perfil->ecosistemaLaboral->modulo->nombre }}
        </p>
    </div>

    @php $conquistadasIds = $perfil->situacionesConquistadas->pluck('id')->toArray(); @endphp

    <section>
        <h2 class="text-base font-bold text-vfds-primary mb-3">Situaciones de Competencia</h2>
        <div class="space-y-2">
            @foreach($perfil->ecosistemaLaboral->situacionesCompetencia->sortBy('codigo') as $sc)
                @php $estaConquistada = in_array($sc->id, $conquistadasIds); @endphp
                <div class="card flex items-start justify-between gap-4
                            {{ $estaConquistada ? 'border-l-4 border-l-green-400' : '' }}">
                    <div class="flex-1">
                        <div class="flex items-center gap-2">
                            <span class="font-mono text-sm font-bold text-vfds-accent">
                                {{ $sc->codigo }}
                            </span>
                            <span class="text-sm font-semibold text-gray-800">
                                {{ $sc->titulo }}
                            </span>
                        </div>
                        @if($estaConquistada)
                            @php
                                $pivot = $perfil->situacionesConquistadas
                                               ->firstWhere('id', $sc->id)->pivot;
                            @endphp
                            <div class="mt-2 flex items-center gap-3 text-xs text-gray-500">
                                <x-gradiente-badge :gradiente="$pivot->gradiente_autonomia" />
                                <span>
                                    {{ \Carbon\Carbon::parse($pivot->fecha_conquista)->format('d/m/Y') }}
                                </span>
                                <span>Intentos: {{ $pivot->intentos }}</span>
                            </div>
                        @else
                            @if($sc->prerequisitos->count() > 0)
                                <p class="text-xs text-gray-400 mt-1">
                                    Pendiente de:
                                    @foreach($sc->prerequisitos as $pre)
                                        <span class="font-mono
                                            {{ in_array($pre->id, $conquistadasIds) ? 'text-green-600 line-through' : 'text-red-400' }}">
                                            {{ $pre->codigo }}
                                        </span>{{ !$loop->last ? ', ' : '' }}
                                    @endforeach
                                </p>
                            @endif
                        @endif
                    </div>
                    <span class="text-lg shrink-0">{{ $estaConquistada ? '✅' : '⬜' }}</span>
                </div>
            @endforeach
        </div>
    </section>

</div>
@endsection
```

---

## 2.6. Panel del docente

### 2.6.1. Dashboard

```blade
{{-- resources/views/docente/dashboard.blade.php --}}
@extends('layouts.docente')
@section('title', 'Mi docencia')

@section('panel')
<div class="space-y-6">

    <div>
        <h1 class="text-xl font-bold text-vfds-primary">
            Hola, {{ auth()->user()->name }}
        </h1>
        <p class="text-sm text-gray-500 mt-1">Ecosistemas que gestionas</p>
    </div>

    @forelse($ecosistemas as $ecosistema)
        <div class="card">
            <div class="flex items-start justify-between mb-3">
                <div>
                    <span class="font-mono text-xs text-vfds-secondary font-semibold">
                        {{ $ecosistema->codigo }}
                    </span>
                    <h2 class="text-base font-bold text-vfds-primary mt-0.5">
                        {{ $ecosistema->nombre }}
                    </h2>
                    <p class="text-xs text-gray-400">
                        {{ $ecosistema->modulo->codigo }} · {{ $ecosistema->modulo->nombre }}
                    </p>
                </div>
            </div>

            @php
                $totalScs         = $ecosistema->situacionesCompetencia->count();
                $totalEstudiantes = $ecosistema->perfilesHabilitacion->count();
            @endphp

            <div class="flex gap-6 text-sm text-gray-600 mt-2">
                <span><strong class="text-vfds-primary">{{ $totalScs }}</strong> SCs definidas</span>
                <span><strong class="text-vfds-primary">{{ $totalEstudiantes }}</strong> estudiantes</span>
            </div>

            <div class="mt-4 flex gap-3">
                <a href="{{ route('docente.ecosistemas.show', $ecosistema) }}"
                   class="btn-primary text-xs">
                    Gestionar ecosistema
                </a>
                <a href="{{ route('docente.progreso.show', $ecosistema) }}"
                   class="btn-secondary text-xs">
                    Ver progreso del grupo
                </a>
            </div>
        </div>
    @empty
        <div class="card text-center py-12">
            <p class="text-gray-400">No tienes ecosistemas asignados.</p>
        </div>
    @endforelse

</div>
@endsection
```

### 2.6.2. Vista de gestión del ecosistema

```blade
{{-- resources/views/docente/ecosistemas/show.blade.php --}}
@extends('layouts.docente')

@section('title', 'Ecosistema · ' . $ecosistema->modulo->nombre)

@section('content')

    {{-- Breadcrumb --}}
    <nav class="text-sm text-gray-500 mb-6">
        <a href="{{ route('docente.dashboard') }}" class="hover:text-gray-700">Panel docente</a>
        <span class="mx-2">›</span>
        <span class="text-gray-900">{{ $ecosistema->modulo->nombre }}</span>
    </nav>

    {{-- Cabecera --}}
    <div class="bg-eac-900 text-white rounded-xl px-8 py-6 mb-8">
        <div class="flex items-start justify-between gap-4 flex-wrap">
            <div>
                <p class="text-xs font-mono text-eac-50 opacity-70 mb-1">{{ $ecosistema->codigo }}</p>
                <h1 class="text-2xl font-bold">{{ $ecosistema->nombre }}</h1>
                <p class="text-gray-300 text-sm mt-1">
                    {{ $ecosistema->modulo->cicloFormativo->familiaProfesional->nombre }}
                    · {{ $ecosistema->modulo->cicloFormativo->nombre }}
                    · {{ $ecosistema->modulo->codigo }}
                </p>
            </div>
            <div class="flex gap-6 text-center">
                <div>
                    <p class="text-3xl font-bold">{{ $ecosistema->situacionesCompetencia->count() }}</p>
                    <p class="text-xs text-gray-400">SCs</p>
                </div>
                <div>
                    <p class="text-3xl font-bold">{{ $totalEstudiantes }}</p>
                    <p class="text-xs text-gray-400">Estudiantes</p>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

        {{-- Columna izquierda: Grafo de SCs con cobertura del grupo --}}
        <div class="lg:col-span-2 space-y-6">

            <section>
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold text-gray-900">Situaciones de Competencia</h2>
                    <a href="{{ route('docente.progreso.show', $ecosistema) }}"
                       class="text-sm text-eac-500 hover:text-eac-700 underline">
                        Ver progreso del grupo →
                    </a>
                </div>

                <div class="space-y-3">
                    @foreach($ecosistema->situacionesCompetencia->sortBy('nivel_complejidad') as $sc)
                        @php
                            $conquistadas = $conquistasPorSc[$sc->codigo] ?? 0;
                            $porcentaje   = $totalEstudiantes > 0
                                ? round(($conquistadas / $totalEstudiantes) * 100)
                                : 0;
                        @endphp

                        <div class="border border-gray-200 rounded-xl overflow-hidden">

                            {{-- Cabecera de la SC --}}
                            <div class="flex items-center gap-3 px-4 py-3 bg-gray-50">
                                <span class="font-mono text-xs bg-eac-900 text-white px-2 py-0.5 rounded flex-shrink-0">
                                    {{ $sc->codigo }}
                                </span>
                                <span class="text-sm font-medium text-gray-800 flex-1">
                                    {{ $sc->titulo }}
                                </span>
                                {{-- Nivel de complejidad --}}
                                <div class="flex gap-0.5 flex-shrink-0">
                                    @for($i = 1; $i <= 5; $i++)
                                        <span class="w-1.5 h-1.5 rounded-full {{ $i <= $sc->nivel_complejidad ? 'bg-eac-500' : 'bg-gray-200' }}"></span>
                                    @endfor
                                </div>
                            </div>

                            <div class="px-4 py-3 space-y-3">

                                {{-- Barra de conquista del grupo --}}
                                <div>
                                    <div class="flex justify-between text-xs text-gray-500 mb-1">
                                        <span>Conquistada por el grupo</span>
                                        <span>{{ $conquistadas }} / {{ $totalEstudiantes }} ({{ $porcentaje }}%)</span>
                                    </div>
                                    <div class="w-full bg-gray-100 rounded-full h-1.5">
                                        <div class="bg-eac-500 h-1.5 rounded-full transition-all"
                                             style="width: {{ $porcentaje }}%"></div>
                                    </div>
                                </div>

                                {{-- Prerequisitos --}}
                                @if($sc->prerequisitos->isNotEmpty())
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="text-xs text-gray-400">Requiere:</span>
                                        @foreach($sc->prerequisitos as $pre)
                                            <span class="font-mono text-xs bg-yellow-50 border border-yellow-200
                                                         text-yellow-700 px-2 py-0.5 rounded">
                                                {{ $pre->codigo }}
                                            </span>
                                        @endforeach
                                    </div>
                                @endif

                                {{-- Criterios de Evaluación cubiertos --}}
                                @if($sc->criteriosEvaluacion->isNotEmpty())
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="text-xs text-gray-400">CE cubiertos:</span>
                                        @foreach($sc->criteriosEvaluacion as $ce)
                                            <span class="font-mono text-xs bg-blue-50 border border-blue-100
                                                         text-blue-600 px-2 py-0.5 rounded"
                                                  title="{{ $ce->descripcion }}">
                                                {{ $ce->codigo }}
                                                <span class="opacity-60">({{ $ce->pivot->peso_en_sc }}%)</span>
                                            </span>
                                        @endforeach
                                    </div>
                                @endif

                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        </div>

        {{-- Columna derecha: trazabilidad RA → CE --}}
        <div class="space-y-4">
            <h2 class="text-lg font-semibold text-gray-900">Trazabilidad curricular</h2>

            @foreach($ecosistema->modulo->resultadosAprendizaje as $ra)
                <div class="border border-gray-200 rounded-xl overflow-hidden">
                    <div class="bg-gray-50 px-4 py-3 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="font-mono text-xs bg-eac-900 text-white px-2 py-0.5 rounded">
                                {{ $ra->codigo }}
                            </span>
                            <span class="text-xs font-medium text-gray-700 line-clamp-1"
                                  title="{{ $ra->descripcion }}">
                                {{ Str::limit($ra->descripcion, 40) }}
                            </span>
                        </div>
                        <span class="text-xs text-gray-400 flex-shrink-0 ml-2">{{ $ra->peso_porcentaje }}%</span>
                    </div>
                    <ul class="divide-y divide-gray-100">
                        @foreach($ra->criteriosEvaluacion as $ce)
                            <li class="px-4 py-2 flex items-start gap-2">
                                <span class="font-mono text-xs text-gray-400 flex-shrink-0 mt-0.5">
                                    {{ $ce->codigo }}
                                </span>
                                <span class="text-xs text-gray-600">{{ $ce->descripcion }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>

    </div>

@endsection
```

### 2.6.3. Progreso del grupo

```blade
{{-- resources/views/docente/progreso/show.blade.php --}}
@extends('layouts.docente')
@section('title', 'Progreso · ' . $ecosistema->nombre)

@section('panel')
<div class="space-y-6">

    <div>
        <a href="{{ route('docente.dashboard') }}"
           class="text-sm text-gray-400 hover:text-vfds-primary">← Mi docencia</a>
        <h1 class="text-xl font-bold text-vfds-primary mt-2">Progreso del grupo</h1>
        <p class="text-sm text-gray-500">
            {{ $ecosistema->nombre }} · <span class="font-mono">{{ $ecosistema->codigo }}</span>
        </p>
    </div>

    <div class="card overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-100">
                    <th class="text-left py-3 pr-4 text-gray-500 font-medium">Estudiante</th>
                    @foreach($ecosistema->situacionesCompetencia->sortBy('codigo') as $sc)
                        <th class="py-3 px-2 text-center font-mono text-xs text-vfds-secondary">
                            {{ $sc->codigo }}
                        </th>
                    @endforeach
                    <th class="py-3 pl-4 text-right text-gray-500 font-medium">Calif.</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @foreach($perfiles as $perfil)
                    @php
                        $conquistadasIds = $perfil->situacionesConquistadas->pluck('id')->toArray();
                    @endphp
                    <tr class="hover:bg-vfds-surface/50">
                        <td class="py-3 pr-4 font-medium text-gray-700">
                            {{ $perfil->estudiante->name }}
                        </td>
                        @foreach($ecosistema->situacionesCompetencia->sortBy('codigo') as $sc)
                            <td class="py-3 px-2 text-center">
                                @if(in_array($sc->id, $conquistadasIds))
                                    @php
                                        $g = $perfil->situacionesConquistadas
                                                    ->firstWhere('id', $sc->id)
                                                    ->pivot->gradiente_autonomia;
                                    @endphp
                                    <x-gradiente-badge :gradiente="$g" />
                                @else
                                    <span class="text-gray-200">—</span>
                                @endif
                            </td>
                        @endforeach
                        <td class="py-3 pl-4 text-right font-bold text-vfds-primary">
                            {{ number_format($perfil->calificacion_actual, 1) }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

</div>
@endsection
```

---

**Unidad anterior ←** [Unidad 1: Modelado de datos EAC en Laravel](./01_modelado_datos_eac.md)

  **Siguiente capítulo →** [Controladores en Laravel](./02_07_controladores.md)

**Siguiente unidad →** [Unidad 3: API REST EAC](./03_api_rest_eac.md)
