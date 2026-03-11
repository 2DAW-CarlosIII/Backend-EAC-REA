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

1) Copia los _CSV_ al directorio `database/seeders/csv` de la aplicación _Laravel_:

(Usamos `database/seeders/csv` para mantener los CSV de importación dentro del proyecto.

2) Crear el seeder:

```bash
php artisan make:seeder FamiliasProfesionalesSeeder
```

3) Implementación mínima usando `str_getcsv()` (pega esto en `database/seeders/FamiliasProfesionalesSeeder.php` en el método `run()`):

```php
public function run(): void
{
    $path = database_path('seeders/csv/familias_profesionales.csv');

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
- Usa `upsert()` con la columna `codigo` para poder ejecutar el seeder repetidas veces sin duplicar registros.
- Valida y limpia los campos (trim, casts) antes de insertar.

### 1.9.3. Crear seeders para ciclos, módulos, RA y CE

Repite el proceso anterior para cada entidad (CicloFormativo, Modulo, ResultadoAprendizaje, CriterioEvaluacion), creando un seeder específico para cada una y adaptando el código de importación al formato de su CSV correspondiente. Tendrás que adaptar las claves del array `$data` a los campos de cada tabla.

Asegúrate de respetar las relaciones entre entidades (por ejemplo, al importar módulos, debes relacionarlos con el ciclo formativo correcto).

Las soluciones completas para cada seeder se pueden encontrar en el apartado Soluciones:
- [CiclosFormativosSeeder](#seeder-ciclosformativosseeder)
- [ModulosSeeder](#seeder-modulosseeder)
- [ResultadosAprendizajeSeeder](#seeder-resultadosaprendizajeseeder)
- [CriteriosEvaluacionSeeder](#seeder-criteriosevaluacionseeder)

### 1.9.4. Seeders del módulo piloto

```bash
php artisan make:seeder EcosistemaLaboralSeeder
```

```php
// database/seeders/EcosistemaLaboralSeeder.php

