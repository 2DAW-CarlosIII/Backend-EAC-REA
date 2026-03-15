<?php

namespace Tests\Feature\Api\V1;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use App\Models\User;
use App\Models\Role;
use App\Models\Modulo;
use App\Models\EcosistemaLaboral;
use App\Models\Matricula;
use App\Models\PerfilHabilitacion;
use Illuminate\Testing\Fluent\AssertableJson;

class StudentControllersTest extends TestCase
{
    use RefreshDatabase;

    public function test_estudiante_perfil_index_requires_authentication()
    {
        $response = $this->getJson('/api/v1/estudiante/perfil');
        $response->assertStatus(401);
    }

    public function test_estudiante_perfil_index_returns_profiles_for_authenticated_student()
    {
        $user = User::factory()->create();

        // Crear dos ecosistemas y perfiles asociados al usuario
        $ecos1 = EcosistemaLaboral::factory()->create(['activo' => true]);
        $ecos2 = EcosistemaLaboral::factory()->create(['activo' => true]);

        PerfilHabilitacion::create([
            'estudiante_id' => $user->id,
            'ecosistema_laboral_id' => $ecos1->id,
            'calificacion_actual' => 8.50,
        ]);

        PerfilHabilitacion::create([
            'estudiante_id' => $user->id,
            'ecosistema_laboral_id' => $ecos2->id,
            'calificacion_actual' => 7.00,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/estudiante/perfil');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'meta' => ['version', 'timestamp'],
            ]);

        $response->assertJson(fn (AssertableJson $json) =>
            $json->has('data.0', fn (AssertableJson $json) =>
                $json->hasAll(['id', 'ecosistema', 'calificacion_actual'])
                ->etc()
            )->etc()
        );

        $this->assertCount(2, $response->json('data'));
    }

    public function test_estudiante_perfil_show_returns_404_when_no_profile()
    {
        $user = User::factory()->create();
        $ecos = EcosistemaLaboral::factory()->create(['activo' => true]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/estudiante/perfil/' . $ecos->id);

        $response->assertStatus(404)
            ->assertJson(fn (AssertableJson $json) =>
                $json->where('status', 404)->has('detail')->etc()
            );
    }

    public function test_estudiante_perfil_show_returns_profile_when_exists()
    {
        $user = User::factory()->create();
        $ecos = EcosistemaLaboral::factory()->create(['activo' => true]);

        $perfil = PerfilHabilitacion::create([
            'estudiante_id' => $user->id,
            'ecosistema_laboral_id' => $ecos->id,
            'calificacion_actual' => 9.25,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/estudiante/perfil/' . $ecos->id);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['id', 'estudiante', 'ecosistema', 'calificacion_actual'],
            ]);

        $this->assertEquals($perfil->id, $response->json('data.id'));
    }

    public function test_matricula_requires_authentication()
    {
        $modulo = Modulo::factory()->create();
        $response = $this->postJson('/api/v1/estudiante/matriculas', ['modulo_id' => $modulo->id]);
        $response->assertStatus(401);
    }

    public function test_matricula_creates_matricula_perfil_and_assigns_role()
    {
        $user = User::factory()->create();

        // Asegurar que existe el role estudiante
        $role = Role::create(['name' => 'estudiante']);

        // Crear módulo con ecosistema activo
        $ecos = EcosistemaLaboral::factory()->create(['activo' => true]);
        $modulo = $ecos->modulo;

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/estudiante/matriculas', ['modulo_id' => $modulo->id]);

        $response->assertStatus(201)
            ->assertJson(fn (AssertableJson $json) =>
                $json->has('data', fn (AssertableJson $json) =>
                    $json->where('modulo_id', $modulo->id)
                         ->where('ecosistema_id', $ecos->id)
                         ->etc()
                )->has('meta')
                ->etc()
            );

        // Comprobar en BD la matrícula
        $this->assertDatabaseHas('matriculas', [
            'estudiante_id' => $user->id,
            'modulo_id' => $modulo->id,
        ]);

        // Comprobar perfil de habilitación
        $this->assertDatabaseHas('perfiles_habilitacion', [
            'estudiante_id' => $user->id,
            'ecosistema_laboral_id' => $ecos->id,
        ]);

        // Comprobar asignación de rol en la tabla user_roles
        $this->assertDatabaseHas('user_roles', [
            'user_id' => $user->id,
            'role_id' => $role->id,
            'ecosistema_laboral_id' => $ecos->id,
        ]);
    }

    public function test_matricula_duplicate_returns_409()
    {
        $user = User::factory()->create();
        $ecos = EcosistemaLaboral::factory()->create(['activo' => true]);
        $modulo = $ecos->modulo;

        // Crear matrícula previa
        Matricula::create([
            'estudiante_id' => $user->id,
            'modulo_id' => $modulo->id,
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/estudiante/matriculas', ['modulo_id' => $modulo->id]);

        $response->assertStatus(409)
            ->assertJson(fn (AssertableJson $json) =>
                $json->where('status', 409)->has('detail')->etc()
            );
    }

    public function test_matricula_without_active_ecosistema_returns_422()
    {
        $user = User::factory()->create();

        // Crear módulo con ecosistema inactivo
        $ecos = EcosistemaLaboral::factory()->create(['activo' => false]);
        $modulo = $ecos->modulo;

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/estudiante/matriculas', ['modulo_id' => $modulo->id]);

        $response->assertStatus(422)
            ->assertJson(fn (AssertableJson $json) =>
                $json->where('status', 422)->has('detail')->etc()
            );
    }
}
