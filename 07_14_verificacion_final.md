## 7.14. Verificación final de la Unidad 7

Antes de continuar , confirma que:

- [ ] `composer require firebase/php-jwt:"^6.10"` se ha ejecutado sin errores.
- [ ] `config/verifier.php` existe y las variables de entorno están definidas en `.env`.
- [ ] La migración `add_verifier_sub_to_users_table` se ha ejecutado y el campo existe en la base de datos.
- [ ] `php artisan verifier:diagnostic` se ejecuta sin errores de conexión cuando el Connector está accesible, y lista al menos una clave JWKS.
- [ ] Con `API_AUTH_DRIVER=verifier`, una petición con Bearer Token válido a `/api/v1/estudiante/perfil` devuelve HTTP 200 y los datos del perfil.
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
- [Verifier — OIDC endpoints](https://www.verifier.org/docs/latest/server_admin/#_oidc_endpoints)
- [FIWARE VCVerifier](https://github.com/FIWARE/VCVerifier)
- [OIDC4VP — OpenID for Verifiable Presentations](https://openid.net/specs/openid-4-verifiable-presentations-1_0.html)

---

**Unidad anterior ←** [Unidad 6: Visualización del espacio competencial](./06_visualizacion_espacio_competencial.md)
