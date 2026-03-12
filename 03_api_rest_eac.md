# Unidad 3: API REST EAC

## Objetivos de esta unidad

Al finalizar esta unidad serás capaz de:

- Diseñar una API REST versionada siguiendo convenciones de Laravel para un dominio con varios roles de acceso.
- Implementar **API Resources** de Eloquent para transformar modelos en respuestas JSON consistentes.
- Separar los controladores de API de los controladores web en namespaces independientes.
- Exponer los endpoints públicos (catálogo de módulos, ecosistema, grafo de SCs) y los endpoints autenticados (perfil del estudiante, registro de conquistas, progreso del grupo para el docente).
- Verificar la API con `curl` y entender cómo su salida alimentará la sincronización con FIWARE Orion en la Unidad 6.

---

## 3.1. Diseño de la API

### Tabla de endpoints

| Verbo | Ruta | Acceso | Descripción |
|-------|------|--------|-------------|
| GET | `/api/v1/modulos` | Público | Catálogo paginado de módulos activos |
| GET | `/api/v1/modulos/{modulo}` | Público | Detalle: RA, CE y ecosistema activo |
| GET | `/api/v1/ecosistemas/{ecosistema}` | Público | Detalle del ecosistema con grafo de SCs |
| GET | `/api/v1/ecosistemas/{ecosistema}/situaciones` | Público | SCs del ecosistema (con prerequisitos) |
| GET | `/api/v1/estudiante/perfil` | Auth estudiante | Lista de perfiles del estudiante autenticado |
| GET | `/api/v1/estudiante/perfil/{ecosistema}` | Auth estudiante | Perfil completo en un ecosistema concreto |
| POST | `/api/v1/estudiante/matriculas` | Auth estudiante | Matricularse en un módulo |
| GET | `/api/v1/docente/ecosistemas/{ecosistema}/progreso` | Auth docente | Progreso del grupo |
| POST | `/api/v1/docente/ecosistemas/{ecosistema}/conquistas` | Auth docente | Registrar conquista de SC a un estudiante |

### Formato de respuesta

Todas las respuestas siguen el mismo formato de envoltorio con `data` y `meta` para facilitar la extensión futura (paginación, metadatos, etc.):

```json
{
    "data": { ... },
    "meta": {
        "version": "1.0",
        "timestamp": "2025-09-01T10:00:00Z"
    }
}
```

Los errores siguen RFC 9457 (Problem Details):

```json
{
    "type": "https://backend-eac.test/errors/not-found",
    "title": "Recurso no encontrado",
    "status": 404,
    "detail": "El ecosistema con id 99 no existe."
}
```

> **Relación con la Unidad 6:** el endpoint `GET /api/v1/estudiante/perfil/{ecosistema}` devuelve exactamente los campos que el `OrionSyncService` necesita para construir la entidad `PerfilHabilitacion` en NGSI-LD. El diseño de la respuesta no es casual.

---

## 3.2. Configuración

### Stub de autenticación Sanctum

En esta unidad los endpoints autenticados usan `auth:sanctum` como middleware. La emisión real de tokens (integrada con Keyrock) se implementa en la Unidad 4. Por ahora, emite un token manualmente para poder probar:

```bash
php artisan tinker
# En Tinker:
$user = App\Models\User::where('email', 'estudiante@backend-eac.test')->first();
$token = $user->createToken('test')->plainTextToken;
echo $token;
```

Guarda ese token: lo usarás en todas las llamadas `curl` autenticadas de esta unidad.

---

**Unidad anterior ←** [Unidad 2: Frontend: layout y vistas Blade para el marketplace](./02_frontend_layout.md)

  **Siguiente capítulo →** [Recursos API](./03_03_recursos_API.md)

**Siguiente unidad →** [Unidad 4: Autenticación con Sanctum + Keyrock](./04_autenticacion_sanctum_keyrock.md)
