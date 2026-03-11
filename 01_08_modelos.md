## 1.8. Modelos Eloquent

Crea todos los modelos de una vez:

```bash
php artisan make:model FamiliaProfesional
php artisan make:model CicloFormativo
php artisan make:model Modulo
php artisan make:model EcosistemaLaboral
php artisan make:model ResultadoAprendizaje
php artisan make:model CriterioEvaluacion
php artisan make:model SituacionCompetencia
php artisan make:model NodoRequisito
php artisan make:model Role
php artisan make:model Matricula
php artisan make:model PerfilHabilitacion
php artisan make:model PerfilSituacion
php artisan make:model HuellaTalento
```

A continuación se muestra cada modelo con sus relaciones.

### 1.8.1. FamiliaProfesional

```php
// app/Models/FamiliaProfesional.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FamiliaProfesional extends Model
{
    protected $fillable = ['nombre', 'codigo', 'descripcion'];
    protected $table = 'familias_profesionales';

    public function ciclosFormativos(): HasMany
    {
        return $this->hasMany(CicloFormativo::class);
    }
}
```

### 1.8.2. CicloFormativo

```php
// app/Models/CicloFormativo.php

class CicloFormativo extends Model
{
    protected $fillable = ['familia_profesional_id', 'nombre', 'codigo', 'grado', 'descripcion'];
    protected $table = 'ciclos_formativos';

    public function familiaProfesional(): BelongsTo
    {
        return $this->belongsTo(FamiliaProfesional::class);
    }

    public function modulos(): HasMany
    {
        return $this->hasMany(Modulo::class);
    }
}
```

### 1.8.3. Modulo

```php
// app/Models/Modulo.php

class Modulo extends Model
{
    protected $fillable = [
        'ciclo_formativo_id', 'nombre', 'codigo', 'horas_totales', 'descripcion',
    ];

    public function cicloFormativo(): BelongsTo
    {
        return $this->belongsTo(CicloFormativo::class);
    }

    public function ecosistemasLaborales(): HasMany
    {
        return $this->hasMany(EcosistemaLaboral::class);
    }

    public function resultadosAprendizaje(): HasMany
    {
        return $this->hasMany(ResultadoAprendizaje::class);
    }
}
```

### 1.8.4. EcosistemaLaboral

```php
// app/Models/EcosistemaLaboral.php

class EcosistemaLaboral extends Model
{
    protected $fillable = [
        'modulo_id', 'nombre', 'codigo', 'descripcion', 'activo',
    ];

    protected $table = 'ecosistemas_laborales';

    protected $casts = ['activo' => 'boolean'];

    public function modulo(): BelongsTo
    {
        return $this->belongsTo(Modulo::class);
    }

    public function situacionesCompetencia(): HasMany
    {
        return $this->hasMany(SituacionCompetencia::class);
    }

    public function matriculas(): HasMany
    {
        return $this->hasMany(Matricula::class);
    }

    public function perfilesHabilitacion(): HasMany
    {
        return $this->hasMany(PerfilHabilitacion::class);
    }
}
```

### 1.8.5. ResultadoAprendizaje

```php
// app/Models/ResultadoAprendizaje.php

class ResultadoAprendizaje extends Model
{
    protected $fillable = [
        'modulo_id', 'codigo', 'descripcion',
    ];
    protected $table = 'resultados_aprendizaje';

    public function modulo(): BelongsTo
    {
        return $this->belongsTo(Modulo::class);
    }

    public function criteriosEvaluacion(): HasMany
    {
        return $this->hasMany(CriterioEvaluacion::class);
    }
}
```

### 1.8.6. CriterioEvaluacion

```php
// app/Models/CriterioEvaluacion.php

class CriterioEvaluacion extends Model
{
    protected $fillable = [
        'resultado_aprendizaje_id', 'codigo', 'descripcion',
    ];
    protected $table = 'criterios_evaluacion';

    public function resultadoAprendizaje(): BelongsTo
    {
        return $this->belongsTo(ResultadoAprendizaje::class);
    }

    // Un CE puede ser cubierto por varias SCs
    public function situacionesCompetencia(): BelongsToMany
    {
        return $this->belongsToMany(
            SituacionCompetencia::class,
            'sc_criterios_evaluacion',
            'criterio_evaluacion_id',
            'situacion_competencia_id'
        )->withPivot('peso_en_sc');
    }
}
```

### 1.8.7. SituacionCompetencia

Este es el modelo más rico del sistema. Incluye las relaciones con el grafo de precedencia (tanto hacia arriba como hacia abajo).

Una vez que hemos creado el modelo `SituacionCompetencia`, modifícalo para incluir todas sus relaciones:

* Relación con `EcosistemaLaboral` (belongsTo)
* Relación con `NodoRequisito` (hasMany)
* Relación de prerequisitos (belongsToMany a sí mismo)
* Relación de dependientes (belongsToMany a sí mismo)
* Relación con `CriterioEvaluacion` (belongsToMany)

