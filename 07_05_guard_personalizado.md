## 7.5. El guard personalizado: `VerifierGuard`

Un **guard** en _Laravel_ es la implementación concreta de cómo se autentica al usuario en cada request. El guard estándar `api` usa Sanctum o Passport. Nosotros creamos uno nuevo que lee el _Bearer Token_, lo valida con las claves _JWKS_ y resuelve el usuario.

```bash
mkdir -p app/Auth
touch app/Auth/VerifierGuard.php
```

```php
<?php

namespace App\Auth;

use App\Models\User;
use App\Services\VerifierJwksService;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Auth\GuardHelpers;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Http\Request;
use stdClass;

class VerifierGuard implements Guard
{
    use GuardHelpers;

    private Request             $request;
    private VerifierJwksService $jwksService;
    private ?stdClass           $decodedToken = null;

    public function __construct(
        UserProvider        $provider,
        Request             $request,
        VerifierJwksService $jwksService
    ) {
        $this->provider    = $provider;
        $this->request     = $request;
        $this->jwksService = $jwksService;
    }

    // ── Implementación de Guard ───────────────────────────────────────────

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

    public function validate(array $credentials = []): bool
    {
        return false;
    }

    // ── Helpers públicos ──────────────────────────────────────────────────

    public function token(): ?stdClass
    {
        $this->user();
        return $this->decodedToken;
    }

    /**
     * Devuelve el tipo de credencial del usuario autenticado,
     * ej. "ResearcherCredential", "StudentCredential".
     */
    public function credentialType(): ?string
    {
        $this->user();
        return $this->decodedToken?->verifiableCredential?->type ?? null;
    }

    // ── Extracción del Bearer Token ───────────────────────────────────────

    private function extractBearerToken(): ?string
    {
        $header = $this->request->header('Authorization', '');

        if (str_starts_with($header, 'Bearer ')) {
            return substr($header, 7);
        }

        return null;
    }

    // ── Validación del access token ───────────────────────────────────────

    private function decodeToken(string $token): ?stdClass
    {
        $algorithm = config('verifier.algorithm'); // ES256
        $issuer    = config('verifier.issuer');    // https://central-verifier.VERIFIER

        try {
            $keySet = $this->jwksService->getKeys();

            $payload = JWT::decode($token, $keySet);

            // Validar issuer
            if (($payload->iss ?? '') !== $issuer) {
                logger()->warning('VerifierGuard: issuer inválido', [
                    'expected' => $issuer,
                    'received' => $payload->iss ?? 'ausente',
                ]);
                return null;
            }

            // Validar audience solo si hay client_id configurado.
            // En el despliegue actual aud = ["data-service"]; configura
            // VERIFIER_CLIENT_ID=data-service para activar la validación.
            $clientId = config('verifier.client_id');

            if ($clientId) {
                $aud = $payload->aud ?? [];
                if (is_string($aud)) {
                    $aud = [$aud];
                }

                if (! in_array($clientId, $aud, strict: true)) {
                    logger()->warning('VerifierGuard: audience inválida', [
                        'expected' => $clientId,
                        'received' => $aud,
                    ]);
                    return null;
                }
            }

            return $payload;

        } catch (ExpiredException) {
            return null;

        } catch (\Firebase\JWT\SignatureInvalidException) {
            // Posible rotación de clave: refrescar JWKS y reintentar una vez
            try {
                $keySet    = $this->jwksService->refreshKeys();
                return JWT::decode($token, $keySet);
            } catch (\Throwable) {
                return null;
            }

        } catch (\Throwable $e) {
            logger()->warning('VerifierGuard: error decodificando JWT', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    // ── Resolución de usuario ─────────────────────────────────────────────

    /**
     * Busca o crea el usuario local a partir de los claims del access token.
     *
     * Estructura relevante del JWT:
     *   sub                                        → identificador único (DID)
     *   verifiableCredential.credentialSubject.email     → email
     *   verifiableCredential.credentialSubject.firstName → nombre
     *   verifiableCredential.type                        → tipo de credencial
     *   verifiableCredential.credentialSubject.roles[].names → roles
     */
    private function resolveUser(stdClass $payload): ?User
    {
        $sub = $payload->sub ?? null;

        if (! $sub) {
            return null;
        }

        $credentialSubject = $payload->verifiableCredential?->credentialSubject ?? null;
        $email             = $credentialSubject?->email ?? null;

        // Buscar por sub del Verifier
        $user = User::where('verifier_sub', $sub)->first();

        if ($user) {
            $this->syncRoles($user, $payload);
            return $user;
        }

        // Primera vez: crear el registro local
        if (! $email) {
            logger()->warning('VerifierGuard: JWT sin email en credentialSubject', [
                'sub' => $sub,
            ]);
            return null;
        }

        $name = $credentialSubject?->firstName
            ?? $credentialSubject?->name
            ?? $email;

        $user = User::create([
            'name'         => $name,
            'email'        => $email,
            'verifier_sub' => $sub,
            'password'     => '',
        ]);

        $this->syncRoles($user, $payload);

        return $user;
    }

    // ── Mapeo de roles ────────────────────────────────────────────────────

    private function syncRoles(User $user, stdClass $payload): void
    {
        $internalRole = $this->resolveInternalRole($payload);

        if (! $internalRole) {
            logger()->warning('VerifierGuard: no se pudo determinar el rol del usuario', [
                'verifier_sub'    => $user->verifier_sub,
                'credential_type' => $payload->verifiableCredential?->type,
            ]);
            return;
        }

        // Evitar escrituras innecesarias si el rol no ha cambiado
        $currentRole = \Illuminate\Support\Facades\DB::table('user_roles')
            ->join('roles', 'user_roles.role_id', '=', 'roles.id')
            ->where('user_roles.user_id', $user->id)
            ->value('roles.name');

        if ($currentRole === $internalRole) {
            return;
        }

        $roleModel = \App\Models\Role::firstOrCreate(['name' => $internalRole]);

        \Illuminate\Support\Facades\DB::table('user_roles')
            ->updateOrInsert(
                ['user_id' => $user->id],
                [
                    'role_id'    => $roleModel->id,
                    'updated_at' => now(),
                ]
            );
    }

    /**
     * Determina el rol interno consultando en orden de prioridad:
     *
     * 1. verifiableCredential.type  (credencial completa: Student, Teacher…)
     * 2. verifiableCredential.credentialSubject.roles[].names  (roles explícitos)
     *
     * Tabla de mapeo:
     *   Tipo de credencial        → rol interno
     *   StudentCredential         → estudiante
     *   TeacherCredential         → docente
     *   ResearcherCredential      → docente
     *   OperatorCredential        → docente
     *
     *   Nombre de rol             → rol interno
     *   STUDENT                   → estudiante
     *   READER                    → estudiante
     *   TEACHER                   → docente
     *   OPERATOR                  → docente
     */
    private function resolveInternalRole(stdClass $payload): ?string
    {
        $credentialTypeMap = [
            'StudentCredential'    => 'estudiante',
            'TeacherCredential'    => 'docente',
            'ResearcherCredential' => 'docente',
            'OperatorCredential'   => 'docente',
        ];

        $roleNameMap = [
            'STUDENT'  => 'estudiante',
            'READER'   => 'estudiante',
            'TEACHER'  => 'docente',
            'OPERATOR' => 'docente',
        ];

        // 1. Tipo de credencial (fuente más semánticamente precisa)
        $vcType = $payload->verifiableCredential?->type ?? null;

        if ($vcType && isset($credentialTypeMap[$vcType])) {
            return $credentialTypeMap[$vcType];
        }

        // 2. Nombres de roles explícitos en credentialSubject.roles[].names
        $roles = $payload->verifiableCredential?->credentialSubject?->roles ?? [];

        foreach ($roles as $roleEntry) {
            $names = $roleEntry->names ?? [];
            foreach ($names as $name) {
                if (isset($roleNameMap[$name])) {
                    return $roleNameMap[$name];
                }
            }
        }

        return null;
    }
}
```

