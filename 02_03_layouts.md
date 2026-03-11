## 2.3. Layout principal

El layout se divide en tres plantillas Blade:

- `layouts/app.blade.php` — base de toda la aplicación
- `layouts/estudiante.blade.php` — extiende `app`, añade sidebar del estudiante
- `layouts/docente.blade.php` — extiende `app`, añade sidebar del docente

### 2.3.1. Estructura de directorios

```
resources/views/
├── layouts/
│   ├── app.blade.php
│   ├── estudiante.blade.php
│   └── docente.blade.php
├── components/
│   ├── navbar.blade.php
│   ├── footer.blade.php
│   └── gradiente-badge.blade.php
├── publico/
│   ├── portada.blade.php
│   ├── modulos/
│   │   ├── index.blade.php
│   │   └── show.blade.php
│   └── ecosistemas/
│       └── show.blade.php
├── estudiante/
│   ├── dashboard.blade.php
│   └── perfil/
│       └── show.blade.php
└── docente/
    ├── dashboard.blade.php
    └── progreso/
        └── show.blade.php
```

### 2.3.2. `layouts/eac.blade.php`

```blade
{{-- resources/views/layouts/eac.blade.php --}}
<!DOCTYPE html>
<html lang="es" class="h-full bg-vfds-surface">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Backend EAC') — VFDS</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="h-full font-sans text-gray-800 antialiased">

    @include('components.navbar')

    @if (session('success'))
        <div class="max-w-7xl mx-auto px-4 mt-4">
            <div class="rounded-lg bg-green-50 border border-green-200 p-4 text-green-800 text-sm">
                {{ session('success') }}
            </div>
        </div>
    @endif
    @if (session('error'))
        <div class="max-w-7xl mx-auto px-4 mt-4">
            <div class="rounded-lg bg-red-50 border border-red-200 p-4 text-red-800 text-sm">
                {{ session('error') }}
            </div>
        </div>
    @endif

    <main class="min-h-screen">
        @yield('content')
    </main>

    @include('components.footer')
    @stack('scripts')
</body>
</html>
```

### 2.3.3. Componente navbar

```blade
{{-- resources/views/components/navbar.blade.php --}}
<nav class="bg-vfds-primary shadow-md">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16 items-center">

            <div class="flex items-center space-x-3">
                <a href="{{ route('publico.portada') }}" class="flex items-center space-x-2">
                    <span class="text-vfds-accent font-bold text-xl tracking-tight">VFDS</span>
                    <span class="text-white text-sm font-light hidden sm:block">Backend EAC</span>
                </a>
            </div>

            <div class="hidden md:flex items-center space-x-6">
                <a href="{{ route('publico.modulos.index') }}"
                   class="text-gray-300 hover:text-white text-sm transition-colors
                          {{ request()->routeIs('publico.modulos*') ? 'text-white font-semibold' : '' }}">
                    Catálogo
                </a>
                @auth
                    @role('estudiante')
                        <a href="{{ route('estudiante.dashboard') }}"
                           class="text-gray-300 hover:text-white text-sm transition-colors
                                  {{ request()->routeIs('estudiante.*') ? 'text-white font-semibold' : '' }}">
                            Mi espacio
                        </a>
                    @endrole
                    @role('docente')
                        <a href="{{ route('docente.dashboard') }}"
                           class="text-gray-300 hover:text-white text-sm transition-colors
                                  {{ request()->routeIs('docente.*') ? 'text-white font-semibold' : '' }}">
                            Mi docencia
                        </a>
                    @endrole
                @endauth
            </div>

            <div class="flex items-center space-x-3">
                @guest
                    <a href="{{ route('login') }}" class="text-gray-300 hover:text-white text-sm">
                        Entrar
                    </a>
                @endguest
                @auth
                    <span class="text-gray-300 text-sm hidden sm:block">
                        {{ auth()->user()->name }}
                    </span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit"
                                class="text-gray-300 hover:text-white text-sm transition-colors">
                            Salir
                        </button>
                    </form>
                @endauth
            </div>

        </div>
    </div>
</nav>
```

