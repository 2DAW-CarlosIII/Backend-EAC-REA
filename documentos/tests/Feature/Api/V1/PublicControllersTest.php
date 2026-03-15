<?php

namespace Tests\Feature\Api\V1;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Fluent\AssertableJson;
use App\Models\EcosistemaLaboral;
use App\Models\SituacionCompetencia;

class PublicControllersTest extends TestCase
{
    use RefreshDatabase;

    public function test_modulos_index_returns_paginated_collection()
    {
        // Crear varios ecosistemas activos (las fábricas deben crear también el módulo relacionado)
        EcosistemaLaboral::factory()->count(3)->create(['activo' => true]);

        $response = $this->getJson('/api/v1/modulos');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'meta' => ['version', 'timestamp', 'total', 'per_page', 'page'],
            ]);

        // Verificar que el primer elemento tiene las claves esperadas del recurso Modulo
        $response->assertJson(fn (AssertableJson $json) =>
            $json->has('data.0', fn (AssertableJson $json) =>
                $json->whereType('id', 'integer')
                     ->whereType('codigo', 'string')
                     ->whereType('nombre', 'string')
                     ->has('ciclo_formativo.id')
                     ->has('links.self')
                     ->etc()
            )->etc()
        );

        // Comprobación adicional sobre meta.total (debe ser al menos 3 módulos)
        $this->assertGreaterThanOrEqual(3, $response->json('meta.total'));
    }

    public function test_modulos_index_search_filters_results()
    {
        // Crear un ecosistema y forzar el nombre del módulo para la búsqueda
        $ecosistema = EcosistemaLaboral::factory()->create(['activo' => true]);

        // Asegurarnos de que existe la relación modulo y actualizar su nombre
        $modulo = $ecosistema->modulo;
        $modulo->update(['nombre' => 'Modulo Especial Busqueda']);

        $response = $this->getJson('/api/v1/modulos?buscar=Especial');

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertNotEmpty($data, 'La búsqueda debería devolver al menos un módulo');

        // Verificar que alguno de los resultados contiene el texto buscado en el nombre
        $found = false;
        foreach ($data as $item) {
            if (str_contains($item['nombre'] ?? '', 'Especial')) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Se esperaba encontrar el módulo con nombre que contiene "Especial"');
    }

    public function test_modulos_show_returns_full_resource()
    {
        $ecosistema = EcosistemaLaboral::factory()->create(['activo' => true]);
        $modulo = $ecosistema->modulo;

        $response = $this->getJson('/api/v1/modulos/' . $modulo->id);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id', 'codigo', 'nombre', 'descripcion', 'horas_totales',
                    'ciclo_formativo' => ['id', 'codigo', 'nombre', 'grado', 'familia_profesional'],
                    'resultados_aprendizaje',
                    'links' => ['self', 'ecosistema'],
                ],
            ]);

        // Verificar que el id devuelto coincide
        $this->assertEquals($modulo->id, $response->json('data.id'));
    }

    public function test_ecosistemas_situaciones_returns_flat_list_with_meta()
    {
        // Crear un ecosistema con 3 situaciones de competencia usando la relación factory
        $ecosistema = EcosistemaLaboral::factory()
            ->has(SituacionCompetencia::factory()->count(3), 'situacionesCompetencia')
            ->create(['activo' => true]);

        $response = $this->getJson('/api/v1/ecosistemas/' . $ecosistema->id . '/situaciones');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'meta' => ['version', 'timestamp', 'ecosistema_id', 'total'],
            ]);

        // Comprobar meta.ecosistema_id y total
        $this->assertEquals($ecosistema->id, $response->json('meta.ecosistema_id'));
        $this->assertEquals(3, $response->json('meta.total'));

        // Verificar estructura de la primera situación
        $response->assertJson(fn (AssertableJson $json) =>
            $json->has('data', 3)
                ->has('data.0', fn (AssertableJson $json) =>
                    $json->hasAll(['id', 'codigo', 'titulo', 'descripcion', 'umbral_maestria', 'nivel_complejidad'])
                    ->where('activa', true)
                    ->etc()
                )
                ->etc()
        );
    }
}
