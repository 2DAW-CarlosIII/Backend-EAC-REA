## 3.8. ✅ Verificación final de la Unidad 3

Antes de continuar con la Unidad 4, confirma que:

- [ ] `GET /api/v1/modulos` devuelve la colección paginada con el módulo piloto.
- [ ] `GET /api/v1/modulos/1` incluye `resultados_aprendizaje` (cargados desde el módulo, no desde el ecosistema).
- [ ] `GET /api/v1/ecosistemas/1/situaciones` devuelve las 3 SCs y SC-03 incluye `prerequisitos` con SC-01 y SC-02.
- [ ] `GET /api/v1/estudiante/perfil/1` sin token devuelve `401`, no HTML.
- [ ] `POST /api/v1/estudiante/matriculas` con un módulo ya matriculado devuelve `409`.
- [ ] `POST /api/v1/docente/ecosistemas/1/conquistas` con `puntuacion_conquista` inferior al `umbral_maestria` de la SC devuelve `422` con el mensaje de umbral no alcanzado.
- [ ] Tras registrar SC-01, `GET /api/v1/estudiante/perfil/1` muestra `codigos_conquistados: ["SC-01"]` y SC-02/SC-03 siguen sin aparecer en esa lista.
- [ ] El campo `ngsi_ld_id` del perfil sigue el patrón `urn:ngsi-ld:PerfilHabilitacion:estudiante-{id}-ecosistema-{id}`.

---

## 📖 Referencias

- [Repositorio del caso de uso VFDS-EAC](https://github.com/C3-VFDS/use_case_pkst)
- [Laravel API Resources](https://laravel.com/docs/eloquent-resources)
- [Laravel Sanctum](https://laravel.com/docs/sanctum)

---

**Unidad anterior ←** [Unidad 2: Frontend: layout y vistas Blade para el marketplace](./02_frontend_layout.md)

**Siguiente unidad →** [Unidad 4: Motor de navegación: ZDP y recomendación](./04_motor_navegacion.md)