---

## 7.6. Migración: añadir `verifier_sub` a la tabla `users`

El guard necesita un campo en la tabla `users` para relacionar el `sub` del JWT con el registro local:

```bash
php artisan make:migration add_verifier_sub_to_users_table --table=users
```

```php
<?php
// database/migrations/xxxx_xx_xx_add_verifier_sub_to_users_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // en desarrollo local el campo es NULL
            $table->string('verifier_sub')->nullable()->unique()->after('email');
            $table->index('verifier_sub');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['verifier_sub']);
            $table->dropColumn('verifier_sub');
        });
    }
};
```

```bash
php artisan migrate
```

Añade `verifier_sub` a los `$fillable` del modelo `User`:

```php
// app/Models/User.php

protected $fillable = [
    'name',
    'email',
    'password',
    'verifier_sub',   // ← añadir
];
```

Añádelo también en los `$hidden` para que no se exponga en las respuestas JSON:

```php
protected $hidden = [
    'password',
    'remember_token',
    'verifier_sub',   // ← añadir
];
```

---

**Unidad anterior ←** [Unidad 6: Visualización del espacio competencial](./06_visualizacion_espacio_competencial.md)

**Siguiente capítulo →** [7.7. Registrar el guard personalizado en Laravel](./07_07_registrar_guard_driver.md)
