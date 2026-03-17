## 5.9. Validación con tests

### 5.9.1 Tests de Huella de Talento

Los tres endpoints a cubrir son `GET huellas`, `GET huella` y `POST huella`. Para cada uno se verifican: autenticación requerida, acceso denegado sin perfil, y el comportamiento correcto.

#### Factories necesarios

Los tests de esta sección necesitan `HuellaTalento`. Crea su factory si no existe:

```bash
php artisan make:factory HuellaTalentoFactory --model=HuellaTalento
```

```php
// database/factories/HuellaTalentoFactory.php

namespace Database\Factories;

use App\Models\EcosistemaLaboral;
use App\Models\HuellaTalento;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class HuellaTalentoFactory extends Factory
{
    protected $model = HuellaTalento::class;

    public function definition(): array
    {
        $estudianteId  = User::factory()->create()->id;
        $ecosistemaId  = EcosistemaLaboral::factory()->create(['activo' => true])->id;

        return [
            'estudiante_id'         => $estudianteId,
            'ecosistema_laboral_id' => $ecosistemaId,
            'payload'               => [
                'ngsi_ld_id'              => "urn:ngsi-ld:PerfilHabilitacion:estudiante-{$estudianteId}-ecosistema-{$ecosistemaId}",
                'calificacion'            => 0.0,
                'situaciones_conquistadas' => [],
                'desglose_curricular'     => [],
                'version'                 => '1.0',
                'generada_en'             => now()->toIso8601String(),
            ],
            'ngsi_ld_id'   => null,
            'generada_en'  => now(),
        ];
    }
}
```

> Necesitarás asociar el trait `HasFactory` al modelo `HuellaTalento` para que funcione la factory.

---

#### Clase de test