public function run(): void
{
    Schema::disableForeignKeyConstraints(); // Deshabilitar temporalmente las restricciones de clave foránea
    // 1. Jerarquía curricular
    $familia = FamiliaProfesional::where([
        'nombre'  => 'Comercio y Marketing',
        'codigo'  => 'COM',
    ])->first();

    $ciclo = CicloFormativo::where([
        'familia_profesional_id' => $familia->id,
        'nombre'  => 'Servicios Comerciales',
    ])->first();

    $modulo = Modulo::where([
        'ciclo_formativo_id' => $ciclo->id,
        'nombre'             => 'Técnicas básicas de merchandising',
    ])->first();


    DB::table('huellas_talento')->truncate(); // Limpiar huellas anteriores para evitar duplicados
    EcosistemaLaboral::truncate(); // Limpiar ecosistemas anteriores para evitar duplicados
    $ecosistema = EcosistemaLaboral::create([
        'modulo_id' => $modulo->id,
        'nombre'    => $modulo->nombre,
        'codigo'    => 'AC-TBM',
        'activo'    => true,
    ]);

    // 2. Resultados de Aprendizaje y Criterios de Evaluación
    $ra1 = ResultadoAprendizaje::where([
        'modulo_id' => $modulo->id,
        'codigo'      => 'RA1',
    ])->first();
        $ce1a = CriterioEvaluacion::where([
            'resultado_aprendizaje_id' => $ra1->id,
            'codigo'      => 'a',
        ])->first();
        $ce1b = CriterioEvaluacion::where([
            'resultado_aprendizaje_id' => $ra1->id,
            'codigo'      => 'b',
        ])->first();
        $ce1c = CriterioEvaluacion::where([
            'resultado_aprendizaje_id' => $ra1->id,
            'codigo'      => 'c',
        ])->first();

    $ra2 = ResultadoAprendizaje::where([
        'modulo_id' => $modulo->id,
        'codigo'      => 'RA2',
    ])->first();
        $ce2a = CriterioEvaluacion::where([
            'resultado_aprendizaje_id' => $ra2->id,
            'codigo'      => 'a',
        ])->first();
        $ce2b = CriterioEvaluacion::where([
            'resultado_aprendizaje_id' => $ra2->id,
            'codigo'      => 'b',
        ])->first();
        $ce2c = CriterioEvaluacion::where([
            'resultado_aprendizaje_id' => $ra2->id,
            'codigo'      => 'c',
        ])->first();

    // 3. Situaciones de Competencia
    SituacionCompetencia::truncate(); // Limpiar SC anteriores para evitar duplicados
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
    DB::table('sc_criterios_evaluacion')->truncate(); // Limpiar trazabilidades anteriores para evitar duplicados
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
    NodoRequisito::truncate(); // Limpiar nodos anteriores para evitar duplicados
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
    DB::table('sc_precedencia')->truncate(); // Limpiar precedencias anteriores para evitar duplicados
    DB::table('sc_precedencia')->insert([
        ['sc_id' => $sc03->id, 'sc_requisito_id' => $sc01->id],
        ['sc_id' => $sc03->id, 'sc_requisito_id' => $sc02->id],
    ]);

    // 7. Usuarios de prueba
    User::truncate(); // Limpiar usuarios anteriores para evitar duplicados
    $docente = User::factory()->create([
        'name'  => 'Profesora Ejemplo',
        'email' => 'docente@backend-eac.test',
    ]);

    $estudiante = User::factory()->create([
        'name'  => 'Estudiante Ejemplo',
        'email' => 'estudiante@backend-eac.test',
    ]);

    Role::truncate(); // Limpiar roles anteriores para evitar duplicados
    $rolDocente    = Role::create(['name' => 'docente',    'description' => 'Docente del ecosistema']);
    $rolEstudiante = Role::create(['name' => 'estudiante', 'description' => 'Estudiante matriculado']);

    // Asignación de roles con contexto
    DB::table('user_roles')->truncate(); // Limpiar asignaciones anteriores para evitar duplicados
    DB::table('user_roles')->insert([
        ['user_id' => $docente->id,    'role_id' => $rolDocente->id,    'ecosistema_laboral_id' => $ecosistema->id],
        ['user_id' => $estudiante->id, 'role_id' => $rolEstudiante->id, 'ecosistema_laboral_id' => $ecosistema->id],
    ]);

    // Matrícula
    Matricula::truncate(); // Limpiar matrículas anteriores para evitar duplicados
    Matricula::create([
        'estudiante_id'        => $estudiante->id,
        'modulo_id' => $modulo->id,
    ]);

    // Perfil de Habilitación inicial (vacío)
    PerfilHabilitacion::truncate(); // Limpiar perfiles anteriores para evitar duplicados
    PerfilHabilitacion::create([
        'estudiante_id'        => $estudiante->id,
        'ecosistema_laboral_id' => $ecosistema->id,
        'calificacion_actual'  => 0.00,
    ]);

    Schema::enableForeignKeyConstraints(); // Habilitar nuevamente las restricciones de clave foránea
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
        ModulosFormativosSeeder::class,
        ResultadosAprendizajeSeeder::class,
        CriteriosEvaluacionSeeder::class,
    ]);
}
```

Ejecuta:

```bash
php artisan db:seed
# o desde cero:
php artisan migrate:fresh --seed
```

El seeder del módulo piloto (`EcosistemaLaboralSeeder`) no se llama desde `DatabaseSeeder` porque queremos poder ejecutarlo de forma independiente para no sobreescribir datos si lo ejecutamos varias veces.

```bash
php artisan db:seed --class=EcosistemaLaboralSeeder
```

---

**Unidad anterior ←** [Unidad 0: Contexto y arquitectura EAC](./00_contexto_arquitectura_eac.md)

  **Siguiente capítulo →** [Verifica Situaciones de Competencia con Tinker](./01_10_verifica_tinker.md)

**Siguiente unidad →** [Unidad 2: Frontend: layout y vistas Blade para el marketplace](./02_frontend_blade.md)
