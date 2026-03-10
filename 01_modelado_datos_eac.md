# Unidad 1: Modelado de datos EAC en Laravel

## Objetivos de esta unidad

Al finalizar esta unidad serás capaz de:

- Traducir el esquema conceptual del EAC (Ecosistema Laboral, SC, Grafo de Precedencia, Perfil de Habilitación) en un esquema relacional de MariaDB.
- Crear las **migraciones** de todas las tablas del backend-EAC en el orden correcto (respetando las FK).
- Definir los **modelos Eloquent** con sus relaciones (`hasMany`, `belongsTo`, `belongsToMany`) para cada entidad.
- Entender qué tablas provienen del e-portfolio anterior, cuáles se adaptan y cuáles son completamente nuevas.
- Poblar la base de datos con datos de prueba usando **Factories** y **Seeders**.

---

## 1.1. El esquema completo: visión de conjunto

Antes de crear una sola migración, necesitas tener clara la imagen completa del esquema. Este es el diagrama ER del backend-EAC:

```
JERARQUÍA CURRICULAR (referencia normativa)
───────────────────────────────────────────────────────────────────────
familias_profesionales
  └──► ciclos_formativos
         └──► modulos                (= módulo del título, sin docente ni curso)
                └──► ecosistemas_laborales  (= dominio competencial del módulo)
                       ├──► resultados_aprendizaje (RA del currículo oficial)
                │      └──► criterios_evaluacion (CE del currículo oficial)
                │                 ▲
                │           sc_criterios_evaluacion (pivot many:many)
                │                 ▼
                └──► situaciones_competencia (SC)
                       ├──► nodos_requisito  (conocimientos/habilidades)
                       └──► sc_precedencia   (tabla de adyacencia del grafo)

USUARIOS Y ROLES
───────────────────────────────────────────────────────────────────────
users
  ├──► roles  (via user_roles)
  ├──► matriculas  ──► ecosistemas_laborales
  └──► perfiles_habilitacion  ──► ecosistemas_laborales
         └──► perfil_situacion  (pivot, incluye gradiente_autonomia)
                                ──► situaciones_competencia

EVALUACIÓN Y TRAZABILIDAD
───────────────────────────────────────────────────────────────────────
users ──► huellas_talento ──► ecosistemas_laborales
```

### Las 16 tablas del sistema

| # | Tabla | Tipo | Origen |
|---|-------|------|--------|
| 1 | `familias_profesionales` | Referencia curricular | Del e-portfolio (sin cambios) |
| 2 | `ciclos_formativos` | Referencia curricular | Del e-portfolio (sin cambios) |
| 3 | `modulos` | Referencia curricular | Adapta `modulos_formativos` (sin `docente_id` ni `curso_escolar`) |
| 4 | `ecosistemas_laborales` | Entidad raíz EAC | Nueva (FK apunta a `modulos`) |
| 5 | `resultados_aprendizaje` | Trazabilidad curricular | Del e-portfolio (FK cambia) |
| 6 | `criterios_evaluacion` | Trazabilidad curricular | Del e-portfolio (FK cambia) |
| 7 | `situaciones_competencia` | Núcleo EAC | Adapta `tareas` |
| 8 | `sc_criterios_evaluacion` | Pivot SC ↔ CE | Nueva (sustituye `criterios_tareas`) |
| 9 | `nodos_requisito` | Núcleo EAC | Nueva |
| 10 | `sc_precedencia` | Grafo de Precedencia | Nueva |
| 11 | `users` | Usuarios | Del e-portfolio (sin cambios) |
| 12 | `roles` / `user_roles` | Roles y contexto | Del e-portfolio (FK cambia) |
| 13 | `matriculas` | Acceso al ecosistema | Del e-portfolio (FK cambia) |
| 14 | `perfiles_habilitacion` | Estado competencial | Nueva |
| 15 | `perfil_situacion` | Pivot perfil ↔ SC | Nueva |
| 16 | `huellas_talento` | Exportable VFDS | Nueva |

---

## 1.2. Instalación y configuración inicial

