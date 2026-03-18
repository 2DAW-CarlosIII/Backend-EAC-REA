## ✅ Verificación final de la Unidad 6

Antes de continuar con la Unidad 7, confirma que:

- [ ] `composer require consoletvs/charts:"6.*"` se ha ejecutado sin errores y `config/charts.php` existe.
- [ ] El layout base incluye Chart.js desde CDN y usa `@stack('scripts')`.
- [ ] `EACAnalyticsService::rankingConquistas()` devuelve un array con las tres claves `labels`, `data` y `colores`, y el número de elementos en `labels` coincide con el número de SCs del ecosistema.
- [ ] `EACAnalyticsService::distribucionGradiente()` devuelve siempre cuatro elementos (uno por nivel de gradiente), aunque alguno tenga valor `0`.
- [ ] `EACAnalyticsService::evolucionTemporal()` devuelve exactamente 8 elementos (o el número de semanas indicado), incluidas las semanas sin conquistas.
- [ ] `EACAnalyticsService::radarHuella()` devuelve un elemento por cada RA del módulo del ecosistema.
- [ ] La ruta `GET /docente/ecosistemas/{ecosistema}/analytics` responde con HTTP 200 y el HTML contiene tres elementos `<canvas>`.
- [ ] La ruta `GET /estudiante/perfil/{ecosistema}/huella-radar` responde con HTTP 200 y el HTML contiene un `<canvas>` con el radar del estudiante.
- [ ] En la vista del docente, las tarjetas de resumen muestran el número correcto de estudiantes y conquistas.
- [ ] En la vista del estudiante, la calificación mostrada coincide con `$perfil->calificacion_actual`.
- [ ] Puedes explicar por qué `@push('scripts')` es necesario en lugar de incluir `{!! $chart->script() !!}` directamente en el cuerpo del HTML.
- [ ] Puedes explicar la diferencia de propósito entre la gráfica radar del estudiante y las tres gráficas del panel del docente.

---

## 📖 Referencias

- [Repositorio del caso de uso VFDS-EAC](https://github.com/C3-VFDS/use_case_pkst)
- [ConsoleTVs/Charts — documentación oficial](https://charts.erikdever.com)
- [Chart.js v4 — Configuration API](https://www.chartjs.org/docs/latest/configuration/)
- [Chart.js v4 — Migration Guide](https://www.chartjs.org/docs/latest/migration/v4-migration.html)
- [Laravel Blade — Stacks (`@push` / `@stack`)](https://laravel.com/docs/11.x/blade#stacks)

---
