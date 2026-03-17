## 5.2. Servicio de calificación

El `CalificacionService` es el núcleo del sistema de evaluación. Encapsula toda la lógica de cálculo de la calificación ponderada del módulo, considerando:
- El gradiente de autonomía de cada conquista (asistido, guiado, supervisado, autónomo).
- El peso de cada CE dentro de su RA.
- El peso de cada RA dentro del módulo.

```php
// app/Services/CalificacionService.php

namespace App\Services;

use App\Models\PerfilHabilitacion;
use Illuminate\Support\Collection;

class CalificacionService
{
    /**
     * Factores de escala por gradiente de autonomía.
     */
    public const FACTORES = [
        'asistido'    => 0.60,
        'guiado'      => 0.75,
        'supervisado' => 0.90,
        'autonomo'    => 1.00,
    ];

    /**
     * Calcula y persiste la calificación ponderada del módulo
     * para el perfil de habilitación indicado.
     *
     * Devuelve el valor calculado (0-10 en escala decimal).
     */
    public function calcularYPersistir(PerfilHabilitacion $perfil): float
    {
        $calificacion = $this->calcular($perfil);
        $perfil->update(['calificacion_actual' => $calificacion]);
        return $calificacion;
    }

    /**
     * Calcula la calificación sin persistirla.
     * Útil para previsualización y tests.
     */
    public function calcular(PerfilHabilitacion $perfil): float
    {
        $perfil->loadMissing([
            'situacionesConquistadas.criteriosEvaluacion',
            'ecosistemaLaboral.modulo.resultadosAprendizaje.criteriosEvaluacion'
                . '.situacionesCompetencia',
        ]);

        $modulo = $perfil->ecosistemaLaboral->modulo;
        $ras    = $modulo->resultadosAprendizaje;

        if ($ras->isEmpty()) {
            return 0.0;
        }

        // Índice de conquistas: ce_id → ['puntuacion_efectiva', 'peso_en_sc']
        $conquistasIndexadas = $this->indexarConquistas($perfil->situacionesConquistadas);

        $sumaPonderadaRas = 0.0;
        $sumaPesosRas     = 0.0;

        foreach ($ras as $ra) {
            $pesoRa = (float) $ra->peso_porcentaje;
            if ($pesoRa <= 0) {
                $pesoRa = 1.0;
            }

            $puntuacionRa = $this->calcularPuntuacionRa($ra, $conquistasIndexadas);

            $sumaPonderadaRas += $puntuacionRa * ($pesoRa / 100);
            $sumaPesosRas     += $pesoRa / 100;
        }

        if ($sumaPesosRas <= 0) {
            return 0.0;
        }

        // Normalizar al rango 0-10
        $calificacion = ($sumaPonderadaRas / $sumaPesosRas) / 10;

        return round(min(10.0, max(0.0, $calificacion)), 2);
    }

    /**
     * Desglose completo: calificación por RA y por CE.
     * Usado por la Huella de Talento y la visualización de la Unidad 8.
     *
     * @return array{
     *   calificacion_total: float,
     *   desglose_ra: array,
     * }
     */
    public function desglose(PerfilHabilitacion $perfil): array
    {
        $perfil->loadMissing([
            'situacionesConquistadas.criteriosEvaluacion',
            'ecosistemaLaboral.modulo.resultadosAprendizaje.criteriosEvaluacion'
                . '.situacionesCompetencia',
        ]);

        $modulo              = $perfil->ecosistemaLaboral->modulo;
        $conquistasIndexadas = $this->indexarConquistas($perfil->situacionesConquistadas);

        $desglose = [];
        $sumaPonderadaRas = 0.0;
        $sumaPesosRas     = 0.0;

        foreach ($modulo->resultadosAprendizaje as $ra) {
            $pesoRa       = (float) $ra->peso_porcentaje > 0 ? (float) $ra->peso_porcentaje : 1.0;
            $desglloseCes = $this->calcularDesgloseCes($ra, $conquistasIndexadas);
            $puntuacionRa = $desglloseCes['puntuacion_ra'];

            $sumaPonderadaRas += $puntuacionRa * ($pesoRa / 100);
            $sumaPesosRas     += $pesoRa / 100;

            $desglose[] = [
                'ra'              => $ra->codigo,
                'descripcion'     => $ra->descripcion,
                'peso'            => $pesoRa,
                'puntuacion'      => round($puntuacionRa, 2),
                'criterios'       => $desglloseCes['criterios'],
            ];
        }

        $calificacionTotal = $sumaPesosRas > 0
            ? round(min(10.0, ($sumaPonderadaRas / $sumaPesosRas) / 10), 2)
            : 0.0;

        return [
            'calificacion_total' => $calificacionTotal,
            'desglose_ra'        => $desglose,
        ];
    }

    // ─── Helpers privados ────────────────────────────────────────────────────

    /**
     * Construye un índice ce_id → [['puntuacion_efectiva', 'peso_en_sc'], ...]
     * para búsqueda O(1) durante el cálculo.
     */
    private function indexarConquistas(Collection $situacionesConquistadas): array
    {
        $indice = [];

        foreach ($situacionesConquistadas as $sc) {
            $factor = self::FACTORES[$sc->pivot->gradiente_autonomia] ?? 1.0;
            $puntEfectiva = (float) $sc->pivot->puntuacion_conquista * $factor;

            foreach ($sc->criteriosEvaluacion as $ce) {
                $indice[$ce->id][] = [
                    'puntuacion_efectiva' => $puntEfectiva,
                    'peso_en_sc'          => (float) $ce->pivot->peso_en_sc,
                ];
            }
        }

        return $indice;
    }

    /**
     * Calcula la puntuación de un RA (0-100) a partir del índice de conquistas.
     */
    private function calcularPuntuacionRa(
        \App\Models\ResultadoAprendizaje $ra,
        array $conquistasIndexadas
    ): float {
        return $this->calcularDesgloseCes($ra, $conquistasIndexadas)['puntuacion_ra'];
    }

    /**
     * Calcula el desglose de CE y la puntuación agregada del RA.
     */
    private function calcularDesgloseCes(
        \App\Models\ResultadoAprendizaje $ra,
        array $conquistasIndexadas
    ): array {
        $sumaPonderadaCes = 0.0;
        $sumaPesosCes     = 0.0;
        $criterios        = [];

        foreach ($ra->criteriosEvaluacion as $ce) {
            $pesoCe = (float) $ce->peso_porcentaje;
            if ($pesoCe <= 0) {
                $pesoCe = 1.0;
            }

            $puntuacionCe = $this->calcularPuntuacionCe($ce->id, $conquistasIndexadas);

            $sumaPonderadaCes += $puntuacionCe * ($pesoCe / 100);
            $sumaPesosCes     += $pesoCe / 100;

            $criterios[] = [
                'ce'         => $ce->codigo,
                'descripcion' => $ce->descripcion,
                'peso'       => $pesoCe,
                'puntuacion' => round($puntuacionCe, 2),
                'cubierto'   => isset($conquistasIndexadas[$ce->id]),
            ];
        }

        $puntuacionRa = $sumaPesosCes > 0
            ? $sumaPonderadaCes / $sumaPesosCes
            : 0.0;

        return [
            'puntuacion_ra' => $puntuacionRa,
            'criterios'     => $criterios,
        ];
    }

    /**
     * Calcula la puntuación de un CE (0-100).
     * Si varias SCs cubren el mismo CE, se pondera por peso_en_sc.
     */
    private function calcularPuntuacionCe(int $ceId, array $conquistasIndexadas): float
    {
        if (!isset($conquistasIndexadas[$ceId])) {
            return 0.0;
        }

        $entradas      = $conquistasIndexadas[$ceId];
        $sumaPonderada = 0.0;
        $sumaPesos     = 0.0;

        foreach ($entradas as $entrada) {
            $sumaPonderada += $entrada['puntuacion_efectiva'] * $entrada['peso_en_sc'];
            $sumaPesos     += $entrada['peso_en_sc'];
        }

        return $sumaPesos > 0 ? $sumaPonderada / $sumaPesos : 0.0;
    }
}
```

---

## 5.3. Registro del servicio

Vamos a registrar los dos servicios que se definen en esta unidad (`CalificacionService` y `HuellaService`) como singletons en el contenedor de servicios de Laravel. Esto garantiza que se reutilice la misma instancia durante toda la petición, lo cual es eficiente dado que no mantienen estado mutable.

Añádelos a los que se definieron en la Unidad 4: `GrafoService` y `RecomendacionService`.

```php
// app/Providers/AppServiceProvider.php  (añadir en register())

use App\Services\CalificacionService;
use App\Services\HuellaService;         // se define en 5.5

$this->app->singleton(CalificacionService::class);
$this->app->singleton(HuellaService::class);
```

---

**Unidad anterior ←** [Unidad 4: Motor de navegación: ZDP y recomendación](./04_motor_navegacion.md)

**Siguiente capítulo →** [Unidad 5.4: Actualizar controlador de conquista](./05_04_actualizar_controlador_conquista.md)

**Siguiente unidad →** [Unidad 6: Visualización del espacio competencial](./06_visualizacion_espacio_competencial.md)
