# Unidad 7: Autorización JWT — Laravel como Resource Server OIDC

## Objetivos de esta unidad

Al finalizar esta unidad serás capaz de:

- Explicar qué papel juega Laravel dentro de la cadena de autenticación del FIWARE Dataspace Connector, y qué parte del proceso **ya ha completado APISIX** antes de que la petición llegue a tu aplicación.
- Instalar y configurar `firebase/php-jwt` para validar tokens JWT emitidos por Keycloak.
- Implementar un **guard de Laravel** personalizado (`KeycloakGuard`) que actúa como OIDC Resource Server: valida el Bearer Token, cachea las claves públicas JWKS y carga la identidad del usuario.
- Mapear los claims del JWT (`StudentCredential` / `TeacherCredential`) a los roles internos `estudiante` / `docente` que ya usa el sistema.
- Integrar el guard con el sistema de autenticación de Laravel de forma que los middlewares existentes (`auth`, `@role`) sigan funcionando sin cambios en los controladores.
- Sustituir las referencias a Laravel Sanctum (usado como placeholder en unidades anteriores) por el nuevo guard.
- Escribir un comando Artisan de diagnóstico para verificar la conectividad con el endpoint JWKS de Keycloak.

---

## 7.1. Posición de Laravel en la cadena de autenticación

Antes de escribir código es fundamental entender qué hace cada componente, porque eso determina exactamente qué tiene que implementar tu aplicación y qué puede delegar en la infraestructura.

### El flujo completo (pasos 1 → 16)

