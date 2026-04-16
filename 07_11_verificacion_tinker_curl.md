## 7.11. Verificación del guard en Tinker

```bash
php artisan tinker
```

```php
// Simular una petición con Bearer Token real

$token = 'eyJhbGciOiJSUzI1NiIs...'; // token obtenido del Connector o el del ejemplo anterior

$request = \Illuminate\Http\Request::create('/api/v1/estudiante/perfil', 'GET');
$request->headers->set('Authorization', 'Bearer ' . $token);
$request->headers->set('Accept', 'application/json');

// Resolver el guard manualmente
$guard = new \App\Auth\VerifierGuard(
    auth()->createUserProvider('users'),
    $request,
    app(\App\Services\VerifierJwksService::class)
);

$user = $guard->user();
dd($user?->email, $user?->roles?->first()?->name);
// Un token válido, debe mostrar el email del token y el rol mapeado ("estudiante" o "docente")
```

---

## 7.12. Entorno de desarrollo sin Connector

Cuando desarrollas en local sin el FIWARE Dataspace Connector, simplemente usas Sanctum tal como se hizo en todas las unidades anteriores. El fichero `.env.testing` o tu `.env` local mantiene:

```ini
API_AUTH_DRIVER=sanctum
```

Y tus tests existentes (`Sanctum::actingAs($usuario)`) siguen funcionando sin modificación.

Cuando despliegues en el entorno con el Connector activo, cambias a:

```ini
API_AUTH_DRIVER=verifier
```

No hay que tocar ningún controlador ni ninguna ruta: el cambio es solo de configuración de infraestructura.

---

## 7.13. Prueba de integración manual con curl

Una vez que el Connector está activo y tienes un JWT válido (obtenido tras completar el flujo OIDC4VP con una `StudentCredential` o `TeacherCredential`):

> Pregunta al profesor cómo obtener un token de prueba o cómo configurar un cliente OIDC para hacer el flujo de autenticación manualmente.

```bash
# Guarda el token en una variable
TOKEN="eyJhbGciOiJSUzI1NiIs..."

# 1. Prueba el endpoint de perfil del estudiante
curl -s http://backend-eac.vfds.es/api/v1/estudiante/perfil \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json" | jq .

# Respuesta esperada: datos del perfil del estudiante
# Respuesta con error de auth: { "message": "Unauthenticated." }

# 2. Prueba que un token de estudiante es rechazado en rutas de docente
curl -s http://backend-eac.vfds.es/api/v1/docente/ecosistemas \
  -H "Authorization: Bearer $TOKEN_ESTUDIANTE" \
  -H "Accept: application/json" | jq .

# Debe devolver HTTP 403 Forbidden

# 3. Verifica que un token expirado es rechazado
curl -s http://backend-eac.vfds.es/api/v1/estudiante/perfil \
  -H "Authorization: Bearer $TOKEN_EXPIRADO" \
  -H "Accept: application/json" | jq .

# Debe devolver HTTP 401 Unauthenticated
```

---

**Unidad anterior ←** [Unidad 6: Visualización del espacio competencial](./06_visualizacion_espacio_competencial.md)

**Siguiente capítulo →** [7.14. Verificación final de la Unidad 7](./07_14_verificacion_final.md)
