## 1.10. Verificación con Tinker

Comprueba que el esquema y los datos de prueba están correctamente relacionados:

```bash
php artisan tinker
```

```php
// ¿Cuántas SCs tiene el ecosistema piloto?
App\Models\EcosistemaLaboral::first()->situacionesCompetencia()->count();
// → 3

// ¿Qué CE cubre SC-03?
$sc03 = App\Models\SituacionCompetencia::where('codigo', 'SC-03')->first();
$sc03->criteriosEvaluacion()->pluck('codigo');
// → ['b', 'a', 'b']

// ¿Qué SCs son prerequisito de SC-03?
$sc03->prerequisitos()->pluck('codigo');
// → ['SC-01', 'SC-02']

// ¿El estudiante tiene perfil creado?
App\Models\User::where('email', 'estudiante@backend-eac.test')->first()->perfilesHabilitacion()->with('ecosistemaLaboral')->first();
// → PerfilHabilitacion { ecosistema_laboral: EcosistemaLaboral { nombre: 'Técnicas básicas de merchandising' } }

// ¿Qué SCs ha conquistado? (ninguna aún)
App\Models\PerfilHabilitacion::first()->situacionesConquistadas()->count();
// → 0
```

---

## ✅ Verificación final de la Unidad 1

Antes de continuar con la Unidad 2, confirma que:

- [ ] `php artisan migrate:status` muestra las 16 migraciones en estado `Ran`.
- [ ] `php artisan db:seed` completa sin errores.
- [ ] En Tinker, `EcosistemaLaboral::first()->situacionesCompetencia()->count()` devuelve `3`.
- [ ] `SituacionCompetencia::where('codigo', 'SC-03')->first()->prerequisitos()->pluck('codigo')` devuelve `['SC-01', 'SC-02']`.
- [ ] `SituacionCompetencia::where('codigo', 'SC-03')->first()->criteriosEvaluacion()->count()` devuelve `3`.
- [ ] Existen usuarios `docente@backend-eac.test` y `estudiante@backend-eac.test` con sus roles y el perfil de habilitación creado.
- [ ] Puedes explicar la diferencia entre `perfil_situacion` y `perfiles_habilitacion`.
- [ ] Puedes explicar por qué `sc_criterios_evaluacion` es una relación many-to-many y no una FK directa.

---

## 📖 Referencias

- [Repositorio del caso de uso VFDS-EAC](https://github.com/C3-VFDS/use_case_pkst)
- [Laravel Migrations](https://laravel.com/docs/migrations)
- [Laravel Eloquent Relationships](https://laravel.com/docs/eloquent-relationships)
- [Laravel Factories](https://laravel.com/docs/eloquent-factories)
- [Laravel Seeders](https://laravel.com/docs/seeding)

---

**Unidad anterior ←** [Unidad 0: Contexto y arquitectura EAC](./00_contexto_arquitectura_eac.md)

  **Siguiente capítulo →** [Soluciones](./01_11_soluciones.md)

**Siguiente unidad →** [Unidad 2: Frontend: layout y vistas Blade para el marketplace](./02_frontend_blade.md)
