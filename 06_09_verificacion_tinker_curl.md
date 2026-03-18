## 6.9. Prueba manual paso a paso

Con el servidor arrancado (`php artisan serve`), sigue esta secuencia:

```bash
# 1. Crea un perfil con conquistas si no tienes datos de prueba
php artisan tinker
```

```php
// En Tinker
$ecosistema = App\Models\EcosistemaLaboral::find(1);
$perfil     = App\Models\PerfilHabilitacion::where('ecosistema_laboral_id', 1)->first();
$sc         = App\Models\SituacionCompetencia::where('ecosistema_laboral_id', 1)->first();

// Simular una conquista
$perfil->situacionesConquistadas()->syncWithoutDetaching([
    $sc->id => [
        'gradiente_autonomia'  => 'supervisado',
        'puntuacion_conquista' => 78.5,
        'intentos'             => 2,
        'fecha_conquista'      => now(),
    ]
]);

// Recalcular la calificación
app(App\Services\CalificacionService::class)->calcularYPersistir($perfil->fresh());
```

```bash
# 2. Verifica la ruta del docente
curl -s http://localhost:8000/docente/ecosistemas/1/analytics \
     -H "Cookie: laravel_session=TU_COOKIE"
# Debe devolver HTML con tres <canvas> y tres bloques <script>

# 3. Verifica que el EACAnalyticsService devuelve datos correctos
php artisan tinker

$svc = app(App\Services\EACAnalyticsService::class);
$eco = App\Models\EcosistemaLaboral::find(1);

$svc->rankingConquistas($eco);
// → ['labels' => ['SC-01', ...], 'data' => [3, ...], 'colores' => ['#22c55e', ...]]

$svc->distribucionGradiente($eco);
// → ['labels' => ['Asistido', ...], 'data' => [0, 0, 1, 0], 'colores' => [...]]

$svc->evolucionTemporal($eco, 4);
// → ['labels' => ['S15 (07/04)', ...], 'data' => [0, 0, 0, 1]]

$perfil = App\Models\PerfilHabilitacion::find(1);
$svc->radarHuella($perfil);
// → ['labels' => ['RA1', 'RA2'], 'data' => [35.2, 0.0], 'max' => 100]
```

---

## 6.10. Errores frecuentes y cómo resolverlos

### `Chart is not defined` en la consola del navegador

Chart.js no se ha cargado antes del script de la gráfica. Verifica que:

1. El layout base incluye `<script src="https://cdn.jsdelivr.net/npm/chart.js@..."></script>` **antes** de `@stack('scripts')`.
2. La vista usa `@push('scripts')` (no `@section`).
3. No hay un bloqueador de red que impida cargar el CDN.

### `Call to undefined method dataset()->backgroundColor()`

El encadenamiento de métodos en `ConsoleTVs/Charts` depende de que `dataset()` devuelva la instancia del dataset, no del chart. Asegúrate de que la llamada es:

```php
$chart
    ->dataset('Etiqueta', 'tipo', $data)
        ->backgroundColor('#...')   // ← sobre el dataset, no sobre $chart
        ->options([...]);           // ← options también sobre el dataset para estilos
```

Si llamas a `$chart->options([...])` (sin pasar antes por `->dataset()`), las opciones se aplican al nivel raíz del chart, que es el comportamiento correcto para opciones globales como `responsive` o `plugins`.

### Las barras horizontales no aparecen horizontales

Chart.js v4 eliminó el tipo `horizontalBar`. Si el paquete lo mapea internamente a `bar`, debes pasar `'indexAxis' => 'y'` dentro del bloque `options()` del nivel raíz del chart (no del dataset):

```php
$chart
    ->dataset('Etiqueta', 'bar', $data)
        ->backgroundColor([...]);

// options a nivel de chart (no de dataset)
$chart->options([
    'indexAxis' => 'y',
    // ...
]);
```

### El radar muestra `NaN` en algún eje

El `EACAnalyticsService::radarHuella()` delega en `CalificacionService::desglose()`. Si algún RA no tiene ningún CE asociado al ecosistema, su puntuación es `0.0` (no `null`), por lo que Chart.js puede representarlo. Si aun así aparece `NaN`, comprueba que `desglose_ra` no tiene valores `null` con:

```php
$desglose = app(App\Services\CalificacionService::class)->desglose($perfil);
dd($desglose['desglose_ra']);
```

---

**Unidad anterior ←** [Unidad 5: Evaluación y Huella de Talento](./05_evaluacion_huella_talento.md)

**Siguiente capítulo →** [Unidad 6.11: Verificación final](./06_11_verificacion_final.md)

**Siguiente unidad →** [Unidad 7: Autenticación con Sanctum + Keyrock](./07_autenticacion_sanctum_keyrock.md)
