## 6.2. Instalación de ConsoleTVs/Charts

### 6.2.1. Paquete PHP

```bash
composer require consoletvs/charts:"6.*"
```

### 6.2.2. Publicar el ServiceProvider

A partir de Laravel 11 los paquetes se registran automáticamente, pero Charts requiere publicar su ServiceProvider para que las vistas lo reconozcan:

```bash
php artisan vendor:publish --tag=charts_config
```

Esto crea `config/charts.php` con la lista de librerías JS disponibles. Comprueba que el valor `default` apunta a `Chartjs`:

```php
// config/charts.php
return [
    'default' => 'Chartjs',
    // ...
];
```

### 6.2.3. Cargar Chart.js desde CDN

`ConsoleTVs/Charts` genera el canvas HTML y el bloque `<script>` con la configuración, pero **no incluye** la librería Chart.js. Hay que cargarla en el layout base.

Abre `resources/views/layouts/app.blade.php` (creado en la Unidad 2) y añade antes de `</body>`:

```html
{{-- resources/views/layouts/app.blade.php --}}
{{-- ... --}}

    @stack('scripts')

    {{-- Chart.js — necesario para ConsoleTVs/Charts --}}
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>

</body>
</html>
```

> **¿Por qué Chart.js 4.x si el paquete se llama "Chartjs"?**
> `ConsoleTVs/Charts` genera la configuración JSON de Chart.js independientemente de la versión. La versión 4.x es compatible con v6 del paquete y es la más actual. Si ves errores de API en consola, consulta el [migration guide de Chart.js v4](https://www.chartjs.org/docs/latest/migration/v4-migration.html).

Verifica la instalación arrancando el servidor y comprobando que no hay errores de consola en una página que incluya el layout:

```bash
php artisan serve
```

---

**Unidad anterior ←** [Unidad 5: Evaluación y Huella de Talento](./05_evaluacion_huella_talento.md)

**Siguiente capítulo →** [Unidad 6.3: Servicio de analítica](./06_03_servicio_analitica.md)

**Siguiente unidad →** [Unidad 7: Autenticación con Sanctum + Keyrock](./07_autenticacion_sanctum_keyrock.md)