```php
// tests/Feature/Api/V1/HuellaControllerTest.php

namespace Tests\Feature\Api\V1;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use App\Models\EcosistemaLaboral;
use App\Models\HuellaTalento;
use App\Models\Matricula;
use App\Models\PerfilHabilitacion;
use App\Models\SituacionCompetencia;
use App\Models\User;
use App\Services\CalificacionService;
use App\Services\HuellaService;
use Illuminate\Testing\Fluent\AssertableJson;

class HuellaControllerTest extends TestCase
{
    use RefreshDatabase;

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Crea el escenario mínimo: estudiante autenticado con perfil en un ecosistema.
     * Devuelve [$estudiante, $ecosistema, $perfil].
     */
    private function crearEscenarioBase(): array
    {
        $estudiante = User::factory()->create();
        $ecosistema = EcosistemaLaboral::factory()->create(['activo' => true]);

        Matricula::create([
            'estudiante_id' => $estudiante->id,
            'modulo_id'     => $ecosistema->modulo_id,
        ]);

        $perfil = PerfilHabilitacion::create([
            'estudiante_id'         => $estudiante->id,
            'ecosistema_laboral_id' => $ecosistema->id,
            'calificacion_actual'   => 0.00,
        ]);

        return [$estudiante, $ecosistema, $perfil];
    }

    private function urlHuella(int $ecosistemaId): string
    {
        return "/api/v1/estudiante/perfil/{$ecosistemaId}/huella";
    }

    private function urlHuellas(int $ecosistemaId): string
    {
        return "/api/v1/estudiante/perfil/{$ecosistemaId}/huellas";
    }

    // ─── GET huella ──────────────────────────────────────────────────────────

    public function test_get_huella_requires_authentication(): void
    {
        $ecosistema = EcosistemaLaboral::factory()->create(['activo' => true]);

        $this->getJson($this->urlHuella($ecosistema->id))
             ->assertStatus(401);
    }

    public function test_get_huella_returns_404_when_no_profile(): void
    {
        $estudiante = User::factory()->create();
        $ecosistema = EcosistemaLaboral::factory()->create(['activo' => true]);

        Sanctum::actingAs($estudiante);

        $this->getJson($this->urlHuella($ecosistema->id))
             ->assertStatus(404);
    }

    public function test_get_huella_generates_one_if_none_exists(): void
    {
        [$estudiante, $ecosistema, $perfil] = $this->crearEscenarioBase();

        // Mock del HuellaService para aislar el test del CalificacionService
        $this->mock(HuellaService::class, function ($mock) use ($perfil, $ecosistema) {
            $huella = HuellaTalento::factory()->make([
                'estudiante_id'         => $perfil->estudiante_id,
                'ecosistema_laboral_id' => $ecosistema->id,
                'id'                    => 1,
            ]);
            $mock->shouldReceive('ultimaOGenerar')
                 ->once()
                 ->andReturn($huella);
        });

        Sanctum::actingAs($estudiante);

        $this->getJson($this->urlHuella($ecosistema->id))
             ->assertStatus(200)
             ->assertJsonStructure([
                 'data',
                 'meta' => ['huella_id', 'generada_en', 'ngsi_ld_id', 'version', 'timestamp'],
             ]);
    }

    public function test_get_huella_returns_existing_huella(): void
    {
        [$estudiante, $ecosistema, $perfil] = $this->crearEscenarioBase();

        $huella = HuellaTalento::create([
            'estudiante_id'         => $estudiante->id,
            'ecosistema_laboral_id' => $ecosistema->id,
            'payload'               => [
                'ngsi_ld_id'              => "urn:ngsi-ld:PerfilHabilitacion:estudiante-{$estudiante->id}-ecosistema-{$ecosistema->id}",
                'calificacion'            => 0.0,
                'situaciones_conquistadas' => [],
                'desglose_curricular'     => [],
                'version'                 => '1.0',
                'generada_en'             => now()->toIso8601String(),
            ],
            'generada_en' => now()->subMinutes(5),
        ]);

        Sanctum::actingAs($estudiante);

        $response = $this->getJson($this->urlHuella($ecosistema->id));

        $response->assertStatus(200);
        $this->assertEquals($huella->id, $response->json('meta.huella_id'));
    }

    public function test_get_huella_payload_contains_ngsi_ld_id(): void
    {
        [$estudiante, $ecosistema, $perfil] = $this->crearEscenarioBase();

        $expectedUrn = "urn:ngsi-ld:PerfilHabilitacion:estudiante-{$estudiante->id}-ecosistema-{$ecosistema->id}";

        HuellaTalento::create([
            'estudiante_id'         => $estudiante->id,
            'ecosistema_laboral_id' => $ecosistema->id,
            'payload'               => [
                'ngsi_ld_id'  => $expectedUrn,
                'calificacion' => 0.0,
                'situaciones_conquistadas' => [],
                'desglose_curricular'      => [],
                'version'      => '1.0',
                'generada_en'  => now()->toIso8601String(),
            ],
            'generada_en' => now(),
        ]);

        Sanctum::actingAs($estudiante);

        $this->getJson($this->urlHuella($ecosistema->id))
             ->assertStatus(200)
             ->assertJson(fn (AssertableJson $json) =>
                 $json->where('data.ngsi_ld_id', $expectedUrn)->etc()
             );
    }

    // ─── POST huella ─────────────────────────────────────────────────────────

    public function test_post_huella_requires_authentication(): void
    {
        $ecosistema = EcosistemaLaboral::factory()->create(['activo' => true]);

        $this->postJson($this->urlHuella($ecosistema->id))
             ->assertStatus(401);
    }

    public function test_post_huella_returns_404_when_no_profile(): void
    {
        $estudiante = User::factory()->create();
        $ecosistema = EcosistemaLaboral::factory()->create(['activo' => true]);

        Sanctum::actingAs($estudiante);

        $this->postJson($this->urlHuella($ecosistema->id))
             ->assertStatus(404);
    }

    public function test_post_huella_creates_new_record_in_database(): void
    {
        [$estudiante, $ecosistema, $perfil] = $this->crearEscenarioBase();

        Sanctum::actingAs($estudiante);

        $this->assertDatabaseCount('huellas_talento', 0);

        $this->postJson($this->urlHuella($ecosistema->id))
             ->assertStatus(201)
             ->assertJsonStructure([
                 'data',
                 'meta' => ['huella_id', 'generada_en', 'version', 'timestamp'],
             ]);

        $this->assertDatabaseCount('huellas_talento', 1);
        $this->assertDatabaseHas('huellas_talento', [
            'estudiante_id'         => $estudiante->id,
            'ecosistema_laboral_id' => $ecosistema->id,
        ]);
    }

    public function test_post_huella_successive_calls_create_independent_snapshots(): void
    {
        [$estudiante, $ecosistema, $perfil] = $this->crearEscenarioBase();

        Sanctum::actingAs($estudiante);

        $this->postJson($this->urlHuella($ecosistema->id))->assertStatus(201);
        $this->postJson($this->urlHuella($ecosistema->id))->assertStatus(201);

        // Cada POST genera una fila independiente en la tabla
        $this->assertDatabaseCount('huellas_talento', 2);
    }

    public function test_post_huella_payload_reflects_conquered_scs(): void
    {
        [$estudiante, $ecosistema, $perfil] = $this->crearEscenarioBase();

        // Registrar una conquista en el perfil
        $sc = SituacionCompetencia::factory()->create([
            'ecosistema_laboral_id' => $ecosistema->id,
            'umbral_maestria'       => 50.00,
        ]);

        $perfil->situacionesConquistadas()->attach($sc->id, [
            'gradiente_autonomia'   => 'autonomo',
            'puntuacion_conquista'  => 90.0,
            'intentos'              => 1,
            'fecha_conquista'       => now(),
        ]);

        Sanctum::actingAs($estudiante);

        $response = $this->postJson($this->urlHuella($ecosistema->id));

        $response->assertStatus(201);

        $conquistadas = $response->json('data.situaciones_conquistadas');
        $this->assertCount(1, $conquistadas);
        $this->assertEquals($sc->codigo, $conquistadas[0]['codigo']);
        $this->assertEquals('autonomo', $conquistadas[0]['gradiente_autonomia']);
        $this->assertEquals(90.0, $conquistadas[0]['puntuacion_conquista']);
        // puntuacion_efectiva = 90.0 × 1.00 (autónomo)
        $this->assertEquals(90.0, $conquistadas[0]['puntuacion_efectiva']);
    }

    public function test_post_huella_puntuacion_efectiva_scaled_by_gradiente(): void
    {
        [$estudiante, $ecosistema, $perfil] = $this->crearEscenarioBase();

        $sc = SituacionCompetencia::factory()->create([
            'ecosistema_laboral_id' => $ecosistema->id,
            'umbral_maestria'       => 50.00,
        ]);

        $perfil->situacionesConquistadas()->attach($sc->id, [
            'gradiente_autonomia'  => 'supervisado',   // factor 0.90
            'puntuacion_conquista' => 100.0,
            'intentos'             => 1,
            'fecha_conquista'      => now(),
        ]);

        Sanctum::actingAs($estudiante);

        $response = $this->postJson($this->urlHuella($ecosistema->id));

        $response->assertStatus(201);

        $efectiva = $response->json('data.situaciones_conquistadas.0.puntuacion_efectiva');
        $this->assertEquals(90.0, $efectiva); // 100 × 0.90
    }

    // ─── GET huellas (historial) ──────────────────────────────────────────────

    public function test_get_huellas_requires_authentication(): void
    {
        $ecosistema = EcosistemaLaboral::factory()->create(['activo' => true]);

        $this->getJson($this->urlHuellas($ecosistema->id))
             ->assertStatus(401);
    }

    public function test_get_huellas_returns_404_when_no_profile(): void
    {
        $estudiante = User::factory()->create();
        $ecosistema = EcosistemaLaboral::factory()->create(['activo' => true]);

        Sanctum::actingAs($estudiante);

        $this->getJson($this->urlHuellas($ecosistema->id))
             ->assertStatus(404);
    }

    public function test_get_huellas_returns_empty_list_when_no_huellas(): void
    {
        [$estudiante, $ecosistema, $perfil] = $this->crearEscenarioBase();

        Sanctum::actingAs($estudiante);

        $this->getJson($this->urlHuellas($ecosistema->id))
             ->assertStatus(200)
             ->assertJson(fn (AssertableJson $json) =>
                 $json->where('meta.total', 0)
                      ->where('data', [])
                      ->etc()
             );
    }

    public function test_get_huellas_returns_all_huellas_ordered_by_date(): void
    {
        [$estudiante, $ecosistema, $perfil] = $this->crearEscenarioBase();

        $payloadBase = [
            'ngsi_ld_id'              => 'urn:ngsi-ld:PerfilHabilitacion:test',
            'calificacion'            => 0.0,
            'situaciones_conquistadas' => [],
            'desglose_curricular'     => [],
            'version'                 => '1.0',
            'generada_en'             => now()->toIso8601String(),
        ];

        // Crear dos huellas en momentos distintos
        HuellaTalento::create([
            'estudiante_id'         => $estudiante->id,
            'ecosistema_laboral_id' => $ecosistema->id,
            'payload'               => $payloadBase,
            'generada_en'           => now()->subHour(),
        ]);

        HuellaTalento::create([
            'estudiante_id'         => $estudiante->id,
            'ecosistema_laboral_id' => $ecosistema->id,
            'payload'               => $payloadBase,
            'generada_en'           => now(),
        ]);

        Sanctum::actingAs($estudiante);

        $response = $this->getJson($this->urlHuellas($ecosistema->id));

        $response->assertStatus(200)
                 ->assertJson(fn (AssertableJson $json) =>
                     $json->where('meta.total', 2)
                          ->has('data', 2, fn (AssertableJson $json) =>
                              $json->hasAll(['id', 'generada_en', 'ngsi_ld_id', 'links'])->etc()
                          )
                          ->etc()
                 );

        // La más reciente debe ser la primera
        $fechas = $response->json('data.*.generada_en');
        $this->assertGreaterThanOrEqual($fechas[1], $fechas[0]);
    }

    public function test_get_huellas_only_returns_own_huellas(): void
    {
        [$estudiante, $ecosistema, $perfil] = $this->crearEscenarioBase();

        // Otro estudiante con sus propias huellas en el mismo ecosistema
        $otroEstudiante = User::factory()->create();
        PerfilHabilitacion::create([
            'estudiante_id'         => $otroEstudiante->id,
            'ecosistema_laboral_id' => $ecosistema->id,
            'calificacion_actual'   => 0.00,
        ]);

        $payloadBase = [
            'ngsi_ld_id'              => 'urn:ngsi-ld:PerfilHabilitacion:otro',
            'calificacion'            => 0.0,
            'situaciones_conquistadas' => [],
            'desglose_curricular'     => [],
            'version'                 => '1.0',
            'generada_en'             => now()->toIso8601String(),
        ];

        HuellaTalento::create([
            'estudiante_id'         => $otroEstudiante->id,
            'ecosistema_laboral_id' => $ecosistema->id,
            'payload'               => $payloadBase,
            'generada_en'           => now(),
        ]);

        Sanctum::actingAs($estudiante);

        $response = $this->getJson($this->urlHuellas($ecosistema->id));

        $response->assertStatus(200)
                 ->assertJson(fn (AssertableJson $json) =>
                     $json->where('meta.total', 0)->etc()
                 );
    }
}
```

