<?php

namespace Tests\Feature\Api\V1;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Role;
use App\Models\EcosistemaLaboral;
use App\Models\Matricula;
use App\Models\PerfilHabilitacion;
use App\Models\SituacionCompetencia;
use Illuminate\Testing\Fluent\AssertableJson;

class TeacherControllersTest extends TestCase
{
    use RefreshDatabase;

    public function test_progreso_requires_authentication()
    {
        $ecos = EcosistemaLaboral::factory()->create(['activo' => true]);

        $response = $this->getJson('/api/v1/docente/ecosistemas/' . $ecos->id . '/progreso');
        $response->assertStatus(401);
    }

    public function test_progreso_forbidden_for_user_without_docente_role()
    {
        $user = User::factory()->create();
        $ecos = EcosistemaLaboral::factory()->create(['activo' => true]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/docente/ecosistemas/' . $ecos->id . '/progreso');
        $response->assertStatus(403);
    }

    public function test_progreso_returns_progress_for_docente()
    {
        $docente = User::factory()->create();
        $role = Role::create(['name' => 'docente']);

        $ecos = EcosistemaLaboral::factory()->create(['activo' => true]);

        // Assign docente role to user for this ecosistema
        DB::table('user_roles')->insert([
            'user_id' => $docente->id,
            'role_id' => $role->id,
            'ecosistema_laboral_id' => $ecos->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Crear 2 estudiantes matriculados en el módulo del ecosistema
        $student1 = User::factory()->create();
        $student2 = User::factory()->create();

        Matricula::create(['estudiante_id' => $student1->id, 'modulo_id' => $ecos->modulo_id]);
        Matricula::create(['estudiante_id' => $student2->id, 'modulo_id' => $ecos->modulo_id]);

        // Crear 3 situaciones de competencia en el ecosistema
        SituacionCompetencia::factory()->count(3)->create(['ecosistema_laboral_id' => $ecos->id]);

        // Crear perfiles de habilitación (sin conquistas)
        PerfilHabilitacion::create(['estudiante_id' => $student1->id, 'ecosistema_laboral_id' => $ecos->id, 'calificacion_actual' => 0]);
        PerfilHabilitacion::create(['estudiante_id' => $student2->id, 'ecosistema_laboral_id' => $ecos->id, 'calificacion_actual' => 0]);

        Sanctum::actingAs($docente);

        $response = $this->getJson('/api/v1/docente/ecosistemas/' . $ecos->id . '/progreso');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'meta' => ['version', 'timestamp', 'ecosistema_id', 'total_scs', 'total_matriculados'],
            ]);

        $this->assertEquals($ecos->id, $response->json('meta.ecosistema_id'));
        $this->assertEquals(3, $response->json('meta.total_scs'));
        $this->assertEquals(2, $response->json('meta.total_matriculados'));

        // Verificar que cada elemento del data contiene estudiante_id y total_scs
        $response->assertJson(fn (AssertableJson $json) =>
            $json->has('data.0', fn (AssertableJson $json) =>
                $json->hasAll(['estudiante_id', 'conquistadas', 'total_scs', 'calificacion'])->etc()
            )->has('data.1')->etc()
        );
    }

    public function test_conquista_requires_authentication()
    {
        $ecos = EcosistemaLaboral::factory()->create(['activo' => true]);
        $response = $this->postJson('/api/v1/docente/ecosistemas/' . $ecos->id . '/conquistas', []);
        $response->assertStatus(401);
    }

    public function test_conquista_forbidden_for_user_without_docente_role()
    {
        $user = User::factory()->create();
        $ecos = EcosistemaLaboral::factory()->create(['activo' => true]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/docente/ecosistemas/' . $ecos->id . '/conquistas', []);
        $response->assertStatus(403);
    }

    public function test_conquista_returns_422_when_score_below_threshold()
    {
        $docente = User::factory()->create();
        $role = Role::create(['name' => 'docente']);

        $ecos = EcosistemaLaboral::factory()->create(['activo' => true]);

        DB::table('user_roles')->insert([
            'user_id' => $docente->id,
            'role_id' => $role->id,
            'ecosistema_laboral_id' => $ecos->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $student = User::factory()->create();
        // Matricular al estudiante
        Matricula::create(['estudiante_id' => $student->id, 'modulo_id' => $ecos->modulo_id]);
        PerfilHabilitacion::create(['estudiante_id' => $student->id, 'ecosistema_laboral_id' => $ecos->id, 'calificacion_actual' => 0]);

        // Crear una SC con umbral alto
        $sc = SituacionCompetencia::factory()->create(['ecosistema_laboral_id' => $ecos->id, 'umbral_maestria' => 90.00]);

        Sanctum::actingAs($docente);

        $payload = [
            'estudiante_id' => $student->id,
            'sc_codigo' => $sc->codigo,
            'gradiente_autonomia' => 'supervisado',
            'puntuacion_conquista' => 80.0, // por debajo del umbral
        ];

        $response = $this->postJson('/api/v1/docente/ecosistemas/' . $ecos->id . '/conquistas', $payload);
        $response->assertStatus(422)
            ->assertJson(fn (AssertableJson $json) =>
                $json->where('status', 422)->has('detail')->etc()
            );
    }

    public function test_conquista_returns_422_when_student_not_matriculated()
    {
        $docente = User::factory()->create();
        $role = Role::create(['name' => 'docente']);

        $ecos = EcosistemaLaboral::factory()->create(['activo' => true]);

        DB::table('user_roles')->insert([
            'user_id' => $docente->id,
            'role_id' => $role->id,
            'ecosistema_laboral_id' => $ecos->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $student = User::factory()->create();
        // Note: no matricula
        PerfilHabilitacion::create(['estudiante_id' => $student->id, 'ecosistema_laboral_id' => $ecos->id, 'calificacion_actual' => 0]);

        $sc = SituacionCompetencia::factory()->create(['ecosistema_laboral_id' => $ecos->id, 'umbral_maestria' => 50.00]);

        Sanctum::actingAs($docente);

        $payload = [
            'estudiante_id' => $student->id,
            'sc_codigo' => $sc->codigo,
            'gradiente_autonomia' => 'supervisado',
            'puntuacion_conquista' => 80.0,
        ];

        $response = $this->postJson('/api/v1/docente/ecosistemas/' . $ecos->id . '/conquistas', $payload);
        $response->assertStatus(422);
    }

    public function test_conquista_success_creates_pivot_and_updates_profile()
    {
        $docente = User::factory()->create();
        $role = Role::create(['name' => 'docente']);

        $ecos = EcosistemaLaboral::factory()->create(['activo' => true]);

        DB::table('user_roles')->insert([
            'user_id' => $docente->id,
            'role_id' => $role->id,
            'ecosistema_laboral_id' => $ecos->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $student = User::factory()->create();
        Matricula::create(['estudiante_id' => $student->id, 'modulo_id' => $ecos->modulo_id]);
        $perfil = PerfilHabilitacion::create(['estudiante_id' => $student->id, 'ecosistema_laboral_id' => $ecos->id, 'calificacion_actual' => 0]);

        $sc = SituacionCompetencia::factory()->create(['ecosistema_laboral_id' => $ecos->id, 'umbral_maestria' => 50.00]);

        Sanctum::actingAs($docente);

        $payload = [
            'estudiante_id' => $student->id,
            'sc_codigo' => $sc->codigo,
            'gradiente_autonomia' => 'autonomo',
            'puntuacion_conquista' => 85.5,
        ];

        $response = $this->postJson('/api/v1/docente/ecosistemas/' . $ecos->id . '/conquistas', $payload);

        $response->assertStatus(201)
            ->assertJson(fn (AssertableJson $json) =>
                $json->has('data', fn (AssertableJson $json) =>
                    $json->where('sc_codigo', $sc->codigo)
                         ->where('puntuacion_conquista', 85.5)
                         ->etc()
                )->has('meta')
            );

        // Verificar pivot 'perfil_situacion'
        $this->assertDatabaseHas('perfil_situacion', [
            'perfil_habilitacion_id' => $perfil->id,
            'situacion_competencia_id' => $sc->id,
            'puntuacion_conquista' => 85.5,
        ]);

        // Verificar que la calificación del perfil se ha actualizado (media ponderada simple -> 85.5)
        $this->assertDatabaseHas('perfiles_habilitacion', [
            'id' => $perfil->id,
            'calificacion_actual' => 85.50,
        ]);
    }
}
