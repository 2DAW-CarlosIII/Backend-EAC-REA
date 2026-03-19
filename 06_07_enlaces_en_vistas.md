## 6.7. Añadir los enlaces de acceso en las vistas existentes

### 6.7.1. Panel del docente — ecosistema

En la vista `docente/ecosistemas/show.blade.php` (creada en la Unidad 2), añade un botón de acceso a la analítica en el encabezado:

```html
{{-- dentro de la cabecera del ecosistema --}}
<a href="{{ route('docente.ecosistemas.analytics', $ecosistema) }}"
   class="btn btn-outline-primary btn-sm">
    📊 Ver analítica
</a>
```

### 6.7.2. Panel del estudiante — módulo

En la vista `estudiante/modulo.blade.php` (creada en la Unidad 2), añade el acceso a la Huella en la barra de acciones del perfil:

```html
{{-- dentro del panel de progreso del estudiante --}}
<a href="{{ route('estudiante.huella-radar', $perfil->ecosistemaLaboral) }}"
   class="btn btn-outline-indigo btn-sm">
    🎯 Ver mi Huella de Talento
</a>
```

---

**Unidad anterior ←** [Unidad 5: Evaluación y Huella de Talento](./05_evaluacion_huella_talento.md)

**Siguiente capítulo →** [Unidad 6.8: Verificación con Tinker y cURL](./06_08_verificacion_tinker_curl.md)

**Siguiente unidad →** [Unidad 7: Autenticación con Sanctum + Keyrock](./07_autenticacion_sanctum_keyrock.md)