![Invocación de usuario a través de FIWARE](https://github.com/FIWARE/data-space-connector/raw/main/doc/img/service_invocation_end_user.png)

### Lo que APISIX ya ha hecho cuando la petición llega a Laravel

Cuando APISIX reenvía la petición al paso 16, **ya ha comprobado**:

- Que el JWT no está expirado (`exp`).
- Que la firma del JWT es válida (verificada contra la clave pública de Keycloak).
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

## 7.2. Estructura de los JWT emitidos por Keycloak

Keycloak actúa como **VC Issuer** en el perímetro del centro, firmando credenciales con Ed25519. El JWT que llega a tu aplicación tiene esta estructura aproximada:

### Header

```json
{
  "alg": "RS256",
  "typ": "JWT",
  "kid": "vfds-keycloak-2025-01"
}
```

> Keycloak puede configurarse con RS256 (RSA) o ES256 (ECDSA). Ed25519 se usa para las VCs W3C; el JWT de acceso que emite el Verifier tras validar la VC suele ser RS256. Confirmad con el operador del Connector qué algoritmo usa vuestro despliegue concreto.

### Payload (claims relevantes)

```json
{
  "iss": "https://keycloak-central.vfds.es/realms/test-realm",
  "sub": "did:web:did-central.vfds.es:users:estudiante-42",
  "aud": ["backend-eac"],
  "exp": 1712345678,
  "iat": 1712342078,
  "email": "estudiante@cfp.example.org",
  "preferred_username": "est42",
  "realm_access": {
    "roles": ["StudentCredential"]
  },
  "vc_type": "StudentCredential"
}
```

Los claims que usará tu guard son:

| Claim | Uso en Laravel |
|-------|---------------|
| `sub` | Identificador único del usuario en el espacio de datos |
| `email` | Para localizar o crear el usuario local |
| `realm_access.roles` | Para asignar el rol interno (`estudiante` / `docente`) |
| `vc_type` | Alternativa al claim de roles si el Connector lo inyecta |
| `aud` | Debe coincidir con el client ID de tu aplicación |

> **Importante:** La estructura exacta de los claims depende de cómo el operador del Connector haya configurado el mapeo entre la VC y el JWT de acceso. Los claims anteriores son los más habituales en despliegues FIWARE, pero **consulta siempre el endpoint `/.well-known/openid-configuration`** del realm de Keycloak para ver el token de ejemplo real de tu entorno.

---

## 7.3. Instalación del paquete de validación JWT

```bash
composer require firebase/php-jwt:"^6.10"
```

Esta librería te permite decodificar y verificar un JWT contra una o varias claves públicas sin necesidad de un servidor de autorización completo. Es la opción más sencilla para implementar un Resource Server en PHP.

Añade las variables de entorno de Keycloak al fichero `.env`:

```ini
# .env

# Realm de Keycloak del espacio de datos
KEYCLOAK_BASE_URL=https://keycloak-central.vfds.es
KEYCLOAK_REALM=test-realm

# Client ID registrado en Keycloak para esta aplicación
KEYCLOAK_CLIENT_ID=backend-eac

# Algoritmo de firma del JWT de acceso (RS256 o ES256 según el despliegue)
KEYCLOAK_JWT_ALGORITHM=RS256

# Nombre del claim que contiene el rol del usuario en el JWT
# Opciones comunes: "realm_access.roles", "vc_type", "roles"
KEYCLOAK_ROLE_CLAIM=realm_access.roles

# TTL en segundos para cachear las claves públicas JWKS (evitar hammering)
KEYCLOAK_JWKS_CACHE_TTL=3600
```

Publica el fichero de configuración (lo crearemos nosotros directamente, no hay vendor:publish):

```bash
touch config/keycloak.php
```

```php
<?php
// config/keycloak.php

return [
    'base_url'       => env('KEYCLOAK_BASE_URL', 'https://keycloak.vfds.example.org'),
    'realm'          => env('KEYCLOAK_REALM', 'vfds'),
    'client_id'      => env('KEYCLOAK_CLIENT_ID', 'backend-eac'),
    'algorithm'      => env('KEYCLOAK_JWT_ALGORITHM', 'RS256'),
    'role_claim'     => env('KEYCLOAK_ROLE_CLAIM', 'realm_access.roles'),
    'jwks_cache_ttl' => (int) env('KEYCLOAK_JWKS_CACHE_TTL', 3600),

    /*
     * URL del endpoint JWKS derivada automáticamente del realm.
     * Formato estándar OIDC. Puedes sobreescribirla si el despliegue
     * usa una ruta personalizada.
     */
    'jwks_uri' => env(
        'KEYCLOAK_JWKS_URI',
        env('KEYCLOAK_BASE_URL', 'https://keycloak.vfds.example.org')
        . '/realms/'
        . env('KEYCLOAK_REALM', 'vfds')
        . '/protocol/openid-connect/certs'
    ),

    /*
     * URL del issuer esperado en el claim "iss" del JWT.
     * Debe coincidir exactamente con el valor que emite Keycloak.
     */
    'issuer' => env(
        'KEYCLOAK_ISSUER',
        env('KEYCLOAK_BASE_URL', 'https://keycloak.vfds.example.org')
        . '/realms/'
        . env('KEYCLOAK_REALM', 'vfds')
    ),
];
```

---

## 7.4. El servicio JWKS: obtener y cachear las claves públicas

El endpoint JWKS (JSON Web Key Set) de Keycloak expone las claves públicas en formato estándar RFC 7517. Las claves rotan periódicamente, por lo que las cacheamos pero con un TTL que permita la renovación.

```bash
touch app/Services/KeycloakJwksService.php
```

```php
<?php
// app/Services/KeycloakJwksService.php

namespace App\Services;

use Firebase\JWT\JWK;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class KeycloakJwksService
{
    private string  $jwksUri;
    private int     $cacheTtl;
    private string  $algorithm;

    public function __construct()
    {
        $this->jwksUri   = config('keycloak.jwks_uri');
        $this->cacheTtl  = config('keycloak.jwks_cache_ttl');
        $this->algorithm = config('keycloak.algorithm');
    }

    /**
     * Devuelve el array de claves públicas en el formato que espera
     * Firebase\JWT\JWT::decode().
     *
     * Las claves se cachean durante $cacheTtl segundos para evitar
     * una petición HTTP en cada request.
     *
     * @return array<string, \OpenSSLAsymmetricKey>
     */
    public function getKeys(): array
    {
        return Cache::remember(
            'keycloak_jwks',
            $this->cacheTtl,
            fn () => $this->fetchAndParseKeys()
        );
    }

    /**
     * Fuerza la renovación de la caché de claves.
     * Útil cuando la validación falla con "invalid kid" (rotación de clave).
     */
    public function refreshKeys(): array
    {
        Cache::forget('keycloak_jwks');
        return $this->getKeys();
    }

    // ── Internos ──────────────────────────────────────────────────────────

    private function fetchAndParseKeys(): array
    {
        $response = Http::timeout(5)->get($this->jwksUri);

        if (! $response->successful()) {
            throw new RuntimeException(
                "No se pudieron obtener las claves JWKS de Keycloak. "
                . "URI: {$this->jwksUri}. "
                . "HTTP {$response->status()}"
            );
        }

        $jwks = $response->json();

        if (empty($jwks['keys'])) {
            throw new RuntimeException(
                "El endpoint JWKS devolvió un conjunto de claves vacío."
            );
        }

        // Firebase\JWT\JWK::parseKeySet devuelve un array indexado por "kid"
        // con objetos OpenSSLAsymmetricKey listos para verificar firmas.
        return JWK::parseKeySet($jwks);
    }
}
```

---

## 7.5. El guard personalizado: `KeycloakGuard`

Un **guard** en Laravel es la implementación concreta de cómo se autentica al usuario en cada request. El guard estándar `api` usa Sanctum o Passport. Nosotros creamos uno nuevo que lee el Bearer Token, lo valida con las claves JWKS y resuelve el usuario.

```bash
mkdir -p app/Auth
touch app/Auth/KeycloakGuard.php
```

```php
<?php
// app/Auth/KeycloakGuard.php

namespace App\Auth;

use App\Models\User;
use App\Services\KeycloakJwksService;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Auth\GuardHelpers;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use stdClass;

class KeycloakGuard implements Guard
{
    use GuardHelpers;

    private Request          $request;
    private KeycloakJwksService $jwksService;
    private ?stdClass        $decodedToken = null;

    public function __construct(
        UserProvider        $provider,
        Request             $request,
        KeycloakJwksService $jwksService
    ) {
        $this->provider    = $provider;
        $this->request     = $request;
        $this->jwksService = $jwksService;
    }

    // ── Implementación de Guard ───────────────────────────────────────────

    /**
     * Devuelve el usuario autenticado o null.
     * Se llama una sola vez por request gracias al trait GuardHelpers.
     */
    public function user(): ?User
    {
        if (! is_null($this->user)) {
            return $this->user;
        }

        $token = $this->extractBearerToken();

        if (! $token) {
            return null;
        }

        $payload = $this->decodeToken($token);

        if (! $payload) {
            return null;
        }

        $this->decodedToken = $payload;
        $this->user         = $this->resolveUser($payload);

        return $this->user;
    }

    /**
     * Requerido por el contrato Guard. No aplica a autenticación sin estado.
     */
    public function validate(array $credentials = []): bool
    {
        return false;
    }

    // ── Helpers públicos ──────────────────────────────────────────────────

    /**
     * Devuelve el payload decodificado del JWT actual, o null si no hay token.
     */
    public function token(): ?stdClass
    {
        $this->user(); // asegura que el token ya fue decodificado
        return $this->decodedToken;
    }

    // ── Internos ──────────────────────────────────────────────────────────

    private function extractBearerToken(): ?string
    {
        $header = $this->request->header('Authorization', '');

        if (str_starts_with($header, 'Bearer ')) {
            return substr($header, 7);
        }

        return null;
    }

    private function decodeToken(string $token): ?stdClass
    {
        $algorithm = config('keycloak.algorithm');
        $issuer    = config('keycloak.issuer');
        $clientId  = config('keycloak.client_id');

        try {
            $keys    = $this->jwksService->getKeys();
            $keySet  = array_map(
                fn ($key) => new Key($key, $algorithm),
                $keys
            );

            $payload = JWT::decode($token, $keySet);

            // Validar issuer
            if (($payload->iss ?? '') !== $issuer) {
                logger()->warning('KeycloakGuard: issuer inválido', [
                    'expected' => $issuer,
                    'received' => $payload->iss ?? 'ausente',
                ]);
                return null;
            }

            // Validar audience (el JWT debe ir dirigido a esta aplicación)
            $aud = $payload->aud ?? [];
            if (is_string($aud)) {
                $aud = [$aud];
            }

            if (! in_array($clientId, $aud, strict: true)) {
                logger()->warning('KeycloakGuard: audience inválida', [
                    'expected' => $clientId,
                    'received' => $aud,
                ]);
                return null;
            }

            return $payload;

        } catch (ExpiredException) {
            // Token expirado: no es un error de programación, es normal
            return null;

        } catch (\Firebase\JWT\SignatureInvalidException) {
            // Puede ser una rotación de clave: intentamos refrescar JWKS una vez
            try {
                $freshKeys = $this->jwksService->refreshKeys();
                $keySet    = array_map(
                    fn ($key) => new Key($key, $algorithm),
                    $freshKeys
                );
                return JWT::decode($token, $keySet);
            } catch (\Throwable) {
                return null;
            }

        } catch (\Throwable $e) {
            logger()->warning('KeycloakGuard: error decodificando JWT', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Busca o crea el usuario local a partir de los claims del JWT.
     *
     * La estrategia es: buscar por "sub" (identificador único del espacio
     * de datos) en el campo keycloak_sub de la tabla users. Si no existe,
     * crearlo con los datos básicos del JWT.
     */
    private function resolveUser(stdClass $payload): ?User
    {
        $sub   = $payload->sub   ?? null;
        $email = $payload->email ?? null;

        if (! $sub) {
            return null;
        }

        // Buscar por sub de Keycloak (campo añadido en esta unidad)
        $user = User::where('keycloak_sub', $sub)->first();

        if ($user) {
            // Sincronizar el rol con el claim actual del JWT
            $this->syncRoles($user, $payload);
            return $user;
        }

        // Primera vez que este usuario accede: crear registro local
        if (! $email) {
            logger()->warning('KeycloakGuard: JWT sin claim email, no se puede crear usuario', [
                'sub' => $sub,
            ]);
            return null;
        }

        $user = User::create([
            'name'         => $payload->preferred_username ?? $email,
            'email'        => $email,
            'keycloak_sub' => $sub,
            'password'     => '', // sin contraseña local; la auth es por JWT
        ]);

        $this->syncRoles($user, $payload);

        return $user;
    }

    /**
     * Mapea los roles del JWT a los roles internos del sistema.
     *
     * El claim puede estar en:
     *   - realm_access.roles  → ["estudiante"]
     *   - roles               → ["docente"]
     *   - vc_type             → "StudentCredential" / "TeacherCredential"
     */
    private function syncRoles(User $user, stdClass $payload): void
    {
        $roleClaim  = config('keycloak.role_claim'); // ej. "realm_access.roles"
        $jwtRoles   = $this->extractRoleClaim($payload, $roleClaim);
        $internalRole = $this->mapToInternalRole($jwtRoles, $payload);

        if ($internalRole && $user->role !== $internalRole) {
            // Actualiza el rol en la tabla user_roles (modelo existente)
            // Si el proyecto usa una relación de roles más compleja,
            // aquí se adaptaría al modelo concreto.
            $roleModel = \App\Models\Role::firstOrCreate(['name' => $internalRole]);

            // Para esta implementación, asumimos un único rol activo por usuario.
            // Ajusta según el modelo de roles de tu aplicación.
            \Illuminate\Support\Facades\DB::table('user_roles')
                ->updateOrInsert(
                    ['user_id' => $user->id],
                    [
                        'role_id'    => $roleModel->id,
                        'updated_at' => now(),
                    ]
                );
        }
    }

    /**
     * Extrae los roles del JWT siguiendo la ruta del claim configurado.
     * Soporta notación dot para claims anidados (ej. "realm_access.roles").
     *
     * @return string[]
     */
    private function extractRoleClaim(stdClass $payload, string $claimPath): array
    {
        $payloadArray = json_decode(json_encode($payload), true);
        $value        = Arr::get($payloadArray, $claimPath, []);

        if (is_string($value)) {
            return [$value];
        }

        return is_array($value) ? $value : [];
    }

    /**
     * Traduce los roles del JWT al rol interno del sistema EAC.
     *
     * Tabla de mapeo:
     *   JWT role            → Rol interno
     *   "estudiante"        → "estudiante"
     *   "docente"           → "docente"
     *   "StudentCredential" → "estudiante"  (si vc_type es el claim)
     *   "TeacherCredential" → "docente"     (si vc_type es el claim)
     */
    private function mapToInternalRole(array $jwtRoles, stdClass $payload): ?string
    {
        $map = [
            'estudiante'         => 'estudiante',
            'docente'            => 'docente',
            'StudentCredential'  => 'estudiante',
            'TeacherCredential'  => 'docente',
            'OperatorCredential' => 'docente',  // los operadores tienen acceso de docente
        ];

        // Comprobar también el claim vc_type como alternativa
        $vcType   = $payload->vc_type ?? null;
        $allRoles = array_merge($jwtRoles, $vcType ? [$vcType] : []);

        foreach ($allRoles as $role) {
            if (isset($map[$role])) {
                return $map[$role];
            }
        }

        return null;
    }
}
```

---

## 7.6. Migración: añadir `keycloak_sub` a la tabla `users`

El guard necesita un campo en la tabla `users` para relacionar el `sub` del JWT con el registro local:

```bash
php artisan make:migration add_keycloak_sub_to_users_table --table=users
```

```php
<?php
// database/migrations/xxxx_xx_xx_add_keycloak_sub_to_users_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('keycloak_sub')->nullable()->unique()->after('email');
            $table->index('keycloak_sub');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['keycloak_sub']);
            $table->dropColumn('keycloak_sub');
        });
    }
};
```

```bash
php artisan migrate
```

Añade `keycloak_sub` a los `$fillable` del modelo `User`:

```php
// app/Models/User.php

protected $fillable = [
    'name',
    'email',
    'password',
    'keycloak_sub',   // ← añadir
];
```

---

## 7.7. Registrar el guard en Laravel

### 7.7.1. Registrar el servicio JWKS

```php
// app/Providers/AppServiceProvider.php

use App\Services\KeycloakJwksService;

public function register(): void
{
    // ... registros previos ...

    $this->app->singleton(KeycloakJwksService::class);
}
```

### 7.7.2. Registrar el guard en `AuthServiceProvider`

```php
// app/Providers/AuthServiceProvider.php

use App\Auth\KeycloakGuard;
use App\Services\KeycloakJwksService;
use Illuminate\Support\Facades\Auth;

public function boot(): void
{
    Auth::extend('keycloak', function ($app, $name, array $config) {
        return new KeycloakGuard(
            Auth::createUserProvider($config['provider']),
            $app->make(\Illuminate\Http\Request::class),
            $app->make(KeycloakJwksService::class)
        );
    });
}
```

### 7.7.3. Configurar `config/auth.php`

```php
// config/auth.php

'guards' => [
    'web' => [
        'driver'   => 'session',
        'provider' => 'users',
    ],

    // Guard anterior con Sanctum (mantener para desarrollo local sin Connector)
    'sanctum' => [
        'driver'   => 'sanctum',
        'provider' => 'users',
    ],

    // Nuevo guard para producción con el FIWARE Dataspace Connector
    'keycloak' => [
        'driver'   => 'keycloak',
        'provider' => 'users',
    ],

    // Guard API: usa Keycloak en producción, Sanctum en desarrollo
    'api' => [
        'driver'   => env('API_AUTH_DRIVER', 'keycloak'),
        'provider' => 'users',
    ],
],
```

Añade en `.env`:

```ini
# Usar "sanctum" en desarrollo local (sin Connector), "keycloak" en producción
API_AUTH_DRIVER=keycloak
```

Y en `.env.testing`:

```ini
API_AUTH_DRIVER=sanctum
```

Así los tests de las unidades anteriores (que usan `Sanctum::actingAs()`) siguen funcionando sin cambios.

---

## 7.8. Adaptar las rutas a los nuevos guards

### 7.8.1. API REST (`routes/api.php`)

Las rutas ya usan `middleware('auth:sanctum')`. Cambia el guard:

```php
// routes/api.php

// Antes:
Route::middleware('auth:sanctum')->group(function () { ... });

// Después (usa el guard configurado en API_AUTH_DRIVER):
Route::middleware('auth:api')->group(function () { ... });
```

O bien, si quieres ser explícito y soportar ambos simultáneamente (útil en el periodo de transición):

```php
Route::middleware('auth:keycloak,sanctum')->group(function () { ... });
```

Laravel probará cada guard en orden y usará el primero que autentique al usuario.

### 7.8.2. Rutas web (`routes/web.php`)

Las vistas Blade usan el guard `web` (sesión). Si quieres que el login web también use JWT, añade un middleware que intercambie el Bearer Token por una sesión. Lo más sencillo para esta unidad es mantener el guard `web` para las vistas y el guard `api` para los endpoints REST, que es la arquitectura más habitual.

---

## 7.9. Middleware de rol: sin cambios

El middleware `CheckRole` y la directiva Blade `@role` que se crearon en la Unidad 2 funcionan llamando a `auth()->user()` y comprobando el rol del usuario devuelto. Como el `KeycloakGuard` ya resuelve el usuario y sincroniza su rol, **no necesitas cambiar nada en esos middlewares**: simplemente pasan de recibir un usuario de Sanctum a recibir uno de Keycloak. El código de tus controladores no se modifica.

---

## 7.10. Comando de diagnóstico

Este comando Artisan te permite verificar la conectividad con Keycloak y la validez de un token concreto sin necesidad de hacer una petición HTTP a tu app:

```bash
php artisan make:command KeycloakDiagnosticCommand
```

```php
<?php
// app/Console/Commands/KeycloakDiagnosticCommand.php

namespace App\Console\Commands;

use App\Services\KeycloakJwksService;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class KeycloakDiagnosticCommand extends Command
{
    protected $signature   = 'keycloak:diagnostic {--token= : JWT a verificar (opcional)}';
    protected $description = 'Verifica la conectividad con Keycloak y valida un JWT de prueba';

    public function handle(KeycloakJwksService $jwksService): int
    {
        $this->info('─── Diagnóstico de Keycloak ────────────────────────────────');

        // 1. Configuración
        $this->line('');
        $this->line('<fg=cyan>Configuración:</>');
        $this->table(['Parámetro', 'Valor'], [
            ['Base URL',    config('keycloak.base_url')],
            ['Realm',       config('keycloak.realm')],
            ['Client ID',   config('keycloak.client_id')],
            ['Algorithm',   config('keycloak.algorithm')],
            ['Issuer',      config('keycloak.issuer')],
            ['JWKS URI',    config('keycloak.jwks_uri')],
            ['JWKS TTL',    config('keycloak.jwks_cache_ttl') . ' s'],
        ]);

        // 2. Conectividad con OIDC Discovery
        $this->line('<fg=cyan>Conectividad OIDC Discovery:</>');
        $discoveryUrl = config('keycloak.issuer') . '/.well-known/openid-configuration';

        try {
            $response = Http::timeout(5)->get($discoveryUrl);

            if ($response->successful()) {
                $this->line("  ✅ <fg=green>OK</> — {$discoveryUrl}");
                $discovery = $response->json();
                $this->line("     jwks_uri     : " . ($discovery['jwks_uri'] ?? 'no encontrado'));
                $this->line("     token_endpoint: " . ($discovery['token_endpoint'] ?? 'no encontrado'));
            } else {
                $this->error("  ❌ HTTP {$response->status()} — {$discoveryUrl}");
                return self::FAILURE;
            }
        } catch (\Throwable $e) {
            $this->error("  ❌ Error de conexión: " . $e->getMessage());
            $this->line("     ¿Está el servidor Keycloak accesible desde este host?");
            return self::FAILURE;
        }

        // 3. Descarga de claves JWKS
        $this->line('');
        $this->line('<fg=cyan>Claves JWKS:</>');

        try {
            $keys = $jwksService->refreshKeys();
            $this->line("  ✅ <fg=green>" . count($keys) . " clave(s) cargada(s)</>");
            foreach (array_keys($keys) as $kid) {
                $this->line("     kid: {$kid}");
            }
        } catch (\Throwable $e) {
            $this->error("  ❌ No se pudieron cargar las claves JWKS: " . $e->getMessage());
            return self::FAILURE;
        }

        // 4. Verificación de un token concreto (opcional)
        $token = $this->option('token');

        if ($token) {
            $this->line('');
            $this->line('<fg=cyan>Verificación del token proporcionado:</>');

            try {
                $algorithm = config('keycloak.algorithm');
                $keySet    = array_map(
                    fn ($key) => new Key($key, $algorithm),
                    $jwksService->getKeys()
                );

                $payload = JWT::decode($token, $keySet);

                $this->line("  ✅ <fg=green>Firma válida</>");
                $this->table(['Claim', 'Valor'], [
                    ['iss',   $payload->iss   ?? '—'],
                    ['sub',   $payload->sub   ?? '—'],
                    ['aud',   is_array($payload->aud ?? '') ? implode(', ', $payload->aud) : ($payload->aud ?? '—')],
                    ['email', $payload->email ?? '—'],
                    ['exp',   $payload->exp   ? date('Y-m-d H:i:s', $payload->exp) . ' UTC' : '—'],
                    ['iat',   $payload->iat   ? date('Y-m-d H:i:s', $payload->iat) . ' UTC' : '—'],
                    ['vc_type', $payload->vc_type ?? '—'],
                    ['roles (realm_access)', implode(', ', $payload->realm_access->roles ?? [])],
                ]);

                // Comprobar si el issuer y audience son los esperados
                if (($payload->iss ?? '') !== config('keycloak.issuer')) {
                    $this->warn("  ⚠️  El issuer del token no coincide con el configurado.");
                    $this->warn("     Esperado: " . config('keycloak.issuer'));
                    $this->warn("     Recibido: " . ($payload->iss ?? 'ausente'));
                }

                $aud = is_array($payload->aud ?? '') ? $payload->aud : [$payload->aud ?? ''];
                if (! in_array(config('keycloak.client_id'), $aud)) {
                    $this->warn("  ⚠️  El audience del token no incluye el client_id configurado.");
                    $this->warn("     Esperado en audience: " . config('keycloak.client_id'));
                    $this->warn("     Audience actual: " . implode(', ', $aud));
                }

            } catch (\Firebase\JWT\ExpiredException) {
                $this->warn("  ⚠️  El token ha expirado (la firma era válida).");
            } catch (\Throwable $e) {
                $this->error("  ❌ Token inválido: " . $e->getMessage());
            }
        }

        $this->line('');
        $this->info('Diagnóstico completado.');

        return self::SUCCESS;
    }
}
```

Uso:

```bash
# Solo conectividad
php artisan keycloak:diagnostic

# Con un token real para verificar
php artisan keycloak:diagnostic --token="eyJhbGciOiJSUzI1NiIsInR5cCI6..."
```

---

## 7.11. Verificación del guard en Tinker

```bash
php artisan tinker
```

```php
// Simular una petición con Bearer Token real

$token = 'eyJhbGciOiJSUzI1NiIs...'; // token obtenido del Connector

$request = \Illuminate\Http\Request::create('/api/v1/estudiante/perfil', 'GET');
$request->headers->set('Authorization', 'Bearer ' . $token);
$request->headers->set('Accept', 'application/json');

// Resolver el guard manualmente
$guard = new \App\Auth\KeycloakGuard(
    auth()->createUserProvider('users'),
    $request,
    app(\App\Services\KeycloakJwksService::class)
);

$user = $guard->user();
dd($user?->email, $user?->roles?->first()?->name);
// Debe mostrar el email del token y el rol mapeado ("estudiante" o "docente")
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
API_AUTH_DRIVER=keycloak
KEYCLOAK_BASE_URL=https://keycloak.vfds.example.org
KEYCLOAK_REALM=vfds
KEYCLOAK_CLIENT_ID=backend-eac
```

No hay que tocar ningún controlador ni ninguna ruta: el cambio es solo de configuración de infraestructura.

---

## 7.13. Prueba de integración manual con curl

Una vez que el Connector está activo y tienes un JWT válido (obtenido tras completar el flujo OIDC4VP con una `StudentCredential` o `TeacherCredential`):

```bash
# Guarda el token en una variable
TOKEN="eyJhbGciOiJSUzI1NiIs..."

# 1. Prueba el endpoint de perfil del estudiante
curl -s http://localhost:8000/api/v1/estudiante/perfil \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json" | jq .

# Respuesta esperada: datos del perfil del estudiante
# Respuesta con error de auth: { "message": "Unauthenticated." }

# 2. Prueba que un token de estudiante es rechazado en rutas de docente
curl -s http://localhost:8000/api/v1/docente/ecosistemas \
  -H "Authorization: Bearer $TOKEN_ESTUDIANTE" \
  -H "Accept: application/json" | jq .

# Debe devolver HTTP 403 Forbidden

# 3. Verifica que un token expirado es rechazado
curl -s http://localhost:8000/api/v1/estudiante/perfil \
  -H "Authorization: Bearer $TOKEN_EXPIRADO" \
  -H "Accept: application/json" | jq .

# Debe devolver HTTP 401 Unauthenticated
```

---

## ✅ Verificación final de la Unidad 7

Antes de continuar con la Unidad 8, confirma que:

- [ ] `composer require firebase/php-jwt:"^6.10"` se ha ejecutado sin errores.
- [ ] `config/keycloak.php` existe y las variables de entorno están definidas en `.env`.
- [ ] La migración `add_keycloak_sub_to_users_table` se ha ejecutado y el campo existe en la base de datos.
- [ ] `php artisan keycloak:diagnostic` se ejecuta sin errores de conexión cuando el Connector está accesible, y lista al menos una clave JWKS.
- [ ] Con `API_AUTH_DRIVER=keycloak`, una petición con Bearer Token válido a `/api/v1/estudiante/perfil` devuelve HTTP 200 y los datos del perfil.
- [ ] Una petición sin token devuelve HTTP 401.
- [ ] Una petición con token de estudiante a rutas de docente devuelve HTTP 403.
- [ ] Con `API_AUTH_DRIVER=sanctum`, los tests de unidades anteriores (`Sanctum::actingAs()`) siguen pasando sin modificaciones.
- [ ] Puedes explicar por qué Laravel sigue validando la firma del JWT aunque APISIX ya lo haya validado previamente.
- [ ] Puedes explicar la diferencia entre el `sub` del JWT (identificador en el espacio de datos, un DID) y el `id` del modelo `User` (clave primaria de la base de datos local).
- [ ] Puedes explicar por qué la caché de claves JWKS tiene un TTL y cuándo se invalida automáticamente (rotación de clave detectada por `SignatureInvalidException`).

---

## 📖 Referencias

- [Repositorio del caso de uso VFDS-EAC](https://github.com/C3-VFDS/use_case_pkst)
- [RFC 9700 — JWT Best Current Practices](https://www.rfc-editor.org/rfc/rfc9700)
- [RFC 7517 — JSON Web Key (JWK)](https://www.rfc-editor.org/rfc/rfc7517)
- [firebase/php-jwt — documentación](https://github.com/firebase/php-jwt)
- [Laravel — Guards personalizados](https://laravel.com/docs/11.x/authentication#adding-custom-guards)
- [Keycloak — OIDC endpoints](https://www.keycloak.org/docs/latest/server_admin/#_oidc_endpoints)
- [FIWARE VCVerifier](https://github.com/FIWARE/VCVerifier)
- [OIDC4VP — OpenID for Verifiable Presentations](https://openid.net/specs/openid-4-verifiable-presentations-1_0.html)

---

**Unidad anterior ←** [Unidad 6: Visualización del espacio competencial](./06_visualizacion_espacio_competencial.md)

**Siguiente unidad →** [Unidad 8: Sincronización con FIWARE Orion](./08_sincronizacion_fiware_orion.md)
