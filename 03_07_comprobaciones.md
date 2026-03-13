## 3.7. Verificación

### 3.7.1 Verificación con `curl`

Para comprobar que los endpoints funcionan, ejecuta las siguientes peticiones desde un terminal, sustituyendo `TU_TOKEN` por el token generado en la [sección 3.2](./03_api_rest_eac.md#32-configuración).

> Para utilizar `jq` desde el terminal, instálalo con `sudo apt install jq`.

### Endpoints públicos

```bash
# Catálogo de módulos
curl -s http://backend-eac.test/api/v1/modulos | jq .

# Detalle de módulo (id=1)
curl -s http://backend-eac.test/api/v1/modulos/1 | jq .

# Ecosistema activo del módulo (id=1)
curl -s http://backend-eac.test/api/v1/ecosistemas/1 | jq .

# Grafo de SCs del ecosistema
curl -s http://backend-eac.test/api/v1/ecosistemas/1/situaciones | jq '.data[].codigo'
# → "SC-01"  "SC-02"  "SC-03"

# Prerequisitos de SC-03
curl -s http://backend-eac.test/api/v1/ecosistemas/1/situaciones \
  | jq '.data[] | select(.codigo=="SC-03") | .prerequisitos'
# → [{"id":1,"codigo":"SC-01"},{"id":2,"codigo":"SC-02"}]
```

### Endpoints autenticados — estudiante

```bash
# Perfil del estudiante en el ecosistema 1
curl -s http://backend-eac.test/api/v1/estudiante/perfil/1 \
  -H "Authorization: Bearer TU_TOKEN" | jq .

# Matricularse en el módulo 1
curl -s -X POST http://backend-eac.test/api/v1/estudiante/matriculas \
  -H "Authorization: Bearer TU_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"modulo_id": 1}' | jq .
```

### Endpoints autenticados — docente

> Para que estos endopoints funcionen, el token debe pertenecer a un usuario con el rol de docente en el ecosistema 1.

> Además, la petición al **progreso del grupo en el ecosistema** generará el error `Call to undefined relationship [estudiante] on model [App\Models\Matricula]`, que deberás corregir añadiendo esta relación al modelo `Matricula`.

```bash
# Progreso del grupo en el ecosistema 1
curl -s http://backend-eac.test/api/v1/docente/ecosistemas/1/progreso \
  -H "Authorization: Bearer TU_TOKEN" | jq .

# Registrar conquista de SC-01 al estudiante 2
curl -s -X POST http://backend-eac.test/api/v1/docente/ecosistemas/1/conquistas \
  -H "Authorization: Bearer TU_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
        "estudiante_id": 2,
        "sc_codigo": "SC-01",
        "gradiente_autonomia": "supervisado",
        "puntuacion_conquista": 84.5
      }' | jq .
```


### Respuesta esperada tras registrar SC-01

```json
{
    "data": {
        "perfil_habilitacion_id": 1,
        "sc_codigo": "SC-01",
        "gradiente_autonomia": "supervisado",
        "puntuacion_conquista": 84.5,
        "mensaje": "SC SC-01 registrada correctamente."
    },
    "meta": {
        "version": "1.0",
        "timestamp": "2025-09-01T10:00:00Z"
    }
}
```

### Respuesta esperada del perfil del estudiante

Observa cómo el campo `ngsi_ld_id` ya tiene el formato URN que usará la Unidad 6:

```json
{
    "data": {
        "id": 1,
        "estudiante": { "id": 2 },
        "ecosistema": { "id": 1, "codigo": "AC-TBM" },
        "calificacion_actual": 84.5,
        "codigos_conquistados": ["SC-01"],
        "situaciones_conquistadas": [
            {
                "codigo": "SC-01",
                "titulo": "Diseñar la disposición de productos en un lineal",
                "gradiente_autonomia": "supervisado",
                "puntuacion_conquista": 84.5,
                "intentos": 1,
                "fecha_conquista": "2025-09-01T10:00:00Z"
            }
        ],
        "ngsi_ld_id": "urn:ngsi-ld:PerfilHabilitacion:estudiante-2-ecosistema-1",
        "meta": {
            "version": "1.0",
            "timestamp": "2025-09-01T10:00:00Z",
            "updated_at": "2025-09-01T10:00:00Z"
        }
    }
}
```

### 3.7.3 Verificación con `test`

Para comprobar que los endpoints funcionan correctamente, puedes crear pruebas automatizadas utilizando el sistema de pruebas de Laravel.


**Unidad anterior ←** [Unidad 2: Frontend: layout y vistas Blade para el marketplace](./02_frontend_layout.md)

  **Siguiente capítulo →** [Verificación de la unidad](./03_08_verificacion_final.md)

**Siguiente unidad →** [Unidad 4: Autenticación con Sanctum + Keyrock](./04_autenticacion_sanctum_keyrock.md)
