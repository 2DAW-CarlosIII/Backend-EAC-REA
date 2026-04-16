# Unidad 7: Autorización JWT — Laravel como Resource Server OIDC

## Objetivos de esta unidad

Al finalizar esta unidad serás capaz de:

- Qué es un Java Web Token (JWT) y cómo se estructura.
- Explicar qué papel juega Laravel dentro de la cadena de autenticación del FIWARE Dataspace Connector, y qué parte del proceso **ya ha completado APISIX** antes de que la petición llegue a tu aplicación.
- Instalar y configurar `firebase/php-jwt` para validar tokens JWT emitidos por Verifier.
- Implementar un **guard de Laravel** personalizado (`VerifierGuard`) que actúa como OIDC Resource Server: valida el Bearer Token, cachea las claves públicas JWKS y carga la identidad del usuario.
- Mapear los claims del JWT (`StudentCredential` / `TeacherCredential`) a los roles internos `estudiante` / `docente` que ya usa el sistema.
- Integrar el guard con el sistema de autenticación de Laravel de forma que los middlewares existentes (`auth`, `@role`) sigan funcionando sin cambios en los controladores.
- Sustituir las referencias a Laravel Sanctum (usado como placeholder en unidades anteriores) por el nuevo guard.
- Escribir un comando Artisan de diagnóstico para verificar la conectividad con el endpoint JWKS de Verifier.

---

## 7.1. Posición de Laravel en la cadena de autenticación

Antes de escribir código es fundamental entender qué hace cada componente, porque eso determina exactamente qué tiene que implementar tu aplicación y qué puede delegar en la infraestructura.

### El flujo completo (pasos 1 → 16)

