# Unidad 2: Frontend: layout y vistas Blade para el marketplace

## Objetivos de esta unidad

Al finalizar esta unidad serás capaz de:

- Construir el **layout principal** de la aplicación con Blade y Tailwind CSS, coherente con la imagen del VFDS.
- Implementar las **vistas públicas del marketplace**: catálogo de módulos y ficha de ecosistema, accesibles sin autenticación.
- Implementar el **panel del estudiante**: mis matrículas, mi perfil de habilitación y mis SCs disponibles.
- Implementar el **panel del docente**: gestión del ecosistema asignado, vista de progreso del grupo.
- Organizar las **rutas** con grupos de middleware y nombrado consistente.
- Entender la diferencia entre datos que se publican al VFDS (catálogo público) y datos que permanecen internos (perfiles individuales).

---

## 2.1. Arquitectura de vistas: tres capas de acceso

El backend-EAC expone tres capas de vistas con distintos requisitos de autenticación:

```
PÚBLICO (sin autenticación)
───────────────────────────────────────────────────────────────────────
/                          Portada y presentación del VFDS-EAC
/modulos                   Catálogo de módulos con ecosistemas activos
/modulos/{modulo}          Ficha del módulo: descripción, RA, ecosistema
/ecosistemas/{ecosistema}  Ficha del ecosistema: SCs públicas, grafo

ESTUDIANTE (autenticado con rol estudiante)
───────────────────────────────────────────────────────────────────────
/estudiante/dashboard      Resumen: matrículas, progreso, ZDP
/estudiante/modulos        Mis módulos matriculados
/estudiante/perfil/{id}    Mi perfil de habilitación en un ecosistema

DOCENTE (autenticado con rol docente)
───────────────────────────────────────────────────────────────────────
/docente/dashboard         Resumen: ecosistemas gestionados, grupos
/docente/ecosistemas/{id}  Vista de gestión del ecosistema
/docente/progreso/{id}     Progreso del grupo en un ecosistema
```

> Como en las vistas se utilizan rutas de autenticación (`route('login')`, `route('register')`) y algunas rutas requieren autenticación, es necesario incluir el sistema de autenticación de _Breeze_ para poder probar las vistas generadas en esta unidad. Ejecuta los siguientes comandos para generar las rutas, controladores y vistas de login y registro:

```bash
composer require laravel/breeze --dev
php artisan breeze:install
```

Elige las siguientes opciones en el proceso de instalación:

```
 ┌ Which Breeze stack would you like to install? ───────────────┐
 │ › ● Blade with Alpine                                           │
 │   ○ Livewire (Volt Class API) with Alpine                       │
 │   ○ Livewire (Volt Functional API) with Alpine                  │
 │   ○ React with Inertia                                          │
 │   ○ Vue with Inertia                                            │
 │   ○ API only                                                    │
 └───────────────────────────────────────────────────────┘

 ┌ Would you like dark mode support? ─────────────────────────┐
 │ ○ Yes / ● No                                                    │
 └───────────────────────────────────────────────────────┘

 ┌ Which testing framework do you prefer? ─────────────────────┐
 │ PHPUnit                                                         │
 └───────────────────────────────────────────────────────┘
```

---

**Unidad anterior ←** [Unidad 1: Modelado de datos EAC en Laravel](./01_modelado_datos_eac.md)

  **Siguiente capítulo →** [Implementación de Tailwind CSS](./02_02_tailwind.md)

**Siguiente unidad →** [Unidad 3: API REST EAC](./03_api_rest_eac.md)
