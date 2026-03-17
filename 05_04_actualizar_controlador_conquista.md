## 5.4. Actualizar controlador de conquista

Sustituye el cálculo provisional de la Unidad 3 por el `CalificacionService`:

```php
// app/Http/Controllers/Api/V1/Docente/ConquistaController.php
// Añadir inyección en el constructor

use App\Services\CalificacionService;

public function __construct(
    private readonly CalificacionService $calificacionService,
) {}
```

Dentro del `DB::transaction`, reemplaza las dos líneas de media simple:

```php
// ANTES (Unidad 3 — eliminar):
$nuevaCalificacion = $perfil->situacionesConquistadas()
    ->avg('perfil_situacion.puntuacion_conquista');
$perfil->update(['calificacion_actual' => round($nuevaCalificacion, 2)]);

// DESPUÉS (Unidad 5 — sustituir por):
$this->calificacionService->calcularYPersistir($perfil->fresh());
```

El `fresh()` es necesario porque el `attach` o `updateExistingPivot` previo no actualiza la instancia `$perfil` en memoria: hay que recargarla desde la base de datos para que `loadMissing` encuentre la conquista recién registrada.

---

**Unidad anterior ←** [Unidad 4: Motor de navegación: ZDP y recomendación](./04_motor_navegacion.md)

**Siguiente capítulo →** [Unidad 5.5: Servicio de huella de talento](./05_05_servicio_huella_talento.md)

**Siguiente unidad →** [Unidad 6: Visualización del espacio competencial](./06_visualizacion_espacio_competencial.md)
