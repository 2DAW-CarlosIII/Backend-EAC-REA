## 4.1. Fundamento conceptual

### La Zona de Despliegue Proximal

La **ZDP** de un estudiante en un ecosistema laboral es el subconjunto de SCs que cumplen simultáneamente dos condiciones:

1. **No han sido conquistadas** aún por el estudiante.
2. **Todos sus prerequisitos** han sido conquistados.

Formalmente, dado el grafo dirigido acíclico (DAG) `G = (V, E)` donde `V` son las SCs y `E ⊆ V × V` es la relación de precedencia, y dado el conjunto `C` de SCs conquistadas por el estudiante:

```
ZDP(C) = { sc ∈ V \ C  |  prerequisitos(sc) ⊆ C }
```

Para el módulo piloto, con el grafo `SC-01 → SC-03` y `SC-02 → SC-03`, el estado inicial (`C = ∅`) produce:

```
ZDP(∅)         = { SC-01, SC-02 }       ← sin prerequisitos → disponibles
ZDP({SC-01})   = { SC-02 }              ← SC-03 aún bloqueada
ZDP({SC-01, SC-02}) = { SC-03 }         ← todos los prerequisitos cubiertos
ZDP({SC-01, SC-02, SC-03}) = ∅          ← ecosistema completado
```

### El papel de la recomendación

La ZDP puede contener varias SCs al mismo tiempo. El **`RecomendacionService`** aplica una heurística sobre la ZDP para sugerir cuál abordar primero. Los criterios por orden de prioridad son:

1. **Menor `nivel_complejidad`** — empezar por lo más asequible.
2. **Mayor cobertura de CE pendientes** — maximizar el avance en trazabilidad curricular.
3. **Mayor número de SCs que desbloquea** — avanzar por el camino que abre más opciones.

---

**Unidad anterior ←** [Unidad 3: API REST EAC](./03_api_rest_eac.md)

**Siguiente capítulo →** [Servicios de la ZDP](./04_02_servicios_zdp.md)

**Siguiente unidad →** [Unidad 5: Evaluación y seguimiento](./05_evaluacion_seguimiento.md)
