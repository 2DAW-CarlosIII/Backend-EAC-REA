# Unidad 6: Visualización del espacio competencial

## Objetivos de esta unidad

Al finalizar esta unidad serás capaz de:

- Instalar y configurar la librería **`ConsoleTVs/Charts`** (v6.x) en un proyecto Laravel con Blade.
- Construir el **`EACAnalyticsService`**: un servicio de dominio que agrega y transforma los datos del espacio competencial para su representación gráfica.
- Implementar cuatro gráficas EAC: **barras** (ranking de SCs por conquistas), **doughnut** (distribución del Gradiente de Autonomía), **radar** (Huella de Talento individual) y **líneas** (evolución temporal del aula).
- Crear los controladores invocables `Docente/AnalyticsController` y `Estudiante/HuellaRadarController` que inyectan el servicio y pasan datos a las vistas.
- Integrar las gráficas en las vistas Blade del docente y del estudiante generadas en la Unidad 2.
- Razonar sobre la diferencia entre una gráfica que agrega datos de grupo (docente) y una que visualiza el estado individual (estudiante).

---

## 6.1. Fundamento: qué visualizar y para quién

Antes de instalar ninguna librería conviene precisar qué preguntas responde cada gráfica y a qué usuario van dirigidas.

### Gráficas del docente (panel de ecosistema)

El docente necesita una visión de conjunto del aula: qué SCs están conquistando los estudiantes, con qué nivel de autonomía, y cómo evoluciona el grupo a lo largo del tiempo.

| Gráfica | Pregunta | Tipo |
|---------|----------|------|
| Ranking de SCs | ¿Cuáles son las SCs más y menos conquistadas del ecosistema? | Barras horizontales |
| Distribución de Gradiente | ¿Con qué nivel de autonomía conquistan las SCs los estudiantes? | Doughnut |
| Evolución temporal | ¿Cómo crece el número de conquistas semana a semana? | Líneas |

### Gráfica del estudiante (panel de módulo)

El estudiante necesita una representación individualizada: un radar que refleje cómo cubre cada Resultado de Aprendizaje del módulo, calculado a partir de su `CalificacionService::desglose()`.

| Gráfica | Pregunta | Tipo |
|---------|----------|------|
| Huella de Talento | ¿En qué RA tengo mayor y menor cobertura competencial? | Radar / Spider |

---

**Unidad anterior ←** [Unidad 5: Evaluación y Huella de Talento](./05_evaluacion_huella_talento.md)

**Siguiente capítulo →** [Unidad 6.2: Instalación de librerías gráficas](./06_02_instala_librerias_graficas.md)

**Siguiente unidad →** [Unidad 7: Autenticación con Sanctum + Keyrock](./07_autenticacion_sanctum_keyrock.md)
