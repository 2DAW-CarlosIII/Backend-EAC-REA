## 5.9. Tests de Huella de Talento

Los tres endpoints a cubrir son `GET huellas`, `GET huella` y `POST huella`. Para cada uno se verifican: autenticación requerida, acceso denegado sin perfil, y el comportamiento correcto.

### Factories necesarios

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

---

### Clase de test

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

Tres decisiones de diseño que vale la pena señalar:

**Mock de `HuellaService` en `test_get_huella_generates_one_if_none_exists`.** Ese test cubre únicamente la responsabilidad del controlador (delegar en el servicio y devolver la respuesta), sin entrar en la lógica de calificación ponderada. Los tests de `CalificacionService` y `HuellaService` en aislamiento pertenecen a `tests/Unit`, no a `Feature`. El resto de los tests de esta sección sí usan el servicio real porque validan el `payload` resultante.

**`test_post_huella_successive_calls_create_independent_snapshots`.** Verifica que `POST` nunca es idempotente: cada llamada es un snapshot nuevo. Esto es distinto de `GET`, que devuelve la última sin crear una nueva.

**`test_get_huellas_only_returns_own_huellas`.** Un estudiante nunca debe ver el historial de huellas de otro aunque compartan ecosistema. La query filtra por `estudiante_id = auth()->id()`, y este test lo confirma explícitamente.

---

**Unidad anterior ←** [Unidad 4: Motor de navegación: ZDP y recomendación](./04_motor_navegacion.md)

**Siguiente capítulo →** [Unidad 5.10: Verificación final](./05_10_verificacion_final.md)

**Siguiente unidad →** [Unidad 6: Visualización del espacio competencial](./06_visualizacion_espacio_competencial.md)