---

### 5.9.2. Tests de calificación para el docente.

Los tests relativos al `CalificacionController` cubren cuatro capas distintas:

* **Autorización:** sin token (401), con token pero sin rol (403), y con rol docente pero en un ecosistema distinto (403 también, que es el caso que más fácilmente se escapa en el desarrollo).
* **Estructura:** que la respuesta incluye `desglose_ra` y `criterios` correctamente anidados.
* **Valor cero:** sin conquistas todo es `cubierto: false` y `calificacion_total: 0.0`.
* **Lógica de ponderación:** los tres últimos tests verifican el factor de gradiente (`autonomo` no reduce, `supervisado` multiplica por 0.90) y la ponderación por `peso_en_sc` cuando dos SCs cubren el mismo CE, con el cálculo explícito en el comentario para que el alumno pueda verificarlo a mano.

```php
// tests/Feature/Api/V1/DocenteCalificacionControllerTest.php

namespace Tests\Feature\Api\V1;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Facades\DB;
use App\Models\EcosistemaLaboral;
use App\Models\Matricula;
use App\Models\PerfilHabilitacion;
use App\Models\ResultadoAprendizaje;
use App\Models\CriterioEvaluacion;
use App\Models\Role;
use App\Models\SituacionCompetencia;
use App\Models\User;
use Illuminate\Testing\Fluent\AssertableJson;

class DocenteCalificacionControllerTest extends TestCase
{
    use RefreshDatabase;

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function urlCalificacion(int $ecosistemaId, int $estudianteId): string
    {
        return "/api/v1/docente/ecosistemas/{$ecosistemaId}/calificacion/{$estudianteId}";
    }

    /**
     * Crea docente con rol asignado al ecosistema.
     */
    private function crearDocente(EcosistemaLaboral $ecosistema): User
    {
        $docente = User::factory()->create();
        $rol     = Role::firstOrCreate(['name' => 'docente']);

        DB::table('user_roles')->insert([
            'user_id'               => $docente->id,
            'role_id'               => $rol->id,
            'ecosistema_laboral_id' => $ecosistema->id,
            'created_at'            => now(),
            'updated_at'            => now(),
        ]);

        return $docente;
    }

    /**
     * Crea estudiante matriculado con perfil en el ecosistema.
     */
    private function crearEstudiante(EcosistemaLaboral $ecosistema): array
    {
        $estudiante = User::factory()->create();

        Matricula::create([
            'estudiante_id' => $estudiante->id,
            'modulo_id'     => $ecosistema->modulo_id,
        ]);

        $perfil = PerfilHabilitacion::create([
            'estudiante_id'         => $estudiante->id,
            'ecosistema_laboral_id' => $ecosistema->id,
            'calificacion_actual'   => 0.00,
        ]);

        return [$estudiante, $perfil];
    }

    // ─── Autenticación y autorización ────────────────────────────────────────

    public function test_calificacion_requires_authentication(): void
    {
        $ecosistema = EcosistemaLaboral::factory()->create(['activo' => true]);
        $estudiante = User::factory()->create();

        $this->getJson($this->urlCalificacion($ecosistema->id, $estudiante->id))
             ->assertStatus(401);
    }

    public function test_calificacion_forbidden_for_user_without_docente_role(): void
    {
        $usuario    = User::factory()->create();
        $ecosistema = EcosistemaLaboral::factory()->create(['activo' => true]);
        $estudiante = User::factory()->create();

        Sanctum::actingAs($usuario);

        $this->getJson($this->urlCalificacion($ecosistema->id, $estudiante->id))
             ->assertStatus(403);
    }

    public function test_calificacion_forbidden_for_docente_of_different_ecosistema(): void
    {
        $ecosistemaA = EcosistemaLaboral::factory()->create(['activo' => true]);
        $ecosistemaB = EcosistemaLaboral::factory()->create(['activo' => true]);

        // Docente solo tiene rol en ecosistema A
        $docente = $this->crearDocente($ecosistemaA);
        [$estudiante] = $this->crearEstudiante($ecosistemaB);

        Sanctum::actingAs($docente);

        // Intenta acceder al ecosistema B
        $this->getJson($this->urlCalificacion($ecosistemaB->id, $estudiante->id))
             ->assertStatus(403);
    }

    // ─── Respuesta 404 ───────────────────────────────────────────────────────

    public function test_calificacion_returns_404_when_student_has_no_profile(): void
    {
        $ecosistema = EcosistemaLaboral::factory()->create(['activo' => true]);
        $docente    = $this->crearDocente($ecosistema);
        $estudiante = User::factory()->create(); // sin perfil

        Sanctum::actingAs($docente);

        $this->getJson($this->urlCalificacion($ecosistema->id, $estudiante->id))
             ->assertStatus(404);
    }

    // ─── Estructura de la respuesta ───────────────────────────────────────────

    public function test_calificacion_returns_correct_structure(): void
    {
        $ecosistema = EcosistemaLaboral::factory()->create(['activo' => true]);
        $docente    = $this->crearDocente($ecosistema);
        [$estudiante] = $this->crearEstudiante($ecosistema);

        Sanctum::actingAs($docente);

        $this->getJson($this->urlCalificacion($ecosistema->id, $estudiante->id))
             ->assertStatus(200)
             ->assertJsonStructure([
                 'data' => [
                     'estudiante_id',
                     'ecosistema_id',
                     'calificacion_total',
                     'desglose_ra',
                 ],
                 'meta' => ['version', 'timestamp'],
             ]);
    }

    public function test_calificacion_total_is_zero_when_no_conquistas(): void
    {
        $ecosistema = EcosistemaLaboral::factory()->create(['activo' => true]);
        $docente    = $this->crearDocente($ecosistema);
        [$estudiante] = $this->crearEstudiante($ecosistema);

        Sanctum::actingAs($docente);

        $response = $this->getJson($this->urlCalificacion($ecosistema->id, $estudiante->id));

        $response->assertStatus(200)
                 ->assertJson(fn (AssertableJson $json) =>
                     $json->where('data.calificacion_total', 0)
                          ->where('data.estudiante_id', $estudiante->id)
                          ->where('data.ecosistema_id', $ecosistema->id)
                          ->etc()
                 );
    }

    // ─── Lógica de calificación ───────────────────────────────────────────────

    public function test_calificacion_desglose_ra_contains_all_ra_of_modulo(): void
    {
        $ecosistema = EcosistemaLaboral::factory()->create(['activo' => true]);
        $docente    = $this->crearDocente($ecosistema);
        [$estudiante, $perfil] = $this->crearEstudiante($ecosistema);

        // Crear 2 RA en el módulo del ecosistema
        ResultadoAprendizaje::factory()->count(2)->create([
            'modulo_id' => $ecosistema->modulo_id,
        ]);

        Sanctum::actingAs($docente);

        $response = $this->getJson($this->urlCalificacion($ecosistema->id, $estudiante->id));

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data.desglose_ra'));
    }

    public function test_calificacion_desglose_ce_marked_as_not_covered_without_conquistas(): void
    {
        $ecosistema = EcosistemaLaboral::factory()->create(['activo' => true]);
        $docente    = $this->crearDocente($ecosistema);
        [$estudiante] = $this->crearEstudiante($ecosistema);

        $ra = ResultadoAprendizaje::factory()->create([
            'modulo_id'       => $ecosistema->modulo_id,
        ]);

        CriterioEvaluacion::factory()->count(2)->create([
            'resultado_aprendizaje_id' => $ra->id,
        ]);

        Sanctum::actingAs($docente);

        $response = $this->getJson($this->urlCalificacion($ecosistema->id, $estudiante->id));

        $response->assertStatus(200);

        $criterios = $response->json('data.desglose_ra.0.criterios');
        $this->assertCount(2, $criterios);
        $this->assertFalse($criterios[0]['cubierto']);
        $this->assertFalse($criterios[1]['cubierto']);
        $this->assertEquals(0.0, $criterios[0]['puntuacion']);
    }

    public function test_calificacion_gradiente_autonomo_does_not_reduce_score(): void
    {
        $ecosistema = EcosistemaLaboral::factory()->create(['activo' => true]);
        $docente    = $this->crearDocente($ecosistema);
        [$estudiante, $perfil] = $this->crearEstudiante($ecosistema);

        $ra = ResultadoAprendizaje::factory()->create([
            'modulo_id'       => $ecosistema->modulo_id,
        ]);

        $ce = CriterioEvaluacion::factory()->create([
            'resultado_aprendizaje_id' => $ra->id,
        ]);

        $sc = SituacionCompetencia::factory()->create([
            'ecosistema_laboral_id' => $ecosistema->id,
            'umbral_maestria'       => 50.00,
        ]);

        $sc->criteriosEvaluacion()->attach($ce->id, ['peso_en_sc' => 100]);

        // Gradiente autónomo: factor 1.00 → puntuacion_efectiva = puntuacion_conquista
        $perfil->situacionesConquistadas()->attach($sc->id, [
            'gradiente_autonomia'  => 'autonomo',
            'puntuacion_conquista' => 80.0,
            'intentos'             => 1,
            'fecha_conquista'      => now(),
        ]);

        Sanctum::actingAs($docente);

        $response = $this->getJson($this->urlCalificacion($ecosistema->id, $estudiante->id));

        $response->assertStatus(200);

        $ce0 = $response->json('data.desglose_ra.0.criterios.0');
        $this->assertTrue($ce0['cubierto']);
        $this->assertEquals(80.0, $ce0['puntuacion']); // sin reducción
    }

    public function test_calificacion_gradiente_supervisado_reduces_score(): void
    {
        $ecosistema = EcosistemaLaboral::factory()->create(['activo' => true]);
        $docente    = $this->crearDocente($ecosistema);
        [$estudiante, $perfil] = $this->crearEstudiante($ecosistema);

        $ra = ResultadoAprendizaje::factory()->create([
            'modulo_id'       => $ecosistema->modulo_id,
        ]);

        $ce = CriterioEvaluacion::factory()->create([
            'resultado_aprendizaje_id' => $ra->id,
        ]);

        $sc = SituacionCompetencia::factory()->create([
            'ecosistema_laboral_id' => $ecosistema->id,
            'umbral_maestria'       => 50.00,
        ]);

        $sc->criteriosEvaluacion()->attach($ce->id, ['peso_en_sc' => 100]);

        // Gradiente supervisado: factor 0.90 → puntuacion_efectiva = 100 × 0.90 = 90
        $perfil->situacionesConquistadas()->attach($sc->id, [
            'gradiente_autonomia'  => 'supervisado',
            'puntuacion_conquista' => 100.0,
            'intentos'             => 1,
            'fecha_conquista'      => now(),
        ]);

        Sanctum::actingAs($docente);

        $response = $this->getJson($this->urlCalificacion($ecosistema->id, $estudiante->id));

        $response->assertStatus(200);

        $puntuacionCe = $response->json('data.desglose_ra.0.criterios.0.puntuacion');
        $this->assertEquals(90.0, $puntuacionCe); // 100 × 0.90
    }

    public function test_calificacion_ce_cubierto_by_multiple_scs_is_weighted(): void
    {
        $ecosistema = EcosistemaLaboral::factory()->create(['activo' => true]);
        $docente    = $this->crearDocente($ecosistema);
        [$estudiante, $perfil] = $this->crearEstudiante($ecosistema);

        $ra = ResultadoAprendizaje::factory()->create([
            'modulo_id'       => $ecosistema->modulo_id,
        ]);

        $ce = CriterioEvaluacion::factory()->create([
            'resultado_aprendizaje_id' => $ra->id,
        ]);

        // Dos SCs cubren el mismo CE con pesos distintos
        $sc1 = SituacionCompetencia::factory()->create([
            'ecosistema_laboral_id' => $ecosistema->id,
            'umbral_maestria'       => 50.00,
        ]);
        $sc2 = SituacionCompetencia::factory()->create([
            'ecosistema_laboral_id' => $ecosistema->id,
            'umbral_maestria'       => 50.00,
        ]);

        $sc1->criteriosEvaluacion()->attach($ce->id, ['peso_en_sc' => 40]);
        $sc2->criteriosEvaluacion()->attach($ce->id, ['peso_en_sc' => 60]);

        // SC1: autónomo, 60.0 → efectiva = 60.0
        // SC2: autónomo, 90.0 → efectiva = 90.0
        // CE ponderado = (60×40 + 90×60) / (40+60) = (2400+5400)/100 = 78.0
        $perfil->situacionesConquistadas()->attach($sc1->id, [
            'gradiente_autonomia'  => 'autonomo',
            'puntuacion_conquista' => 60.0,
            'intentos'             => 1,
            'fecha_conquista'      => now(),
        ]);
        $perfil->situacionesConquistadas()->attach($sc2->id, [
            'gradiente_autonomia'  => 'autonomo',
            'puntuacion_conquista' => 90.0,
            'intentos'             => 1,
            'fecha_conquista'      => now(),
        ]);

        Sanctum::actingAs($docente);

        $response = $this->getJson($this->urlCalificacion($ecosistema->id, $estudiante->id));

        $response->assertStatus(200);

        $puntuacionCe = $response->json('data.desglose_ra.0.criterios.0.puntuacion');
        $this->assertEquals(78.0, $puntuacionCe);
    }
}
```

