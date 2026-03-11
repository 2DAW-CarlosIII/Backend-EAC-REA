## 1.2. Instalación y configuración inicial

Si todavía no tienes el proyecto creado, este es el momento. Asegúrate de tener el entorno Laradock funcionando (ver Unidad 0, [sección 0.6](./00_contexto_arquitectura_eac.md#06-preparación-del-entorno-con-laradock)).

### Verificar la conexión con MariaDB

Desde el contenedor workspace de Laradock:

```bash
php artisan migrate:status
```

Si ves el mensaje `Migration table not found`, ejecuta:

```bash
php artisan migrate
```

Esto crea la tabla `migrations` y aplica las migraciones por defecto de Laravel (`users`, `password_reset_tokens`, `sessions`, `cache`, `jobs`).

---

**Unidad anterior ←** [Unidad 0: Contexto y arquitectura EAC](./00_contexto_arquitectura_eac.md)

  **Siguiente capítulo →** [Migraciones EAC: creación de tablas y relaciones](./01_03_migraciones.md)

**Siguiente unidad →** [Unidad 2: Frontend: layout y vistas Blade para el marketplace](./02_frontend_blade.md)
