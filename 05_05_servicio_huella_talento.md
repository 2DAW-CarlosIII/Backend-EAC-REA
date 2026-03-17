## 5.5. Servicio de Huella de Talento

La Huella de Talento es el snapshot exportable del estado competencial del estudiante. Su `payload` JSON está diseñado para ser directamente mapeado a la entidad NGSI-LD `PerfilHabilitacion` en la Unidad 7.

```php
// app/Services/HuellaService.php

namespace App\Services;

use App\Models\HuellaTalento;
use App\Models\PerfilHabilitacion;

class HuellaService
{
    public function __construct(
        private readonly CalificacionService $calificacionService,
    ) {}

    /**
     * Genera la Huella de Talento del estudiante en un ecosistema,
     * la persiste en huellas_talento y la devuelve.
     */
    public function generar(PerfilHabilitacion $perfil): HuellaTalento
    {
        $perfil->loadMissing([
            'estudiante:id,name',
            'ecosistemaLaboral.modulo.cicloFormativo.familiaProfesional',
            'ecosistemaLaboral.modulo.resultadosAprendizaje.criteriosEvaluacion',
            'situacionesConquistadas.criteriosEvaluacion',
            'situacionesConquistadas.prerequisitos:id,codigo',
        ]);

        $desglose = $this->calificacionService->desglose($perfil);

        $payload = [
            // Identificación NGSI-LD
            'ngsi_ld_id' => sprintf(
                'urn:ngsi-ld:PerfilHabilitacion:estudiante-%d-ecosistema-%d',
                $perfil->estudiante_id,
                $perfil->ecosistema_laboral_id
            ),
            '@context' => 'https://vfds.example.org/ngsi-ld/eac-context.jsonld',

            // Contexto curricular
            'modulo' => [
                'codigo' => $perfil->ecosistemaLaboral->modulo->codigo,
                'nombre' => $perfil->ecosistemaLaboral->modulo->nombre,
                'ciclo'  => $perfil->ecosistemaLaboral->modulo->cicloFormativo->nombre,
                'familia_profesional' =>
                    $perfil->ecosistemaLaboral->modulo->cicloFormativo->familiaProfesional->nombre,
            ],

            'ecosistema' => [
                'id'     => $perfil->ecosistema_laboral_id,
                'codigo' => $perfil->ecosistemaLaboral->codigo,
                'nombre' => $perfil->ecosistemaLaboral->nombre,
            ],

            // Resultado global
            'calificacion' => $desglose['calificacion_total'],

            // Situaciones conquistadas con gradiente
            'situaciones_conquistadas' => $perfil->situacionesConquistadas
                ->map(fn($sc) => [
                    'codigo'              => $sc->codigo,
                    'titulo'              => $sc->titulo,
                    'gradiente_autonomia' => $sc->pivot->gradiente_autonomia,
                    'puntuacion_conquista' => (float) $sc->pivot->puntuacion_conquista,
                    'puntuacion_efectiva' => (float) $sc->pivot->puntuacion_conquista
                        * (CalificacionService::FACTORES[$sc->pivot->gradiente_autonomia] ?? 1.0),
                    'intentos'            => $sc->pivot->intentos,
                    'fecha_conquista'     => $sc->pivot->fecha_conquista,
                ])
                ->values(),

            // Desglose por RA y CE (trazabilidad curricular)
            'desglose_curricular' => $desglose['desglose_ra'],

            // Metadatos de la huella
            'generada_en' => now()->toIso8601String(),
            'version'     => '1.0',
        ];

        return HuellaTalento::create([
            'estudiante_id'         => $perfil->estudiante_id,
            'ecosistema_laboral_id' => $perfil->ecosistema_laboral_id,
            'payload'               => $payload,
            'generada_en'           => now(),
        ]);
    }

    /**
     * Recupera la huella más reciente del estudiante en un ecosistema,
     * o genera una nueva si no existe ninguna.
     */
    public function ultimaOGenerar(PerfilHabilitacion $perfil): HuellaTalento
    {
        return HuellaTalento::where('estudiante_id', $perfil->estudiante_id)
            ->where('ecosistema_laboral_id', $perfil->ecosistema_laboral_id)
            ->latest('generada_en')
            ->first()
            ?? $this->generar($perfil);
    }
}
```

Para que `HuellaService` pueda acceder a `FACTORES` como constante pública, declárala como `public` en `CalificacionService`:

```php
// app/Services/CalificacionService.php  — cambiar visibilidad
public const FACTORES = [
    'asistido'    => 0.60,
    'guiado'      => 0.75,
    'supervisado' => 0.90,
    'autonomo'    => 1.00,
];
```

---

**Unidad anterior ←** [Unidad 4: Motor de navegación: ZDP y recomendación](./04_motor_navegacion.md)

**Siguiente capítulo →** [Unidad 5.6: Endpoints de la Huella de Talento](./05_06_endpoints_huella_talento.md)

**Siguiente unidad →** [Unidad 6: Visualización del espacio competencial](./06_visualizacion_espacio_competencial.md)
