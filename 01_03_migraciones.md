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
        $table->enum('grado', ['GB','GM','GS','CE']);
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
            ->nullable()
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
        $table->foreignId('modulo_id')
            ->constrained('modulos')
            ->cascadeOnDelete();
        $table->string('codigo', 5);             // Ej: "RA1", "RA2"
        $table->text('descripcion');
        $table->timestamps();

        $table->unique(['modulo_id', 'codigo']);
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
        $table->string('codigo', 5);             // Ej: "CE1a", "CE1b"
        $table->text('descripcion');
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

    // Evitar que una SC sea requisito de sí misma (no válido en sqlite)
    // DB::statement('ALTER TABLE sc_precedencia ADD CONSTRAINT chk_sc_precedencia CHECK (sc_id != sc_requisito_id);');
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
        $table->foreignId('modulo_id')
            ->constrained('modulos')
            ->cascadeOnDelete();
        $table->timestamps();

        $table->unique(['estudiante_id', 'modulo_id']);
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

**Unidad anterior ←** [Unidad 0: Contexto y arquitectura EAC](./00_contexto_arquitectura_eac.md)

  **Siguiente capítulo →** [Modelos Eloquent](./01_08_modelos.md)

**Siguiente unidad →** [Unidad 2: Frontend: layout y vistas Blade para el marketplace](./02_frontend_blade.md)
