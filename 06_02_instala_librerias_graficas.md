## 6.2. Instalación de ConsoleTVs/Charts

### 6.2.1. Paquete PHP

```bash
composer update
composer require consoletvs/charts:"6.*"
```

### 6.2.2. Publicar el ServiceProvider

A partir de Laravel 5.5 los paquetes se registran automáticamente, pero _Charts_ requiere publicar su archivo de configuración:

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

Verifica la instalación comprobando que no hay errores de consola al cargar la _landing page_ del proyecto:

---

**Unidad anterior ←** [Unidad 5: Evaluación y Huella de Talento](./05_evaluacion_huella_talento.md)

**Siguiente capítulo →** [Unidad 6.3: Servicio de analítica](./06_03_servicio_analitica.md)

**Siguiente unidad →** [Unidad 7: Autenticación con Sanctum + Keyrock](./07_autenticacion_sanctum_keyrock.md)
