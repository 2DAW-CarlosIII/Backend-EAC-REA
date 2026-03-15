## 2.10. ✅ Verificación final de la Unidad 2

Antes de continuar con la Unidad 3, confirma que:

- [ ] `npm run build` completa sin errores.
- [ ] `GET /` muestra la portada con las tarjetas de módulos del seeder.
- [ ] `GET /modulos` muestra el catálogo con el filtro por familia funcional.
- [ ] `GET /modulos/1` muestra la ficha de **Técnicas Básicas de Merchandising** con sus RA y CE cargados desde `$modulo->resultadosAprendizaje` (relación directa, no a través del ecosistema).
- [ ] `GET /ecosistemas/1` muestra las tres SCs con sus prerequisitos.
- [ ] Al autenticarse como `estudiante@backend-eac.test`, el panel muestra el perfil de habilitación con barra de progreso al 0%.
- [ ] Al autenticarse como `docente@backend-eac.test`, el dashboard muestra el ecosistema asignado con el número de SCs y estudiantes.
- [ ] `GET /estudiante/dashboard` devuelve 403 para el docente y redirige a login para un invitado.
- [ ] Las directivas `@role('docente')` y `@role('estudiante')` funcionan correctamente en las vistas.

---

## 📖 Referencias

- [Repositorio del caso de uso VFDS-EAC](https://github.com/C3-VFDS/use_case_pkst)
- [Laravel Blade Templates](https://laravel.com/docs/blade)
- [Laravel Routing](https://laravel.com/docs/routing)
- [Tailwind CSS](https://tailwindcss.com/docs)
- [@tailwindcss/forms](https://github.com/tailwindlabs/tailwindcss-forms)
- [Laravel Vite Plugin](https://laravel.com/docs/vite)

---
**Unidad anterior ←** [Unidad 1: Modelado de datos EAC en Laravel](./01_modelado_datos_eac.md)

  **Siguiente capítulo →** [Soluciones](./02_11_soluciones.md)

**Siguiente unidad →** [Unidad 3: API REST EAC](./03_api_rest_eac.md)
