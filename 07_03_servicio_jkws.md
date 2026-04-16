## 7.4. El servicio JWKS: obtener y cachear las claves públicas

El endpoint JWKS (JSON Web Key Set) de Verifier expone las claves públicas en formato estándar RFC 7517. Las claves rotan periódicamente, por lo que las cacheamos pero con un TTL que permita la renovación.

```bash
touch app/Services/VerifierJwksService.php
```

```php
<?php

namespace App\Services;

use Firebase\JWT\JWK;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class VerifierJwksService
{
    private string  $jwksUri;
    private int     $cacheTtl;
    private string  $algorithm;

    public function __construct()
    {
        $this->jwksUri   = config('verifier.jwks_uri');
        $this->cacheTtl  = config('verifier.jwks_cache_ttl');
        $this->algorithm = config('verifier.algorithm');
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
        // Cache the raw JWKS array (serializable) and parse it on each request.
        $jwks = Cache::remember(
            'verifier_jwks',
            $this->cacheTtl,
            fn () => $this->fetchRawJwks()
        );

        // Firebase\JWT\JWK::parseKeySet devuelve un array indexado por "kid"
        // con objetos OpenSSLAsymmetricKey listos para verificar firmas.
        return JWK::parseKeySet($jwks, $this->algorithm);
    }

    /**
     * Fuerza la renovación de la caché de claves.
     * Útil cuando la validación falla con "invalid kid" (rotación de clave).
     */
    public function refreshKeys(): array
    {
        Cache::forget('verifier_jwks');
        return $this->getKeys();
    }

    // ── Internos ──────────────────────────────────────────────────────────

    private function fetchRawJwks(): array
    {
        $response = Http::timeout(5)->get($this->jwksUri);

        if (! $response->successful()) {
            throw new RuntimeException(
                "No se pudieron obtener las claves JWKS de Verifier. "
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

        // Devuelve el conjunto JWKS bruto (serializable) para cachear;
        return $jwks;
    }
}
```

---

**Unidad anterior ←** [Unidad 6: Visualización del espacio competencial](./06_visualizacion_espacio_competencial.md)

**Siguiente capítulo →** [7.5. Guard personalizado para autenticación con JWT](./07_05_guard_personalizado.md)