Define, además, las siguientes propiedades estáticas:

* `fillable` con los campos editables (sin `id` ni `timestamps`)
* `casts` para convertir `umbral_maestria` a decimal y `activa` a booleano

Puedes ver una posible solución en el apartado [Soluciones](#modelo-situacioncompetencia).

### 1.8.8. NodoRequisito

```php
// app/Models/NodoRequisito.php

class NodoRequisito extends Model
{
    protected $fillable = [
        'situacion_competencia_id', 'tipo', 'descripcion', 'orden',
    ];
    protected $table = 'nodos_requisito';

    public function situacionCompetencia(): BelongsTo
    {
        return $this->belongsTo(SituacionCompetencia::class);
    }
}
```

### 1.8.9. Extensión del modelo User

Añade las relaciones EAC al modelo `User` existente:

```php
// app/Models/User.php  (añadir a los métodos existentes)

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

public function roles(): BelongsToMany
{
    return $this->belongsToMany(Role::class, 'user_roles')
                ->withPivot('ecosistema_laboral_id')
                ->withTimestamps();
}

// Ecosistemas en los que está matriculado (como estudiante)
public function matriculas(): HasMany
{
    return $this->hasMany(Matricula::class, 'estudiante_id');
}

public function ecosistemasMatriculado(): BelongsToMany
{
    return $this->belongsToMany(
        EcosistemaLaboral::class,
        'matriculas',
        'estudiante_id'
    )->withTimestamps();
}

// Perfiles de habilitación del estudiante
public function perfilesHabilitacion(): HasMany
{
    return $this->hasMany(PerfilHabilitacion::class, 'estudiante_id');
}

public function perfilEn(EcosistemaLaboral $ecosistema): ?PerfilHabilitacion
{
    return $this->perfilesHabilitacion()
                ->where('ecosistema_laboral_id', $ecosistema->id)
                ->first();
}
```

Modelo `Role`:

```php
// app/Models/Role.php
class Role extends Model
{
    protected $fillable = ['name', 'description'];
}
```

### 1.8.10. PerfilHabilitacion

```php
// app/Models/PerfilHabilitacion.php

class PerfilHabilitacion extends Model
{
    protected $fillable = [
        'estudiante_id', 'ecosistema_laboral_id', 'calificacion_actual',
    ];
    protected $table = 'perfiles_habilitacion';

    protected $casts = ['calificacion_actual' => 'decimal:2'];

    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'estudiante_id');
    }

    public function ecosistemaLaboral(): BelongsTo
    {
        return $this->belongsTo(EcosistemaLaboral::class);
    }

    // SCs conquistadas por este estudiante en este ecosistema
    public function situacionesConquistadas(): BelongsToMany
    {
        return $this->belongsToMany(
            SituacionCompetencia::class,
            'perfil_situacion',
            'perfil_habilitacion_id',
            'situacion_competencia_id'
        )->withPivot([
            'gradiente_autonomia',
            'puntuacion_conquista',
            'intentos',
            'fecha_conquista',
        ]);
    }

    // Códigos de SCs conquistadas (útil para el motor ZDP)
    public function codigosConquistados(): array
    {
        return $this->situacionesConquistadas()
                    ->pluck('codigo')
                    ->toArray();
    }
}
```

### 1.8.11. PerfilSituacion (tabla pivote)

```php
// app/Models/PerfilSituacion.php
class PerfilSituacion extends Pivot
{
    protected $table = 'perfil_situacion';

    protected $fillable = [
        'perfil_habilitacion_id', 'situacion_competencia_id',
        'gradiente_autonomia', 'puntuacion_conquista', 'intentos', 'fecha_conquista',
    ];

    protected $casts = [
        'gradiente_autonomia' => 'decimal:2',
        'puntuacion_conquista' => 'decimal:2',
        'fecha_conquista' => 'datetime',
    ];
}
```

### 1.8.12. HuellaTalento

```php
// app/Models/HuellaTalento.php

class HuellaTalento extends Model
{
    protected $fillable = [
        'estudiante_id', 'ecosistema_laboral_id',
        'payload', 'ngsi_ld_id', 'generada_en',
    ];

    protected $table = 'huellas_talento';

    protected $casts = [
        'payload'      => 'array',
        'generada_en'  => 'datetime',
    ];

    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'estudiante_id');
    }

    public function ecosistemaLaboral(): BelongsTo
    {
        return $this->belongsTo(EcosistemaLaboral::class);
    }
}
```

---

**Unidad anterior ←** [Unidad 0: Contexto y arquitectura EAC](./00_contexto_arquitectura_eac.md)

  **Siguiente capítulo →** [Factories y Seeders](./01_09_factories_seeders.md)

**Siguiente unidad →** [Unidad 2: Frontend: layout y vistas Blade para el marketplace](./02_frontend_blade.md)
