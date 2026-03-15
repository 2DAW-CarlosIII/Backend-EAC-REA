# Unidad 4: Motor de navegación: ZDP y recomendación

## Objetivos de esta unidad

Al finalizar esta unidad serás capaz de:

- Implementar el algoritmo de **Zona de Despliegue Proximal (ZDP)**: calcular qué SCs son accesibles dado el perfil actual de un estudiante.
- Construir el **`GrafoService`**: validación de acíclicidad del grafo de precedencia y ordenación topológica.
- Construir el **`RecomendacionService`**: seleccionar la SC más adecuada para acometer a continuación desde la ZDP.
- Exponer la ZDP a través de un endpoint de la API y consumirla en las vistas Blade del estudiante.
- Entender por qué el cálculo de la ZDP debe vivir en un servicio de dominio y no en el controlador ni en el modelo.

---

**Unidad anterior ←** [Unidad 3: API REST EAC](./03_api_rest_eac.md)

**Siguiente capítulo →** [Implementación de la ZDP](./04_01_fundamento_conceptual_zdp.md)

**Siguiente unidad →** [Unidad 5: Evaluación y seguimiento](./05_evaluacion_seguimiento.md)
