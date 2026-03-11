## 1.1. El esquema completo: visión de conjunto

Antes de crear una sola migración, necesitas tener clara la imagen completa del esquema. Este es el diagrama ER del backend-EAC:

```
JERARQUÍA CURRICULAR (referencia normativa)
───────────────────────────────────────────────────────────────────────
familias_profesionales
  └──► ciclos_formativos
         └──► modulos                (= módulo del título, sin docente ni curso)
                └──► ecosistemas_laborales  (= dominio competencial del módulo)
                ├──► resultados_aprendizaje (RA del currículo oficial)
                        └──► criterios_evaluacion (CE del currículo oficial)
                │                 ▲
                │           sc_criterios_evaluacion (pivot many:many)
                │                 ▼
                └──► situaciones_competencia (SC)
                       ├──► nodos_requisito  (conocimientos/habilidades)
                       └──► sc_precedencia   (tabla de adyacencia del grafo)

USUARIOS Y ROLES
───────────────────────────────────────────────────────────────────────
users
  ├──► roles  (via user_roles)
  ├──► matriculas  ──► modulos
  └──► perfiles_habilitacion  ──► ecosistemas_laborales
         └──► perfil_situacion  (pivot, incluye gradiente_autonomia)
                                ──► situaciones_competencia

EVALUACIÓN Y TRAZABILIDAD
───────────────────────────────────────────────────────────────────────
users ──► huellas_talento ──► ecosistemas_laborales
```

### Las 16 tablas del sistema

| # | Tabla | Tipo | Origen |
|---|-------|------|--------|
| 1 | `familias_profesionales` | Referencia curricular | Del e-portfolio (sin cambios) |
| 2 | `ciclos_formativos` | Referencia curricular | Del e-portfolio (sin cambios) |
| 3 | `modulos` | Referencia curricular | Adapta `modulos_formativos` (sin `docente_id` ni `curso_escolar`) |
| 4 | `ecosistemas_laborales` | Entidad raíz EAC | Nueva (FK apunta a `modulos`) |
| 5 | `resultados_aprendizaje` | Trazabilidad curricular | Del e-portfolio (FK cambia) |
| 6 | `criterios_evaluacion` | Trazabilidad curricular | Del e-portfolio (FK cambia) |
| 7 | `situaciones_competencia` | Núcleo EAC | Adapta `tareas` |
| 8 | `sc_criterios_evaluacion` | Pivot SC ↔ CE | Nueva (sustituye `criterios_tareas`) |
| 9 | `nodos_requisito` | Núcleo EAC | Nueva |
| 10 | `sc_precedencia` | Grafo de Precedencia | Nueva |
| 11 | `users` | Usuarios | Del e-portfolio (sin cambios) |
| 12 | `roles` / `user_roles` | Roles y contexto | Del e-portfolio (FK cambia) |
| 13 | `matriculas` | Acceso al ecosistema | Del e-portfolio (FK cambia) |
| 14 | `perfiles_habilitacion` | Estado competencial | Nueva |
| 15 | `perfil_situacion` | Pivot perfil ↔ SC | Nueva |
| 16 | `huellas_talento` | Exportable VFDS | Nueva |

---

**Unidad anterior ←** [Unidad 0: Contexto y arquitectura EAC](./00_contexto_arquitectura_eac.md)

  **Siguiente capítulo →** [Instalación inicial del backend-EAC](./01_02_instalacion_inicial.md)

**Siguiente unidad →** [Unidad 2: Frontend: layout y vistas Blade para el marketplace](./02_frontend_blade.md)
