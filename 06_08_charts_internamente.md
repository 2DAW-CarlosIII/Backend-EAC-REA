## 6.8. Profundización: cómo funciona `ConsoleTVs/Charts` internamente

Entender lo que hace la librería te ayudará a depurar problemas y personalizar las gráficas más allá de las opciones expuestas por la API fluida.

### El método `container()`

Genera un `<canvas>` HTML con un `id` único:

```html
<canvas id="8f4a2c1e3b..."></canvas>
```

### El método `script()`

Genera un bloque `<script>` que busca ese canvas por id e instancia `new Chart(canvas, config)`. La config es la serialización JSON de todos los datasets y opciones que has encadenado en el controlador.

```html
<script>
    var ctx = document.getElementById('8f4a2c1e3b...');
    new Chart(ctx, {
        "type": "bar",
        "data": { "labels": [...], "datasets": [...] },
        "options": { ... }
    });
</script>
```

### Por qué `@push('scripts')` y no incluir `script()` inline

Si incluyes `{!! $chart->script() !!}` directamente en el cuerpo del HTML, el `<script>` se ejecuta antes de que Chart.js (cargado al final del `<body>`) esté disponible. Al usar `@push('scripts')`, los scripts de las gráficas se insertan **después** del CDN de Chart.js, garantizando el orden correcto.

### Opciones avanzadas con `options()`

El método `options(array $config)` acepta cualquier clave válida de la [API de configuración de Chart.js](https://www.chartjs.org/docs/latest/configuration/). Se fusiona con las opciones por defecto del paquete usando `array_merge_recursive`. Si necesitas sobreescribir un valor (no solo añadir), puedes llamar a `options()` varias veces: la última llamada tiene precedencia en claves escalares.

---

**Unidad anterior ←** [Unidad 5: Evaluación y Huella de Talento](./05_evaluacion_huella_talento.md)

**Siguiente capítulo →** [Unidad 6.9: Verificación con `tinker` y `cUrl`](./06_09_verificacion_tinker_curl.md)

**Siguiente unidad →** [Unidad 7: Autenticación con Sanctum + Keyrock](./07_autenticacion_sanctum_keyrock.md)
