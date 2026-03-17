## ✅ Verificación final de la Unidad 5

Antes de continuar con la Unidad 6, confirma que:

- [ ] `CalificacionService::calcular()` devuelve `0.0` para un perfil sin conquistas.
- [ ] Con SC-01 (`supervisado`, 84.5) la calificación es inferior a con SC-01 (`autonomo`, 84.5): el factor del gradiente tiene efecto real.
- [ ] `CalificacionService::desglose()` muestra `cubierto: false` en los CE cuyas SCs no han sido conquistadas.
- [ ] `ConquistaController` ya no usa `avg()`: usa `CalificacionService::calcularYPersistir()`.
- [ ] `POST /api/v1/estudiante/perfil/1/huella` crea una fila en `huellas_talento` con el `payload` correcto.
- [ ] El `payload` de la huella contiene `ngsi_ld_id` con el formato `urn:ngsi-ld:PerfilHabilitacion:estudiante-{id}-ecosistema-{id}`.
- [ ] `GET /api/v1/estudiante/perfil/1/huellas` devuelve el historial de huellas ordenado por `generada_en` descendente.
- [ ] Puedes explicar por qué `fresh()` es necesario en el `ConquistaController` antes de llamar a `calcularYPersistir`.
- [ ] Puedes explicar la diferencia entre `puntuacion_conquista` y `puntuacion_efectiva` en el payload de la huella.
- [ ] Puedes explicar por qué la calificación de un módulo no completado nunca puede ser 10, aunque todas las SCs conquistadas tengan 100 puntos.

---

## 📖 Referencias

- [Repositorio del caso de uso VFDS-EAC](https://github.com/C3-VFDS/use_case_pkst)
- [Laravel Service Container — Singleton](https://laravel.com/docs/11.x/container#binding-singletons)
- [Eloquent: Recargar modelos con `fresh()` y `refresh()`](https://laravel.com/docs/11.x/eloquent#refreshing-models)

---

**Unidad anterior ←** [Unidad 4: Motor de navegación: ZDP y recomendación](./04_motor_navegacion.md)

**Siguiente unidad →** [Unidad 6: Visualización del espacio competencial](./06_visualizacion_espacio_competencial.md)
