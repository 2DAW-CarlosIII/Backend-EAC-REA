## 1.11. Soluciones

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

### Seeder `CiclosFormativosSeeder`

```php
    public function run(): void
    {
        $path = database_path('seeders/csv/ciclos.csv');

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
                'familia_profesional_id' => FamiliaProfesional::where('codigo', trim($rec['familia'] ?? ''))->first()->id,
                'grado' => trim($rec['nivel'] ?? ''),
                'codigo' => trim($rec['cod_ciclo'] ?? ''),
                'nombre' => trim($rec['nombre'] ?? ''),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Insertar/actualizar usando upsert para evitar duplicados por 'codigo'
        DB::transaction(function () use ($data) {
            foreach (array_chunk($data, 200) as $chunk) {
                DB::table('ciclos_formativos')->upsert(
                    $chunk,
                    ['codigo'], // llave única para evitar duplicados
                    ['nombre', 'descripcion', 'updated_at']
                );
            }
        });
    }
```

### Seeder `ModulosFormativosSeeder`

```php
    public function run(): void
    {
        // Deshabilitar temporalmente la verificación de claves foráneas para evitar errores al insertar módulos sin ciclos formativos existentes
        Schema::disableForeignKeyConstraints();

        $path = database_path('seeders/csv/modulos.csv');

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
                'codigo' => trim($rec['cod_modulo'] ?? ''),
                'nombre' => trim($rec['nombre_modulo'] ?? ''),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Insertar/actualizar usando upsert para evitar duplicados por 'codigo'
        DB::transaction(function () use ($data) {
            foreach (array_chunk($data, 200) as $chunk) {
                DB::table('modulos')->upsert(
                    $chunk,
                    ['codigo'], // llave única para evitar duplicados
                    ['nombre', 'descripcion', 'updated_at']
                );
            }
        });

        $path = database_path('seeders/csv/ciclo_modulo_relaciones.csv');

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

            $ciclo = CicloFormativo::where('codigo', trim($rec['cod_ciclo'] ?? ''))->first();
            if (!$ciclo) {
                $this->command->error("Ciclo formativo no encontrado para código: " . trim($rec['cod_ciclo'] ?? ''));
                continue;
            }

            Modulo::where('codigo', trim($rec['cod_modulo'] ?? ''))->update([
                'ciclo_formativo_id' => $ciclo->id,
            ]);
        }

        Schema::enableForeignKeyConstraints();
    }
```

### Seeder `ResultadosAprendizajeSeeder`

```php
    public function run(): void
    {
        $path = database_path('seeders/csv/resultados_aprendizaje.csv');

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
                'modulo_id' => DB::table('modulos')->where('codigo', trim($rec['cod_modulo'] ?? ''))->value('id'),
                'codigo' => "RA" . trim($rec['id_ra'] ?? ''),
                'descripcion' => $rec['definicion'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Insertar/actualizar usando upsert para evitar duplicados por 'codigo'
        DB::transaction(function () use ($data) {
            foreach (array_chunk($data, 200) as $chunk) {
                DB::table('resultados_aprendizaje')->upsert(
                    $chunk,
                    ['modulo_id', 'codigo'], // llave única para evitar duplicados
                    ['descripcion', 'updated_at']
                );
            }
        });
    }
```

### Seeder `CriteriosEvaluacionSeeder`

```php
    public function run(): void
    {
        $path = database_path('seeders/csv/criterios_evaluacion.csv');

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

            $moduloId = Modulo::where('codigo', trim($rec['cod_modulo'] ?? ''))->first()->id ?? null;

            if(!$moduloId) {
                $this->command->warn("Módulo no encontrado para código '{$rec['cod_modulo']}'");
                continue;
            }

            $resultadoId = ResultadoAprendizaje::where(['modulo_id' => $moduloId, 'codigo' => "RA" . trim($rec['id_ra'] ?? '')])->first()->id ?? null;

            if(!$resultadoId) {
                $this->command->warn("Resultado de aprendizaje no encontrado para módulo '{$rec['cod_modulo']}' y RA 'RA{$rec['id_ra']}'");
                continue;
            }
            $data[] = [
                'resultado_aprendizaje_id' => $resultadoId,
                'codigo' => trim($rec['id_criterio'] ?? ''),
                'descripcion' => $rec['definicion'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Insertar/actualizar usando upsert para evitar duplicados por 'codigo'
        DB::transaction(function () use ($data) {
            foreach (array_chunk($data, 200) as $chunk) {
                DB::table('criterios_evaluacion')->upsert(
                    $chunk,
                    ['resultado_aprendizaje_id', 'codigo'], // llave única para evitar duplicados
                    ['descripcion', 'updated_at']
                );
            }
        });
    }
```

---

**Unidad anterior ←** [Unidad 0: Contexto y arquitectura EAC](./00_contexto_arquitectura_eac.md)

**Siguiente unidad →** [Unidad 2: Frontend: layout y vistas Blade para el marketplace](./02_frontend_blade.md)