Si todavía no tienes el proyecto creado, este es el momento. Asegúrate de tener el entorno Laradock funcionando (ver Unidad 0, [sección 0.6](./00_contexto_arquitectura_eac.md#06-preparación-del-entorno-con-laradock)).

### Verificar la conexión con MariaDB

Desde el contenedor workspace de Laradock:

```bash
php artisan migrate:status
```

Si ves el mensaje `Migration table not found`, ejecuta:

```bash
php artisan migrate
```

Esto crea la tabla `migrations` y aplica las migraciones por defecto de Laravel (`users`, `password_reset_tokens`, `sessions`, `cache`, `jobs`).

---

## 1.3. Migraciones: jerarquía curricular

Crea las migraciones en este orden exacto para respetar las claves foráneas.

### 1.3.1. Familias profesionales

```bash
php artisan make:migration create_familias_profesionales_table
```

```php
// database/migrations/xxxx_create_familias_profesionales_table.php

public function up(): void
{
    Schema::create('familias_profesionales', function (Blueprint $table) {
        $table->id();
        $table->string('nombre');
        $table->string('codigo', 10)->unique();
        $table->text('descripcion')->nullable();
        $table->timestamps();
    });
}
```

### 1.3.2. Ciclos formativos

```bash
php artisan make:migration create_ciclos_formativos_table
```

```php
public function up(): void
{
    Schema::create('ciclos_formativos', function (Blueprint $table) {
        $table->id();
        $table->foreignId('familia_profesional_id')
              ->constrained('familias_profesionales')
              ->cascadeOnDelete();
        $table->string('nombre');
        $table->string('codigo', 10)->unique();
        $table->enum('grado', ['basico', 'medio', 'superior']);
        $table->text('descripcion')->nullable();
        $table->timestamps();
    });
}
```

### 1.3.3. Módulos

La tabla `modulos` recoge los módulos formativos del título tal como los define la norma: nombre, código oficial y carga horaria. No incluye `docente_id` ni `curso_escolar` porque esa dimensión temporal y personal se gestiona a través de `user_roles` y `matriculas`.

```bash
php artisan make:migration create_modulos_table
```

```php
public function up(): void
{
    Schema::create('modulos', function (Blueprint $table) {
        $table->id();
        $table->foreignId('ciclo_formativo_id')
              ->constrained('ciclos_formativos')
              ->cascadeOnDelete();
        $table->string('nombre');
        $table->string('codigo', 20);             // Ej: "0614"
        $table->unsignedSmallInteger('horas_totales')->default(0);
        $table->text('descripcion')->nullable();
        $table->timestamps();

        $table->unique(['ciclo_formativo_id', 'codigo']);
    });
}
```

### 1.3.4. Ecosistemas laborales

El ecosistema laboral es la entidad raíz del EAC. Depende de un `modulo` concreto y representa su traducción al mapa competencial: un mismo módulo podría tener versiones distintas del ecosistema (por ejemplo, revisiones anuales del grafo de SCs) sin que eso afecte a la jerarquía curricular.

```bash
php artisan make:migration create_ecosistemas_laborales_table
```

```php
public function up(): void
{
    Schema::create('ecosistemas_laborales', function (Blueprint $table) {
        $table->id();
        $table->foreignId('modulo_id')
              ->constrained('modulos')
              ->cascadeOnDelete();
        $table->string('nombre');
        $table->string('codigo', 20)->unique();   // Ej: "0614-TBM"
        $table->text('descripcion')->nullable();
        $table->boolean('activo')->default(true);
        $table->timestamps();
    });
}
```

### 1.3.5. Resultados de Aprendizaje

Los RA pertenecen al ecosistema laboral, no al módulo formativo: son la representación de los objetivos de aprendizaje del título dentro del EAC.

```bash
php artisan make:migration create_resultados_aprendizaje_table
```

```php
public function up(): void
{
    Schema::create('resultados_aprendizaje', function (Blueprint $table) {
        $table->id();
        $table->foreignId('ecosistema_laboral_id')
              ->constrained('ecosistemas_laborales')
              ->cascadeOnDelete();
        $table->string('codigo', 20);             // Ej: "RA1", "RA2"
        $table->text('descripcion');
        $table->decimal('peso_porcentaje', 5, 2)->default(0);
        $table->unsignedSmallInteger('orden')->default(0);
        $table->timestamps();

        $table->unique(['ecosistema_laboral_id', 'codigo']);
    });
}
```

### 1.3.6. Criterios de Evaluación

```bash
php artisan make:migration create_criterios_evaluacion_table
```

```php
public function up(): void
{
    Schema::create('criterios_evaluacion', function (Blueprint $table) {
        $table->id();
        $table->foreignId('resultado_aprendizaje_id')
              ->constrained('resultados_aprendizaje')
              ->cascadeOnDelete();
        $table->string('codigo', 20);             // Ej: "CE1a", "CE1b"
        $table->text('descripcion');
        $table->decimal('peso_porcentaje', 5, 2)->default(0);
        $table->unsignedSmallInteger('orden')->default(0);
        $table->timestamps();

        $table->unique(['resultado_aprendizaje_id', 'codigo']);
    });
}
```

---

## 1.4. Migraciones: núcleo del grafo EAC

### 1.4.1. Situaciones de Competencia

La SC es el corazón del sistema. A diferencia de una `TAREA` del e-portfolio, no tiene `fecha_apertura` ni `fecha_cierre`: su disponibilidad la determina el Grafo de Precedencia en tiempo de ejecución.

```bash
php artisan make:migration create_situaciones_competencia_table
```

```php
public function up(): void
{
    Schema::create('situaciones_competencia', function (Blueprint $table) {
        $table->id();
        $table->foreignId('ecosistema_laboral_id')
              ->constrained('ecosistemas_laborales')
              ->cascadeOnDelete();
        $table->string('codigo', 20);             // Ej: "SC-01"
        $table->string('titulo');
        $table->text('descripcion');

        // Umbral de Maestría: porcentaje mínimo para considerar la SC conquistada
        $table->decimal('umbral_maestria', 5, 2)->default(80.00);

        // Complejidad relativa (para ordenación y ZDP)
        $table->unsignedTinyInteger('nivel_complejidad')->default(1); // 1-5

        $table->boolean('activa')->default(true);
        $table->timestamps();

        $table->unique(['ecosistema_laboral_id', 'codigo']);
    });
}
```

### 1.4.2. Pivot SC ↔ Criterios de Evaluación

Esta tabla cierra el círculo de trazabilidad curricular: registra qué criterios de evaluación del currículo oficial quedan cubiertos por cada SC.

```bash
php artisan make:migration create_sc_criterios_evaluacion_table
```

```php
public function up(): void
{
    Schema::create('sc_criterios_evaluacion', function (Blueprint $table) {
        $table->foreignId('situacion_competencia_id')
              ->constrained('situaciones_competencia')
              ->cascadeOnDelete();
        $table->foreignId('criterio_evaluacion_id')
              ->constrained('criterios_evaluacion')
              ->cascadeOnDelete();

        // Peso del CE dentro de la evaluación de esta SC concreta
        $table->decimal('peso_en_sc', 5, 2)->default(0);

        $table->primary(['situacion_competencia_id', 'criterio_evaluacion_id']);
    });
}
```

> **¿Por qué many-to-many?** Una SC puede cubrir CE de distintos RA (por ejemplo, SC-01 cubre CE1a, CE1b y CE2c). Y un CE puede ser evaluado por más de una SC (diferentes contextos de aplicación). Esta relación es la que permite calcular la calificación final del módulo a partir del Gradiente de Autonomía.

### 1.4.3. Nodos de Requisito

Los nodos son los conocimientos y habilidades atómicos previos a una SC. Se diferencian del contenido de la SC en que no son "lo que se demuestra" sino "lo que se necesita para poder demostrarlo".

```bash
php artisan make:migration create_nodos_requisito_table
```

```php
public function up(): void
{
    Schema::create('nodos_requisito', function (Blueprint $table) {
        $table->id();
        $table->foreignId('situacion_competencia_id')
              ->constrained('situaciones_competencia')
              ->cascadeOnDelete();
        $table->enum('tipo', ['conocimiento', 'habilidad']);
        $table->text('descripcion');
        $table->unsignedSmallInteger('orden')->default(0);
        $table->timestamps();
    });
}
```

### 1.4.4. Grafo de Precedencia (tabla de adyacencia)

Esta es la tabla más característica del EAC. Define las dependencias entre SCs: si `sc_requisito_id` debe estar conquistada antes de poder acceder a `sc_id`.

```bash
php artisan make:migration create_sc_precedencia_table
```

```php
public function up(): void
{
    Schema::create('sc_precedencia', function (Blueprint $table) {
        // La SC que requiere un prerequisito
        $table->foreignId('sc_id')
            ->constrained('situaciones_competencia')
            ->cascadeOnDelete();
        // La SC que debe estar conquistada previamente
        $table->foreignId('sc_requisito_id')
            ->constrained('situaciones_competencia')
            ->cascadeOnDelete();

        $table->primary(['sc_id', 'sc_requisito_id']);
    });

    // Evitar que una SC sea requisito de sí misma
    DB::statement('ALTER TABLE sc_precedencia ADD CONSTRAINT chk_sc_precedencia CHECK (sc_id != sc_requisito_id);');
}
```

> **Importante:** Esta tabla no detecta ciclos en el grafo (SC-01 requiere SC-02 y SC-02 requiere SC-01). La validación de acíclicidad se implementará en el servicio `GrafoService` en la Unidad 5.

---

## 1.5. Migraciones: usuarios, roles y acceso

### 1.5.1. Roles

```bash
php artisan make:migration create_roles_table
```

```php
public function up(): void
{
    Schema::create('roles', function (Blueprint $table) {
        $table->id();
        $table->string('name')->unique();      // 'docente', 'estudiante', 'administrador'
        $table->text('description')->nullable();
        $table->timestamps();
    });
}
```

### 1.5.2. User Roles (pivot con contexto)

El rol de un usuario siempre tiene un contexto: un usuario puede ser `docente` en el ecosistema de Merchandising y `estudiante` en otro.

```bash
php artisan make:migration create_user_roles_table
```

```php
public function up(): void
{
    Schema::create('user_roles', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')
              ->constrained('users')
              ->cascadeOnDelete();
        $table->foreignId('role_id')
              ->constrained('roles')
              ->cascadeOnDelete();
        $table->foreignId('ecosistema_laboral_id')
              ->nullable()
              ->constrained('ecosistemas_laborales')
              ->nullOnDelete();
        $table->timestamps();

        $table->unique(['user_id', 'role_id', 'ecosistema_laboral_id']);
    });
}
```

### 1.5.3. Matrículas

```bash
php artisan make:migration create_matriculas_table
```

```php
public function up(): void
{
    Schema::create('matriculas', function (Blueprint $table) {
        $table->id();
        $table->foreignId('estudiante_id')
              ->constrained('users')
              ->cascadeOnDelete();
        $table->foreignId('ecosistema_laboral_id')
              ->constrained('ecosistemas_laborales')
              ->cascadeOnDelete();
        $table->timestamps();

        $table->unique(['estudiante_id', 'ecosistema_laboral_id']);
    });
}
```

---

## 1.6. Migraciones: estado competencial y trazabilidad

### 1.6.1. Perfiles de Habilitación

Un perfil de habilitación es el estado competencial de un estudiante en un ecosistema laboral concreto. Agrupa todas las SCs que ese estudiante ha conquistado.

```bash
php artisan make:migration create_perfiles_habilitacion_table
```

```php
public function up(): void
{
    Schema::create('perfiles_habilitacion', function (Blueprint $table) {
        $table->id();
        $table->foreignId('estudiante_id')
              ->constrained('users')
              ->cascadeOnDelete();
        $table->foreignId('ecosistema_laboral_id')
              ->constrained('ecosistemas_laborales')
              ->cascadeOnDelete();

        // Calificación calculada dinámicamente (se actualiza en cada conquista)
        $table->decimal('calificacion_actual', 4, 2)->default(0.00);

        $table->timestamps();

        // Un estudiante solo puede tener un perfil por ecosistema
        $table->unique(['estudiante_id', 'ecosistema_laboral_id']);
    });
}
```

### 1.6.2. Pivot Perfil ↔ Situación Conquistada

Esta es la tabla que registra qué SCs ha conquistado un estudiante y con qué Gradiente de Autonomía.

```bash
php artisan make:migration create_perfil_situacion_table
```

```php
public function up(): void
{
    Schema::create('perfil_situacion', function (Blueprint $table) {
        $table->foreignId('perfil_habilitacion_id')
              ->constrained('perfiles_habilitacion')
              ->cascadeOnDelete();
        $table->foreignId('situacion_competencia_id')
              ->constrained('situaciones_competencia')
              ->cascadeOnDelete();

        // Gradiente de Autonomía alcanzado
        $table->enum('gradiente_autonomia', [
            'asistido',
            'guiado',
            'supervisado',
            'autonomo',
        ]);

        // Puntuación que obtuvo en la evaluación que conquistó la SC
        $table->decimal('puntuacion_conquista', 5, 2)->nullable();

        // Número de intentos hasta conquistar la SC
        $table->unsignedSmallInteger('intentos')->default(1);

        $table->timestamp('fecha_conquista')->useCurrent();

        $table->primary(['perfil_habilitacion_id', 'situacion_competencia_id']);
    });
}
```

### 1.6.3. Huellas de Talento

La Huella de Talento es el registro exportable del recorrido completo del estudiante. Se almacena como JSON para facilitar su publicación en el VFDS.

Crea la migración de la tabla `huellas_talento` con los siguientes campos:

* `id` (PK)
* `estudiante_id` (FK a `users`)
* `ecosistema_laboral_id` (FK a `ecosistemas_laborales`)
* `payload` (JSON, para almacenar el estado competencial completo en el momento de la exportación)
* `ngsi_ld_id` (string, para almacenar la URN del recurso NGSI-LD publicado en Orion, si ya fue publicado)
* `generada_en` (timestamp, para registrar cuándo se generó la huella)
* `created_at` y `updated_at` (timestamps por defecto de Laravel)

Asegúrate de definir las claves foráneas (con borrado en cascada) correctamente para mantener la integridad referencial. La tabla `huellas_talento` es crucial para la trazabilidad y la interoperabilidad con el VFDS, ya que contiene un snapshot del estado competencial del estudiante en un formato exportable.

Puedes ver una posible solución en el apartado [Soluciones](#tabla-huellas_talento).

---

## 1.7. Ejecutar todas las migraciones

```bash
php artisan migrate
```

Verifica que las 16 tablas se han creado correctamente:

```bash
php artisan migrate:status
```

Debes ver todas las migraciones con estado `Ran`. Si alguna falla por orden de FK, revisa que el orden de los ficheros de migración (por timestamp) coincida con el orden de creación de las secciones anteriores.

---

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

    protected $casts = ['activo' => 'boolean'];

    public function modulo(): BelongsTo
    {
        return $this->belongsTo(Modulo::class);
    }

    public function resultadosAprendizaje(): HasMany
    {
        return $this->hasMany(ResultadoAprendizaje::class);
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
        'ecosistema_laboral_id', 'codigo', 'descripcion',
        'peso_porcentaje', 'orden',
    ];

    public function ecosistemaLaboral(): BelongsTo
    {
        return $this->belongsTo(EcosistemaLaboral::class);
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
        'peso_porcentaje', 'orden',
    ];

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

// Roles del usuario (con contexto de ecosistema)
public function userRoles(): HasMany
{
    return $this->hasMany(UserRole::class);
}

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

### 1.8.10. PerfilHabilitacion

```php
// app/Models/PerfilHabilitacion.php

class PerfilHabilitacion extends Model
{
    protected $fillable = [
        'estudiante_id', 'ecosistema_laboral_id', 'calificacion_actual',
    ];

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

### 1.8.11. HuellaTalento

```php
// app/Models/HuellaTalento.php

class HuellaTalento extends Model
{
    protected $fillable = [
        'estudiante_id', 'ecosistema_laboral_id',
        'payload', 'ngsi_ld_id', 'generada_en',
    ];

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

## 1.9. Factories y Seeders

Con los modelos listos, crea datos de prueba para el módulo piloto: **Técnicas Básicas de Merchandising**.

### 1.9.1. Crear los factories

```bash
php artisan make:factory EcosistemaLaboralFactory --model=EcosistemaLaboral
php artisan make:factory SituacionCompetenciaFactory --model=SituacionCompetencia
php artisan make:factory PerfilHabilitacionFactory --model=PerfilHabilitacion
```

Factory de SituacionCompetencia:

```php
// database/factories/SituacionCompetenciaFactory.php

public function definition(): array
{
    return [
        'ecosistema_laboral_id' => EcosistemaLaboral::factory(),
        'codigo'                => 'SC-' . str_pad($this->faker->unique()->numberBetween(1, 99), 2, '0', STR_PAD_LEFT),
        'titulo'                => $this->faker->sentence(6),
        'descripcion'           => $this->faker->paragraph(),
        'umbral_maestria'       => $this->faker->randomElement([70.00, 75.00, 80.00, 85.00]),
        'nivel_complejidad'     => $this->faker->numberBetween(1, 5),
        'activa'                => true,
    ];
}
```

### 1.9.2. Crear `FamiliasProfesionalesSeeder` desde CSV

En el repositorio de este REA en GitHub, puedes encontrar [ficheros _CSV_](https://github.com/2DAW-CarlosIII/Backend-EAC-REA/tree/master/documentos/seeders) con datos reales de familias profesionales, ciclos formativos, módulos formativos, resultados de aprendizaje y criterios de evaluación, preparados para alimentar la base de datos.

1) Copia los _CSV_ al directorio `storage/seeders` de la aplicación _Laravel_:

(Usamos `storage/seeders` para mantener los CSV de importación dentro del proyecto.

2) Crear el seeder:

```bash
php artisan make:seeder FamiliasProfesionalesSeeder
```

3) Implementación mínima usando `str_getcsv()` (pega esto en `database/seeders/FamiliasProfesionalesSeeder.php` en el método `run()`):

```php
public function run(): void
{
    $path = storage_path('seeders/familias.csv');

    if (!file_exists($path)) {
        $this->command->error("CSV no encontrado: $path");
        return;
    }

    // Leer todas las líneas y parsear con str_getcsv
    $rows = array_map('str_getcsv', file($path));

    // El primer registro es la cabecera
    $header = array_map('trim', array_shift($rows));

    $data = [];
    foreach ($rows as $row) {
        // Ignorar filas vacías o mal formadas
        if (count($row) < count($header)) {
            continue;
        }

        $rec = array_combine($header, $row);

        $data[] = [
            'nombre' => trim($rec['nombre'] ?? ''),
            'codigo' => trim($rec['codigo'] ?? ''),
            'descripcion' => $rec['descripcion'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    // Insertar/actualizar usando upsert para evitar duplicados por 'codigo'
    DB::transaction(function () use ($data) {
        foreach (array_chunk($data, 200) as $chunk) {
            DB::table('familias_profesionales')->upsert(
                $chunk,
                ['codigo'], // llave única para evitar duplicados
                ['nombre', 'descripcion', 'updated_at']
            );
        }
    });
}
```

Notas y recomendaciones:

- Asegúrate de que el CSV tenga una cabecera con, al menos, las columnas `nombre` y `codigo`.
- Si el fichero contiene BOM u otros problemas de codificación, normaliza a UTF-8 antes de importarlo.
- Usa `upsert()` con la columna `codigo` para poder ejecutar el seeder repetidas veces sin duplicar registros.
- Valida y limpia los campos (trim, casts) antes de insertar.

### 1.9.3. Crear seeders para ciclos, módulos, RA y CE

Repite el proceso anterior para cada entidad (CicloFormativo, Modulo, ResultadoAprendizaje, CriterioEvaluacion), creando un seeder específico para cada una y adaptando el código de importación al formato de su CSV correspondiente. Asegúrate de respetar las relaciones entre entidades (por ejemplo, al importar módulos, debes relacionarlos con el ciclo formativo correcto).

### 1.9.4. Seeders del módulo piloto

```bash
php artisan make:seeder EcosistemaLaboralSeeder
```

```php
// database/seeders/EcosistemaLaboralSeeder.php

public function run(): void
{
    // 1. Jerarquía curricular
    $familia = FamiliaProfesional::create([
        'nombre'  => 'Comercio y Marketing',
        'codigo'  => 'COM',
    ]);

    $ciclo = CicloFormativo::create([
        'familia_profesional_id' => $familia->id,
        'nombre'  => 'Actividades Comerciales',
        'codigo'  => 'AC',
        'grado'   => 'medio',
    ]);

    $modulo = Modulo::create([
        'ciclo_formativo_id' => $ciclo->id,
        'nombre'             => 'Técnicas Básicas de Merchandising',
        'codigo'             => '0614',
        'horas_totales'      => 96,
    ]);

    $ecosistema = EcosistemaLaboral::create([
        'modulo_id' => $modulo->id,
        'nombre'    => 'Técnicas Básicas de Merchandising',
        'codigo'    => 'AC-TBM',
        'activo'    => true,
    ]);

    // 2. Resultados de Aprendizaje y Criterios de Evaluación
    $ra1 = ResultadoAprendizaje::create([
        'ecosistema_laboral_id' => $ecosistema->id,
        'codigo'      => 'RA1',
        'descripcion' => 'Organiza productos en el punto de venta aplicando técnicas de merchandising.',
        'peso_porcentaje' => 40,
        'orden'       => 1,
    ]);

    $ce1a = CriterioEvaluacion::create([
        'resultado_aprendizaje_id' => $ra1->id,
        'codigo'      => 'CE1a',
        'descripcion' => 'Se han descrito los principios del visual merchandising.',
        'peso_porcentaje' => 30, 'orden' => 1,
    ]);
    $ce1b = CriterioEvaluacion::create([
        'resultado_aprendizaje_id' => $ra1->id,
        'codigo'      => 'CE1b',
        'descripcion' => 'Se han identificado las zonas calientes y frías del punto de venta.',
        'peso_porcentaje' => 40, 'orden' => 2,
    ]);
    $ce1c = CriterioEvaluacion::create([
        'resultado_aprendizaje_id' => $ra1->id,
        'codigo'      => 'CE1c',
        'descripcion' => 'Se ha elaborado un planograma básico para el lineal.',
        'peso_porcentaje' => 30, 'orden' => 3,
    ]);

    $ra2 = ResultadoAprendizaje::create([
        'ecosistema_laboral_id' => $ecosistema->id,
        'codigo'      => 'RA2',
        'descripcion' => 'Analiza el rendimiento del punto de venta mediante indicadores.',
        'peso_porcentaje' => 35,
        'orden'       => 2,
    ]);

    $ce2a = CriterioEvaluacion::create([
        'resultado_aprendizaje_id' => $ra2->id,
        'codigo'      => 'CE2a',
        'descripcion' => 'Se han calculado índices de rotación de stock.',
        'peso_porcentaje' => 50, 'orden' => 1,
    ]);
    $ce2b = CriterioEvaluacion::create([
        'resultado_aprendizaje_id' => $ra2->id,
        'codigo'      => 'CE2b',
        'descripcion' => 'Se han identificado productos de baja rotación y propuesto acciones de mejora.',
        'peso_porcentaje' => 50, 'orden' => 2,
    ]);

    // 3. Situaciones de Competencia
    $sc01 = SituacionCompetencia::create([
        'ecosistema_laboral_id' => $ecosistema->id,
        'codigo'      => 'SC-01',
        'titulo'      => 'Diseñar la disposición de productos en un lineal',
        'descripcion' => 'El estudiante diseña y argumenta la disposición óptima de una categoría de productos en un lineal de 2m, aplicando los principios del visual merchandising y elaborando el planograma correspondiente.',
        'umbral_maestria'     => 80.00,
        'nivel_complejidad'   => 2,
    ]);

    $sc02 = SituacionCompetencia::create([
        'ecosistema_laboral_id' => $ecosistema->id,
        'codigo'      => 'SC-02',
        'titulo'      => 'Elaborar un planograma básico para un punto de venta',
        'descripcion' => 'El estudiante elabora el planograma completo de una sección de un establecimiento, justificando la ubicación de cada producto en función de su rotación y margen.',
        'umbral_maestria'     => 80.00,
        'nivel_complejidad'   => 2,
    ]);

    $sc03 = SituacionCompetencia::create([
        'ecosistema_laboral_id' => $ecosistema->id,
        'codigo'      => 'SC-03',
        'titulo'      => 'Analizar el rendimiento de una zona caliente/fría',
        'descripcion' => 'El estudiante analiza el rendimiento de un punto de venta real o simulado mediante indicadores de ventas e identifica propuestas de mejora para las zonas de bajo rendimiento.',
        'umbral_maestria'     => 75.00,
        'nivel_complejidad'   => 3,
    ]);

    // 4. Trazabilidad curricular: qué CE cubre cada SC
    $sc01->criteriosEvaluacion()->attach([
        $ce1a->id => ['peso_en_sc' => 30],
        $ce1b->id => ['peso_en_sc' => 40],
        $ce1c->id => ['peso_en_sc' => 30],
    ]);
    $sc02->criteriosEvaluacion()->attach([
        $ce1c->id => ['peso_en_sc' => 60],
        $ce2a->id => ['peso_en_sc' => 40],
    ]);
    $sc03->criteriosEvaluacion()->attach([
        $ce1b->id => ['peso_en_sc' => 30],
        $ce2a->id => ['peso_en_sc' => 40],
        $ce2b->id => ['peso_en_sc' => 30],
    ]);

    // 5. Nodos de Requisito
    NodoRequisito::insert([
        ['situacion_competencia_id' => $sc01->id, 'tipo' => 'conocimiento',
         'descripcion' => 'Conocer los principios del color en escaparatismo', 'orden' => 1],
        ['situacion_competencia_id' => $sc01->id, 'tipo' => 'habilidad',
         'descripcion' => 'Manejar software básico de diseño (Canva o similar)', 'orden' => 2],
        ['situacion_competencia_id' => $sc02->id, 'tipo' => 'conocimiento',
         'descripcion' => 'Conocer la estructura de un planograma', 'orden' => 1],
        ['situacion_competencia_id' => $sc02->id, 'tipo' => 'habilidad',
         'descripcion' => 'Manejar software de planogramas', 'orden' => 2],
        ['situacion_competencia_id' => $sc03->id, 'tipo' => 'conocimiento',
         'descripcion' => 'Conocer los indicadores de rendimiento comercial (KPIs)', 'orden' => 1],
        ['situacion_competencia_id' => $sc03->id, 'tipo' => 'habilidad',
         'descripcion' => 'Calcular el índice de rotación de stock', 'orden' => 2],
    ]);

    // 6. Grafo de Precedencia
    // SC-03 requiere haber conquistado SC-01 y SC-02
    DB::table('sc_precedencia')->insert([
        ['sc_id' => $sc03->id, 'sc_requisito_id' => $sc01->id],
        ['sc_id' => $sc03->id, 'sc_requisito_id' => $sc02->id],
    ]);

    // 7. Usuarios de prueba
    $docente = User::factory()->create([
        'name'  => 'Profesora Ejemplo',
        'email' => 'docente@backend-eac.test',
    ]);

    $estudiante = User::factory()->create([
        'name'  => 'Estudiante Ejemplo',
        'email' => 'estudiante@backend-eac.test',
    ]);

    $rolDocente    = Role::create(['name' => 'docente',    'description' => 'Docente del ecosistema']);
    $rolEstudiante = Role::create(['name' => 'estudiante', 'description' => 'Estudiante matriculado']);

    // Asignación de roles con contexto
    DB::table('user_roles')->insert([
        ['user_id' => $docente->id,    'role_id' => $rolDocente->id,    'ecosistema_laboral_id' => $ecosistema->id],
        ['user_id' => $estudiante->id, 'role_id' => $rolEstudiante->id, 'ecosistema_laboral_id' => $ecosistema->id],
    ]);

    // Matrícula
    Matricula::create([
        'estudiante_id'        => $estudiante->id,
        'ecosistema_laboral_id' => $ecosistema->id,
    ]);

    // Perfil de Habilitación inicial (vacío)
    PerfilHabilitacion::create([
        'estudiante_id'        => $estudiante->id,
        'ecosistema_laboral_id' => $ecosistema->id,
        'calificacion_actual'  => 0.00,
    ]);
}
```

### 1.9.4. Registra los seeders en `DatabaseSeeder`:

```php
// database/seeders/DatabaseSeeder.php

public function run(): void
{
    $this->call([
        FamiliasProfesionalesSeeder::class,
        CiclosFormativosSeeder::class,
        ModulosSeeder::class,
        ResultadosAprendizajeSeeder::class,
        CriteriosEvaluacionSeeder::class,
        EcosistemaLaboralSeeder::class,
    ]);
}
```

Ejecuta:

```bash
php artisan db:seed
# o desde cero:
php artisan migrate:fresh --seed
```

---

## 1.10. Verificación con Tinker

Comprueba que el esquema y los datos de prueba están correctamente relacionados:

```bash
php artisan tinker
```

```php
// ¿Cuántas SCs tiene el ecosistema piloto?
App\Models\EcosistemaLaboral::first()->situacionesCompetencia()->count();
// → 3

// ¿Qué CE cubre SC-03?
$sc03 = App\Models\SituacionCompetencia::where('codigo', 'SC-03')->first();
$sc03->criteriosEvaluacion()->pluck('codigo');
// → ['CE1b', 'CE2a', 'CE2b']

// ¿Qué SCs son prerequisito de SC-03?
$sc03->prerequisitos()->pluck('codigo');
// → ['SC-01', 'SC-02']

// ¿El estudiante tiene perfil creado?
App\Models\User::where('email', 'estudiante@backend-eac.test')
    ->first()
    ->perfilesHabilitacion()
    ->with('ecosistemaLaboral')
    ->first();
// → PerfilHabilitacion { ecosistema_laboral: EcosistemaLaboral { nombre: 'Técnicas Básicas de Merchandising' } }

// ¿Qué SCs ha conquistado? (ninguna aún)
App\Models\PerfilHabilitacion::first()->situacionesConquistadas()->count();
// → 0
```

---

## ✅ Verificación final de la Unidad 1

Antes de continuar con la Unidad 2, confirma que:

- [ ] `php artisan migrate:status` muestra las 16 migraciones en estado `Ran`.
- [ ] `php artisan db:seed` completa sin errores.
- [ ] En Tinker, `EcosistemaLaboral::first()->situacionesCompetencia()->count()` devuelve `3`.
- [ ] `SituacionCompetencia::where('codigo', 'SC-03')->first()->prerequisitos()->pluck('codigo')` devuelve `['SC-01', 'SC-02']`.
- [ ] `SituacionCompetencia::where('codigo', 'SC-03')->first()->criteriosEvaluacion()->count()` devuelve `3`.
- [ ] Existen usuarios `docente@backend-eac.test` y `estudiante@backend-eac.test` con sus roles y el perfil de habilitación creado.
- [ ] Puedes explicar la diferencia entre `perfil_situacion` y `perfiles_habilitacion`.
- [ ] Puedes explicar por qué `sc_criterios_evaluacion` es una relación many-to-many y no una FK directa.

---

## 📖 Referencias

- [Repositorio del caso de uso VFDS-EAC](https://github.com/C3-VFDS/use_case_pkst)
- [Laravel Migrations](https://laravel.com/docs/11.x/migrations)
- [Laravel Eloquent Relationships](https://laravel.com/docs/11.x/eloquent-relationships)
- [Laravel Factories](https://laravel.com/docs/11.x/eloquent-factories)
- [Laravel Seeders](https://laravel.com/docs/11.x/seeding)

## Soluciones

### Tabla `huellas_talento`

```bash
php artisan make:migration create_huellas_talento_table
```

```php
public function up(): void
{
    Schema::create('huellas_talento', function (Blueprint $table) {
        $table->id();
        $table->foreignId('estudiante_id')
              ->constrained('users')
              ->cascadeOnDelete();
        $table->foreignId('ecosistema_laboral_id')
              ->constrained('ecosistemas_laborales')
              ->cascadeOnDelete();

        // Snapshot del estado competencial en el momento de la exportación
        $table->json('payload');

        // URN del recurso NGSI-LD publicado en Orion (si ya fue publicado)
        $table->string('ngsi_ld_id')->nullable();

        $table->timestamp('generada_en')->useCurrent();
        $table->timestamps();
    });
}
```

### Modelo `SituacionCompetencia`

```php
// app/Models/SituacionCompetencia.php

class SituacionCompetencia extends Model
{
    protected $fillable = [
        'ecosistema_laboral_id', 'codigo', 'titulo', 'descripcion',
        'umbral_maestria', 'nivel_complejidad', 'activa',
    ];

    protected $casts = [
        'umbral_maestria'   => 'decimal:2',
        'activa'            => 'boolean',
    ];

    public function ecosistemaLaboral(): BelongsTo
    {
        return $this->belongsTo(EcosistemaLaboral::class);
    }

    public function nodosRequisito(): HasMany
    {
        return $this->hasMany(NodoRequisito::class);
    }

    // SCs que deben estar conquistadas ANTES de acceder a esta SC
    public function prerequisitos(): BelongsToMany
    {
        return $this->belongsToMany(
            SituacionCompetencia::class,
            'sc_precedencia',
            'sc_id',           // esta SC
            'sc_requisito_id'  // sus prerequisitos
        );
    }

    // SCs que requieren esta SC como prerequisito
    public function dependientes(): BelongsToMany
    {
        return $this->belongsToMany(
            SituacionCompetencia::class,
            'sc_precedencia',
            'sc_requisito_id', // esta SC es el requisito
            'sc_id'            // las SCs que la necesitan
        );
    }

    // CEs del currículo que cubre esta SC
    public function criteriosEvaluacion(): BelongsToMany
    {
        return $this->belongsToMany(
            CriterioEvaluacion::class,
            'sc_criterios_evaluacion',
            'situacion_competencia_id',
            'criterio_evaluacion_id'
        )->withPivot('peso_en_sc');
    }
}
```

---

**Unidad anterior ←** [Unidad 0: Contexto y arquitectura EAC](./00_contexto_arquitectura_eac.md)

**Siguiente unidad →** [Unidad 2: Frontend: layout y vistas Blade para el marketplace](./02_frontend_blade.md)
