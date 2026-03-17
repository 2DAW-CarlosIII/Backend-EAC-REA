## 5.8. Verificación con `curl` y Tinker

### Calcular calificación en Tinker

```bash
php artisan tinker
```

```php
$perfil = App\Models\PerfilHabilitacion::first()
    ->load('situacionesConquistadas', 'ecosistemaLaboral.modulo.resultadosAprendizaje.criteriosEvaluacion');

$svc = app(App\Services\CalificacionService::class);

// Solo con SC-01 conquistada (supervisado, 84.5)
$svc->calcular($perfil);
// → depende de los pesos del seeder; con los datos piloto ≈ 1.49

// Desglose completo
$svc->desglose($perfil);
// → ['calificacion_total' => 1.49, 'desglose_ra' => [...]]

// Generar huella
$huella = app(App\Services\HuellaService::class)->generar($perfil);
$huella->payload['calificacion'];
// → 1.49
$huella->payload['situaciones_conquistadas'][0]['gradiente_autonomia'];
// → 'supervisado'
$huella->payload['situaciones_conquistadas'][0]['puntuacion_efectiva'];
// → 76.05  (84.5 × 0.90)
```

### Endpoints de la API

```bash
# Generar nueva huella
curl -s -X POST http://backend-eac.test/api/v1/estudiante/perfil/1/huella \
  -H "Authorization: Bearer TU_TOKEN" | jq .

# Consultar la huella más reciente
curl -s http://backend-eac.test/api/v1/estudiante/perfil/1/huella \
  -H "Authorization: Bearer TU_TOKEN" | jq '.data.calificacion, .data.situaciones_conquistadas[].gradiente_autonomia'

# Desglose de calificación (como docente)
curl -s http://backend-eac.test/api/v1/docente/ecosistemas/1/calificacion/2 \
  -H "Authorization: Bearer TOKEN_DOCENTE" | jq '.data.desglose_ra[] | {ra: .ra, puntuacion: .puntuacion}'
```

### Respuesta esperada del desglose (módulo completado)

```json
{
  "data": {
    "estudiante_id": 2,
    "calificacion_total": 6.84,
    "desglose_ra": [
      {
        "ra": "RA1",
        "peso": 40,
        "puntuacion": 79.02,
        "criterios": [
          { "ce": "CE1a", "peso": 30, "puntuacion": 76.05, "cubierto": true },
          { "ce": "CE1b", "peso": 40, "puntuacion": 76.05, "cubierto": true },
          { "ce": "CE1c", "peso": 30, "puntuacion": 85.92, "cubierto": true }
        ]
      },
      {
        "ra": "RA2",
        "peso": 35,
        "puntuacion": 45.50,
        "criterios": [
          { "ce": "CE2a", "peso": 50, "puntuacion": 91.00, "cubierto": true },
          { "ce": "CE2b", "peso": 50, "puntuacion": 0.00,  "cubierto": false }
        ]
      }
    ]
  }
}
```

> **Nota:** CE2b tiene `cubierto: false` porque SC-03 aún no ha sido conquistada. La calificación refleja fielmente el estado parcial del módulo.

---

**Unidad anterior ←** [Unidad 4: Motor de navegación: ZDP y recomendación](./04_motor_navegacion.md)

**Siguiente capítulo →** [Unidad 5.9: Pruebas de la huella de talento](./05_09_tests_huella_talento.md)

**Siguiente unidad →** [Unidad 6: Visualización del espacio competencial](./06_visualizacion_espacio_competencial.md)