### 2.3.4. Componente footer

```blade
{{-- resources/views/components/footer.blade.php --}}
<footer class="bg-white border-t border-gray-200">
    <div class="max-w-7xl mx-auto px-4 py-6 flex flex-col sm:flex-row justify-between items-center text-sm text-gray-600">
        <div class="text-center sm:text-left">
            © {{ now()->year }} VFDS — Backend EAC. Todos los derechos reservados.
        </div>

        <div class="mt-3 sm:mt-0 flex items-center space-x-4">
            <a href="{{ route('publico.portada') }}" class="text-vfds-primary hover:underline">Inicio</a>
            <a href="{{ route('publico.modulos.index') }}" class="text-vfds-primary hover:underline">Catálogo</a>
            <a href="/legal" class="text-vfds-primary hover:underline">Aviso legal</a>
        </div>
    </div>
</footer>
```

### 2.3.5. Layouts de panel

```blade
{{-- resources/views/layouts/estudiante.blade.php --}}
@extends('layouts.eac')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="flex gap-8">

        <aside class="w-64 shrink-0">
            <nav class="card space-y-1">
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">
                    Mi espacio
                </p>
                <a href="{{ route('estudiante.dashboard') }}"
                   class="flex items-center px-3 py-2 rounded-lg text-sm
                          {{ request()->routeIs('estudiante.dashboard') ? 'bg-vfds-surface text-vfds-primary font-semibold' : 'text-gray-600 hover:bg-gray-50' }}">
                    Panel general
                </a>
                <a href="{{ route('estudiante.modulos.index') }}"
                   class="flex items-center px-3 py-2 rounded-lg text-sm
                          {{ request()->routeIs('estudiante.modulos*') ? 'bg-vfds-surface text-vfds-primary font-semibold' : 'text-gray-600 hover:bg-gray-50' }}">
                    Mis módulos
                </a>
            </nav>
        </aside>

        <div class="flex-1 min-w-0">
            @yield('panel')
        </div>
    </div>
</div>
@endsection
```

```blade
{{-- resources/views/layouts/docente.blade.php --}}
@extends('layouts.eac')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="flex gap-8">

        <aside class="w-64 shrink-0">
            <nav class="card space-y-1">
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">
                    Mi docencia
                </p>
                <a href="{{ route('docente.dashboard') }}"
                   class="flex items-center px-3 py-2 rounded-lg text-sm
                          {{ request()->routeIs('docente.dashboard') ? 'bg-vfds-surface text-vfds-primary font-semibold' : 'text-gray-600 hover:bg-gray-50' }}">
                    Panel general
                </a>
            </nav>
        </aside>

        <div class="flex-1 min-w-0">
            @yield('panel')
        </div>
    </div>
</div>
@endsection
```

### 2.3.6. Componente `gradiente-badge`

```blade
{{-- resources/views/components/gradiente-badge.blade.php --}}
@props(['gradiente'])

@php
$clases = match($gradiente) {
    'autonomo'    => 'badge-autonomo',
    'supervisado' => 'badge-supervisado',
    'guiado'      => 'badge-guiado',
    'asistido'    => 'badge-asistido',
    default       => 'badge-asistido',
};
$etiquetas = [
    'autonomo'    => 'Autónomo',
    'supervisado' => 'Supervisado',
    'guiado'      => 'Guiado',
    'asistido'    => 'Asistido',
];
@endphp

<span class="{{ $clases }}">{{ $etiquetas[$gradiente] ?? $gradiente }}</span>
```

---

**Unidad anterior ←** [Unidad 1: Modelado de datos EAC en Laravel](./01_modelado_datos_eac.md)

  **Siguiente capítulo →** [Vistas con Blade](./02_04_vistas.md)

**Siguiente unidad →** [Unidad 3: API REST EAC](./03_api_rest_eac.md)
