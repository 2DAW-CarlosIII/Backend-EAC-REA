# Unidad 5: Evaluación y Huella de Talento

## Objetivos de esta unidad

Al finalizar esta unidad serás capaz de:

- Implementar el **`CalificacionService`**: cálculo ponderado de la calificación del módulo a partir del Gradiente de Autonomía, los pesos de SC↔CE, CE↔RA y RA↔módulo.
- Sustituir el cálculo provisional de la Unidad 3 (media simple) por el cálculo real en el `ConquistaController`.
- Construir el **`HuellaService`**: generación del payload JSON exportable y registro en la tabla `huellas_talento`.
- Exponer un endpoint de la API para generar y consultar la Huella de Talento de un estudiante.
- Entender la relación entre la calificación calculada y la entidad NGSI-LD que se publicará en Orion en la Unidad 7.

---

## 5.1. Fundamento: el modelo de calificación ponderada

La calificación de un módulo no es la media de las puntuaciones de conquista. Se calcula desde los CE hacia arriba, a través de tres niveles de pesos:

```
Calificación del módulo
    └── Suma ponderada de RA  (peso_porcentaje de cada RA / 100)
            └── Cada RA: suma ponderada de sus CE  (peso_porcentaje de cada CE / 100)
                    └── Cada CE: media de puntuaciones de las SCs que lo cubren,
                                 ponderada por peso_en_sc
                                 y escalada por el factor del Gradiente de Autonomía
```

### Factor del Gradiente de Autonomía

El gradiente no es solo una etiqueta: modula el valor real de la puntuación de conquista. Un estudiante que alcanza 90 puntos de forma `asistida` ha demostrado menos dominio real que uno que obtiene 80 de forma `autónoma`:

| Gradiente | Factor |
|-----------|--------|
| `asistido` | 0.60 |
| `guiado` | 0.75 |
| `supervisado` | 0.90 |
| `autonomo` | 1.00 |

La **puntuación efectiva** de una conquista es:

```
puntuacion_efectiva = puntuacion_conquista × factor_gradiente
```

### Ejemplo con el módulo piloto

Dado el estado `SC-01 (supervisado, 84.5) + SC-02 (autónomo, 91.0)`:

```
CE1a: cubierto por SC-01 → (84.5 × 0.90) × (30/100) = 22.82
CE1b: cubierto por SC-01 → (84.5 × 0.90) × (40/100) = 30.42
CE1c: cubierto por SC-01 (30%) + SC-02 (60%) →
      [(84.5×0.90)×30 + (91.0×1.00)×60] / 90 = [22.82 + 54.60] / 90 = 85.92
      → CE1c contribuye 85.92 × (30/100) = 25.78

RA1 = (CE1a×0.30 + CE1b×0.40 + CE1c×0.30) = (22.82 + 30.42 + 25.78) = 79.02

CE2a: cubierto por SC-02 → (91.0 × 1.00) × (40/100) = 36.40 → contribución: 36.40
CE2b: no conquistada → 0

RA2 = (CE2a×0.50 + CE2b×0.50) = 18.20

Calificación = RA1×0.40 + RA2×0.35 = 31.61 + 6.37 = 37.98   ← módulo no completado
```

Este resultado refleja que el módulo no está terminado: solo se han conquistado 2 de 3 SCs, y varios CE quedan sin cubrir.

---

**Unidad anterior ←** [Unidad 4: Motor de navegación: ZDP y recomendación](./04_motor_navegacion.md)

**Siguiente capítulo →** [Unidad 5.2: Servicio de calificación](./05_02_servicio_calificacion.md)

**Siguiente unidad →** [Unidad 6: Visualización del espacio competencial](./06_visualizacion_espacio_competencial.md)