También necesitarás los factories de `ResultadoAprendizaje` y `CriterioEvaluacion` si no existen:

```bash
php artisan make:factory ResultadoAprendizajeFactory --model=ResultadoAprendizaje
php artisan make:factory CriterioEvaluacionFactory --model=CriterioEvaluacion
```

> Recuerda asociar el trait `HasFactory` a ambos modelos para que las factories funcionen correctamente.

```php
// database/factories/ResultadoAprendizajeFactory.php

class ResultadoAprendizajeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'modulo_id'       => \App\Models\Modulo::factory(),
            'codigo'          => 'RA' . $this->faker->unique()->numberBetween(1, 99),
            'descripcion'     => $this->faker->sentence(),
            // 'peso_porcentaje' => $this->faker->randomElement([25, 30, 35, 40]),
            // 'orden'           => $this->faker->numberBetween(1, 10),
        ];
    }
}
```

```php
// database/factories/CriterioEvaluacionFactory.php

class CriterioEvaluacionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'resultado_aprendizaje_id' => \App\Models\ResultadoAprendizaje::factory(),
            'codigo'                   => 'CE' . $this->faker->unique()->bothify('#?'),
            'descripcion'              => $this->faker->sentence(),
            // 'peso_porcentaje'          => $this->faker->randomElement([20, 25, 30, 50]),
            // 'orden'                    => $this->faker->numberBetween(1, 10),
        ];
    }
}
```

Finalmente, para ejecutar los tests puedes usar el siguiente comando:

```bash
php artisan test --filter=HuellaControllerTest
php artisan test --filter=DocenteCalificacionControllerTest
```

> Si quieres ejecutar todos los tests de la aplicación, simplemente ejecuta `php artisan test` sin filtros.

---

**Unidad anterior ←** [Unidad 4: Motor de navegación: ZDP y recomendación](./04_motor_navegacion.md)

**Siguiente capítulo →** [Unidad 5.10: Verificación final](./05_10_verificacion_final.md)

**Siguiente unidad →** [Unidad 6: Visualización del espacio competencial](./06_visualizacion_espacio_competencial.md)
