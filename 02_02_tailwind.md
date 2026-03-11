## 2.2. Adaptación de Tailwind CSS

En primer lugar, asegúrate de tener la última versión de `node` y `npm` para evitar problemas de compatibilidad. [notas de actualización](https://nodejs.org/en/download/).

Modifica el fichero `resources/css/app.css` con el siguiente contenido:

```css
@tailwind base;
@tailwind components;
@tailwind utilities;

@source '../../vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php';
@source '../../storage/framework/views/*.php';
@source '../**/*.blade.php';
@source '../**/*.js';

@theme {
    --font-sans: 'Instrument Sans', ui-sans-serif, system-ui, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji',
        'Segoe UI Symbol', 'Noto Color Emoji';

    --color-eac-50:  #f0f4ff;
    --color-eac-500: #4f6ef7;
    --color-eac-700: #3451d1;
    --color-eac-900: #1e2f8a;
}
```

Compila los assets:

```bash
npm run dev   # desarrollo con HMR
npm run build # únicamente para producción
```

---

**Unidad anterior ←** [Unidad 1: Modelado de datos EAC en Laravel](./01_modelado_datos_eac.md)

  **Siguiente capítulo →** [Layouts con Blade y Tailwind CSS](./02_03_layouts.md)

**Siguiente unidad →** [Unidad 3: API REST EAC](./03_api_rest_eac.md)
