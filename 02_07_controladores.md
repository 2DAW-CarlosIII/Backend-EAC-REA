## 2.7. Controladores

```bash
php artisan make:controller Publico/PortadaController       --invokable
php artisan make:controller Publico/ModuloController
php artisan make:controller Publico/EcosistemaController    --invokable
php artisan make:controller Estudiante/DashboardController  --invokable
php artisan make:controller Estudiante/PerfilController     --invokable
php artisan make:controller Docente/DashboardController     --invokable
php artisan make:controller Docente/EcosistemaController    --invokable
php artisan make:controller Docente/ProgresoController      --invokable
```

> El modificador `--invokable` crea controladores con un único método `__invoke()`, ideal para acciones simples como mostrar la portada o el dashboard.

### 2.7.1. Controladores públicos

```php
// app/Http/Controllers/Publico/PortadaController.php
class PortadaController extends Controller
{
    public function __invoke(): View
    {
        $modulos = Modulo::with([
                'cicloFormativo.familiaProfesional',
                'ecosistemasLaborales' => fn($q) => $q->where('activo', true),
            ])
            ->whereHas('ecosistemasLaborales', fn($q) => $q->where('activo', true))
            ->take(6)->get();

        return view('publico.portada', compact('modulos'));
    }
}
```

```php
// app/Http/Controllers/Publico/ModuloController.php
class ModuloController extends Controller
{
    public function index(Request $request): View
    {
        $familias = FamiliaProfesional::orderBy('nombre')->get();

        $modulos = Modulo::with([
                'cicloFormativo.familiaProfesional',
                'ecosistemasLaborales' => fn($q) => $q->where('activo', true),
            ])
            ->whereHas('ecosistemasLaborales', fn($q) => $q->where('activo', true))
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
        // resultadosAprendizaje → FK modulo_id (no ecosistema_laboral_id)
        $modulo->load([
            'cicloFormativo.familiaProfesional',
            'resultadosAprendizaje.criteriosEvaluacion',
            'ecosistemasLaborales' => fn($q) => $q->where('activo', true)
                                                   ->withCount('situacionesCompetencia'),
        ]);

        return view('publico.modulos.show', compact('modulo'));
    }
}
```

```php
// app/Http/Controllers/Publico/EcosistemaController.php
class EcosistemaController extends Controller
{
    public function __invoke(EcosistemaLaboral $ecosistema): View
    {
        $ecosistema->load([
            'modulo.cicloFormativo.familiaProfesional',
            'situacionesCompetencia.prerequisitos',
        ]);

        return view('publico.ecosistemas.show', compact('ecosistema'));
    }
}
```

### 2.7.2. Controladores del estudiante

```php
// app/Http/Controllers/Estudiante/DashboardController.php
class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $perfiles = auth()->user()
            ->perfilesHabilitacion()
            ->with([
                'ecosistemaLaboral.modulo',
                'ecosistemaLaboral.situacionesCompetencia',
                'situacionesConquistadas',
            ])
            ->get();

        return view('estudiante.dashboard', compact('perfiles'));
    }
}
```

```php
// app/Http/Controllers/Estudiante/PerfilController.php
class PerfilController extends Controller
{
    public function __invoke(PerfilHabilitacion $perfil): View
    {
        abort_unless($perfil->estudiante_id === auth()->id(), 403);

        $perfil->load([
            'ecosistemaLaboral.modulo',
            'ecosistemaLaboral.situacionesCompetencia.prerequisitos',
            'situacionesConquistadas',
        ]);

        return view('estudiante.perfil.show', compact('perfil'));
    }
}
```

### 2.7.3. Controladores del docente

```php
// app/Http/Controllers/Docente/DashboardController.php
class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $docenteRoleId = Role::where('name', 'docente')->value('id');

        $ecosistemas = auth()->user()
            ->userRoles()
            ->where('role_id', $docenteRoleId)
            ->with([
                'ecosistemaLaboral.modulo',
                'ecosistemaLaboral.situacionesCompetencia',
                'ecosistemaLaboral.perfilesHabilitacion',
            ])
            ->get()
            ->pluck('ecosistemaLaboral')
            ->filter();

        return view('docente.dashboard', compact('ecosistemas'));
    }
}
```

```php
// app/Http/Controllers/Docente/EcosistemaController.php
class EcosistemaController extends Controller
{
    public function __invoke(EcosistemaLaboral $ecosistema): View
    {
        // Verificar que el usuario autenticado tiene rol docente en este ecosistema
        $esDocente = auth()->user()
            ->userRoles()
            ->where('ecosistema_laboral_id', $ecosistema->id)
            ->whereHas('role', fn($q) => $q->where('name', 'docente'))
            ->exists();

        abort_unless($esDocente, 403, 'No tienes rol de docente en este ecosistema.');

        $ecosistema->load([
            'modulo.cicloFormativo.familiaProfesional',
            'modulo.resultadosAprendizaje.criteriosEvaluacion',
            'situacionesCompetencia.prerequisitos',
            'situacionesCompetencia.dependientes',
            'situacionesCompetencia.criteriosEvaluacion',
            'situacionesCompetencia.nodosRequisito',
        ]);

        // Estadísticas rápidas de progreso del grupo
        $totalEstudiantes = $ecosistema->perfilesHabilitacion()->count();

        // Para cada SC: cuántos estudiantes la han conquistado
        $conquistasPorSc = $ecosistema->situacionesCompetencia
            ->mapWithKeys(function ($sc) {
                return [
                    $sc->codigo => PerfilSituacion::whereHas(
                        'perfilHabilitacion',
                        fn($q) => $q->where('ecosistema_laboral_id', $sc->ecosistema_laboral_id)
                    )
                    ->where('situacion_competencia_id', $sc->id)
                    ->count(),
                ];
            });

        return view('docente.ecosistemas.show', compact(
            'ecosistema',
            'totalEstudiantes',
            'conquistasPorSc'
        ));
    }
}
```

```php
// app/Http/Controllers/Docente/ProgresoController.php
class ProgresoController extends Controller
{
    public function __invoke(EcosistemaLaboral $ecosistema): View
    {
        $esDocente = auth()->user()
            ->userRoles()
            ->where('ecosistema_laboral_id', $ecosistema->id)
            ->whereHas('role', fn($q) => $q->where('name', 'docente'))
            ->exists();

        abort_unless($esDocente, 403);

        $ecosistema->load(['situacionesCompetencia', 'modulo']);

        $perfiles = $ecosistema->perfilesHabilitacion()
            ->with(['estudiante', 'situacionesConquistadas'])
            ->get();

        return view('docente.progreso.show', compact('ecosistema', 'perfiles'));
    }
}
```

---

**Unidad anterior ←** [Unidad 1: Modelado de datos EAC en Laravel](./01_modelado_datos_eac.md)

  **Siguiente capítulo →** [Rutas](./02_08_rutas.md)

**Siguiente unidad →** [Unidad 3: API REST EAC](./03_api_rest_eac.md)