![Invocación de usuario a través de FIWARE](https://github.com/FIWARE/data-space-connector/raw/main/doc/img/service_invocation_end_user.png)

### Lo que APISIX ya ha hecho cuando la petición llega a Laravel

Cuando APISIX reenvía la petición al paso 16, **ya ha comprobado**:

- Que el JWT no está expirado (`exp`).
- Que la firma del JWT es válida (verificada contra la clave pública de Verifier).
- Que el emisor (`iss`) está en la lista de confianza.
- Que la credencial verificable que originó el JWT no ha sido revocada.
- Que las políticas ODRL del PDP permiten el acceso al recurso solicitado.

**Lo que aún tiene que hacer _Backend EAC_ :**

- Leer el Bearer Token de la cabecera `Authorization`.
- Decodificar el JWT y extraer los claims (`sub`, `email`, roles…).
- Resolver qué usuario local corresponde a ese `sub` (o crearlo si no existe).
- Mapear los roles del JWT a los roles internos del sistema.
- Poner ese usuario en `auth()->user()` para que los controladores lo usen con normalidad.

> **¿Por qué validar la firma si APISIX ya lo ha hecho?**
>
> Porque **defensa en profundidad**. Si por un error de configuración alguien consigue acceder a tu aplicación saltándose APISIX (por ejemplo, conectándose directamente al puerto de Laravel en red interna), el guard lo detectará. Un JWT con firma inválida o sin firma será rechazado. Esta práctica —validar siempre en el Resource Server aunque el proxy ya lo haya hecho— está recomendada por las buenas prácticas de OAuth2 (RFC 9700).

---

## 7.2. Estructura de los JWT emitidos por Verifier

Un JWT (JSON Web Token) es un formato estándar para representar información de identidad y autorización en forma de token firmado digitalmente. En el contexto del FIWARE Dataspace Connector, Verifier emite un JWT de acceso tras validar la credencial verificable del usuario. Este JWT contiene claims como `sub`, `email` y `roles` que tu aplicación puede usar para identificar al usuario y asignarle permisos.

Verifier actúa como **VC Issuer** en el perímetro del centro, firmando credenciales con Ed25519. El JWT que llega a tu aplicación tiene esta estructura aproximada:

### Header

```json
{
  "alg": "ES256",
  "kid": "random-kid1",
  "typ": "JWT"
}
```

> Verifier puede configurarse con RS256 (RSA) o ES256 (ECDSA). Ed25519 se usa para las VCs W3C; el JWT de acceso que emite el Verifier tras validar la VC suele ser RS256. Confirmad con el operador del Connector qué algoritmo usa vuestro despliegue concreto.

### Payload (claims relevantes)

```json
{
  "aud": [
    "data-service"
  ],
  "exp": 1776068420,
  "iat": 1776066620,
  "iss": "https://central-verifier.vfds.es",
  "nonce": "9d1fe1e4-bc82-405c-bcfd-.........",
  "sub": "did:web:did-central.vfds.es",
  "verifiableCredential": {
    "credentialSubject": {
      "email": "test@user.org",
      "firstName": "Test",
      "roles": [
        {
          "names": [
            "OPERATOR",
            "READER"
          ],
          "target": "did:web:did-central.vfds.es"
        }
      ]
    },
    "issuer": "did:web:did-central.vfds.es",
    "type": "ResearcherCredential"
  }
}
```

Los claims que usará tu guard son:

| Claim | Uso en Laravel |
|-------|---------------|
| `email` | Para localizar o crear el usuario local |
| `credentialSubject.roles` | Para asignar el rol interno (`operador` / `docente` / `investigador`) |
| `vc_type` | Alternativa al _claim_ de roles si el Connector lo inyecta |
| `aud` | Debe coincidir con el client ID de tu aplicación |

> **Importante:** La estructura exacta de los claims depende de cómo el operador del Connector haya configurado el mapeo entre la credencial verificable (VC) y el JWT de acceso. Los claims anteriores son los más habituales en despliegues FIWARE, pero **consulta siempre el endpoint `/.well-known/openid-configuration`** del realm de Keycloak para ver el token de ejemplo real de tu entorno. Por ejemplo, https://keycloak-central.vfds.es/realms/test-realm/.well-known/openid-configuration

---

## 7.3. Instalación del paquete de validación _JWT_

```bash
composer require firebase/php-jwt:"^6.10"
```

Esta librería te permite decodificar y verificar un _JWT_ contra una o varias claves públicas sin necesidad de un servidor de autorización completo. Es la opción más sencilla para implementar un _Resource Server_ en _PHP_.

---

Añade las variables de entorno de Verifier al fichero `.env`:

```ini
# .env

# Verifier del espacio de datos
VERIFIER_BASE_URL=https://central-verifier.vfds.es
VERIFIER_JWKS=".well-known/jwks"

# Client ID registrado en Verifier para esta aplicación
VERIFIER_CLIENT_ID=data-service

# Algoritmo de firma del JWT de acceso (RS256 o ES256 según el despliegue)
VERIFIER_JWT_ALGORITHM=ES256

# Nombre del claim que contiene el rol del usuario en el JWT
# Opciones comunes: "realm_access.roles", "vc_type", "roles"
VERIFIER_ROLE_CLAIM=roles

# TTL en segundos para cachear las claves públicas JWKS (evitar hammering)
VERIFIER_JWKS_CACHE_TTL=3600

# Usar "sanctum" en desarrollo local (sin Connector), "verifier" en producción
API_AUTH_DRIVER=verifier
```

Recuerda añadir estas variables también en `.env.example` para que los desarrolladores sepan que deben configurarlas:

```ini
# .env.example

VERIFIER_BASE_URL=https://central-verifier.vfds.es
VERIFIER_JWKS=".well-known/jwks"
VERIFIER_CLIENT_ID=data-service
VERIFIER_JWT_ALGORITHM=ES256
VERIFIER_ROLE_CLAIM=roles
VERIFIER_JWKS_CACHE_TTL=3600
API_AUTH_DRIVER=verifier
```

En relación a las variables de entorno, conviene también crear un archivo `.env.testing` con una copia del archivo `.env` pero con `API_AUTH_DRIVER=sanctum` para que los tests que usen `Sanctum::actingAs()` sigan funcionando sin cambios.

---

Publica el fichero de configuración (lo crearemos nosotros directamente, no hay vendor:publish):

```bash
touch config/verifier.php
```

```php
<?php
// config/verifier.php

return [
    'base_url'       => env('VERIFIER_BASE_URL', 'https://verifier.vfds.example.org'),
    'client_id'      => env('VERIFIER_CLIENT_ID', 'backend-eac'),
    'algorithm'      => env('VERIFIER_JWT_ALGORITHM', 'RS256'),
    'role_claim'     => env('VERIFIER_ROLE_CLAIM', 'realm_access.roles'),
    'jwks_cache_ttl' => (int) env('VERIFIER_JWKS_CACHE_TTL', 3600),

    /*
     * URL del endpoint JWKS derivada automáticamente del realm.
     * Formato estándar OIDC. Puedes sobreescribirla si el despliegue
     * usa una ruta personalizada.
     */
    'jwks_uri' => env(
        'VERIFIER_JWKS_URI',
        env('VERIFIER_BASE_URL', 'https://verifier.vfds.example.org')
        . '/'
        . env('VERIFIER_JWKS', '.well-known/jwks')
    ),

    /*
     * URL del issuer esperado en el claim "iss" del JWT.
     * Debe coincidir exactamente con el valor que emite Verifier.
     */
    'issuer' => env(
        'VERIFIER_ISSUER',
        env('VERIFIER_BASE_URL', 'https://verifier.vfds.example.org')
    ),
];
```

---

**Unidad anterior ←** [Unidad 6: Visualización del espacio competencial](./06_visualizacion_espacio_competencial.md)

**Siguiente capítulo →** [7.3. El servicio JWKS: obtener y cachear las claves públicas](./07_03_servicio_jkws.md)