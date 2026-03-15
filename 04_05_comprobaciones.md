## 4.5. Verificación con `curl`

```bash
# Estado inicial del estudiante (sin conquistas)
curl -s http://backend-eac.test/api/v1/estudiante/perfil/1/zdp \
  -H "Authorization: Bearer TU_TOKEN" | jq .

# Respuesta esperada:
# - zdp: [SC-01, SC-02]   ← sin prerequisitos, accesibles
# - bloqueadas: [SC-03]   ← requiere SC-01 y SC-02
# - recomendacion: SC-01  ← menor complejidad
# - completado: false
```

```bash
# Simular conquista de SC-01 (como docente) y volver a consultar la ZDP
curl -s -X POST http://backend-eac.test/api/v1/docente/ecosistemas/1/conquistas \
  -H "Authorization: Bearer TOKEN_DOCENTE" \
  -H "Content-Type: application/json" \
  -d '{"estudiante_id":2,"sc_codigo":"SC-01","gradiente_autonomia":"supervisado","puntuacion_conquista":84.5}'

curl -s http://backend-eac.test/api/v1/estudiante/perfil/1/zdp \
  -H "Authorization: Bearer TU_TOKEN" | jq '{
      zdp: [.data.zdp[].codigo],
      bloqueadas: [.data.bloqueadas[].codigo],
      recomendacion: .data.recomendacion.codigo
  }'

# Respuesta esperada:
# {
#   "zdp": ["SC-02"],
#   "bloqueadas": ["SC-03"],
#   "recomendacion": "SC-02"
# }
```

```bash
# Conquistar SC-02 y verificar que SC-03 pasa a la ZDP
curl -s -X POST http://backend-eac.test/api/v1/docente/ecosistemas/1/conquistas \
  -H "Authorization: Bearer TOKEN_DOCENTE" \
  -H "Content-Type: application/json" \
  -d '{"estudiante_id":2,"sc_codigo":"SC-02","gradiente_autonomia":"autonomo","puntuacion_conquista":91.0}'

curl -s http://backend-eac.test/api/v1/estudiante/perfil/1/zdp \
  -H "Authorization: Bearer TU_TOKEN" | jq '{
      zdp: [.data.zdp[].codigo],
      bloqueadas: [.data.bloqueadas[].codigo],
      completado: .data.completado
  }'

# Respuesta esperada:
# {
#   "zdp": ["SC-03"],
#   "bloqueadas": [],
#   "completado": false
# }
```

### Verificación del `GrafoService` en Tinker

```bash
php artisan tinker
```

```php
$ecosistema = App\Models\EcosistemaLaboral::first();
$grafo = app(App\Services\GrafoService::class);

// Ordenación topológica del ecosistema piloto
$grafo->ordenTopologico($ecosistema)->pluck('codigo');
// → ["SC-01", "SC-02", "SC-03"]  (SC-03 siempre al final)

// ZDP con perfil vacío
$grafo->calcularZdp($ecosistema, [])->pluck('codigo');
// → ["SC-01", "SC-02"]

// ZDP tras conquistar SC-01
$grafo->calcularZdp($ecosistema, ['SC-01'])->pluck('codigo');
// → ["SC-02"]

// Probar la validación de acíclicidad
// (SC-01 → SC-03 ya existe implícitamente vía SC-02; esto crearía un ciclo directo)
try {
    $sc01 = App\Models\SituacionCompetencia::where('codigo','SC-01')->first();
    $sc03 = App\Models\SituacionCompetencia::where('codigo','SC-03')->first();
    // SC-03 requiere SC-01; añadir SC-01 requiere SC-03 crea ciclo
    $grafo->validarAristaAciclica($sc01->id, $sc03->id, $ecosistema);
} catch (\RuntimeException $e) {
    echo $e->getMessage();
}
// → "La arista crea un ciclo: la SC #1 ya es alcanzable desde la SC #3."

// Recomendación
$rec = app(App\Services\RecomendacionService::class);
$rec->recomendar($ecosistema, [])->codigo;
// → "SC-01"

// Ranking de la ZDP
$rec->rankingZdp($ecosistema, [])->map(fn($i) => [
    $i['sc']->codigo => $i['score']
]);
// → [{"SC-01": 20}, {"SC-02": 17}]  (puntuaciones orientativas)
```

---

## ✅ Verificación final de la Unidad 4

Antes de continuar con la Unidad 5, confirma que:

- [ ] `GrafoService::calcularZdp($ecosistema, [])` devuelve `[SC-01, SC-02]`.
- [ ] `GrafoService::calcularZdp($ecosistema, ['SC-01', 'SC-02'])` devuelve `[SC-03]`.
- [ ] `GrafoService::calcularZdp($ecosistema, ['SC-01', 'SC-02', 'SC-03'])` devuelve colección vacía.
- [ ] `GrafoService::ordenTopologico($ecosistema)` devuelve SC-01 y SC-02 antes que SC-03.
- [ ] `GrafoService::validarAristaAciclica` lanza `RuntimeException` ante una arista que crea ciclo.
- [ ] `RecomendacionService::recomendar($ecosistema, [])` devuelve SC-01 (menor complejidad).
- [ ] `GET /api/v1/estudiante/perfil/1/zdp` devuelve `completado: true` cuando los tres códigos están conquistados.
- [ ] La vista `estudiante/modulo.blade.php` muestra SC-03 en "Bloqueadas" con el texto "Requiere: SC-01, SC-02" en un perfil sin conquistas.
- [ ] Puedes explicar la diferencia entre `GrafoService` (qué es accesible) y `RecomendacionService` (qué conviene acometer primero).
- [ ] Puedes explicar por qué el motor ZDP vive en un `Service` y no en el `PerfilHabilitacion` ni en el controlador.

---

## 📖 Referencias

- [Repositorio del caso de uso VFDS-EAC](https://github.com/C3-VFDS/use_case_pkst)
- [Vygotsky — Zona de Desarrollo Próximo](https://en.wikipedia.org/wiki/Zone_of_proximal_development)
- [Algoritmo de Kahn (ordenación topológica)](https://en.wikipedia.org/wiki/Topological_sorting#Kahn's_algorithm)
- [Laravel Service Container](https://laravel.com/docs/container)
- [Laravel Service Providers](https://laravel.com/docs/providers)

---

**Unidad anterior ←** [Unidad 3: API REST EAC](./03_api_rest_eac.md)

**Siguiente unidad →** [Unidad 5: Evaluación y seguimiento](./05_evaluacion_seguimiento.md)
