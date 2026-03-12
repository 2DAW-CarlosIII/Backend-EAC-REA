## 2.11. Soluciones

### Vista docente

Se proporciona el resultado de aplicar `diff` entre el código original y el código modificado para mostrar la vista del docente sin errores y con el enlace a "Mi docencia" funcional:

```diff
diff --git a/app/Models/Role.php b/app/Models/Role.php
index 6685add..d500562 100644
--- a/app/Models/Role.php
+++ b/app/Models/Role.php
@@ -3,8 +3,14 @@
 namespace App\Models;
 
 use Illuminate\Database\Eloquent\Model;
+use Illuminate\Database\Eloquent\Relations\BelongsTo;
 
 class Role extends Model
 {
     protected $fillable = ['name', 'description'];
+
+    public function ecosistemaLaboral() : BelongsTo
+    {
+        return $this->belongsTo(EcosistemaLaboral::class, 'ecosistema_laboral_id');
+    }
 }
diff --git a/app/Models/User.php b/app/Models/User.php
index a9f125d..d02cf8b 100644
--- a/app/Models/User.php
+++ b/app/Models/User.php
@@ -48,7 +48,7 @@ protected function casts(): array
         ];
     }
 
-    public function roles(): BelongsToMany
+    public function userRoles(): BelongsToMany
     {
         return $this->belongsToMany(Role::class, 'user_roles')
                     ->withPivot('ecosistema_laboral_id')
@@ -82,4 +82,11 @@ public function perfilEn(EcosistemaLaboral $ecosistema): ?PerfilHabilitacion
                     ->where('ecosistema_laboral_id', $ecosistema->id)
                     ->first();
     }
+
+    // Método helper que consulta la relación roles y devuelve true/false
+    public function hasRole(string $role): bool
+    {
+        // Se usa la relación 'userRoles' definida en el modelo User
+        return $this->userRoles()->where('name', $role)->exists();
+    }
 }
```

### Vista estudiante

Se muestra el resultado de aplicar `diff` entre el código original y el código modificado para mostrar la vista del estudiante sin errores y con el enlace a "Mis módulos" funcional:

```diff
diff --git a/app/Http/Controllers/Estudiante/ModuloController.php b/app/Http/Controllers/Estudiante/ModuloController.php
new file mode 100644
index 0000000..9c4049e
--- /dev/null
+++ b/app/Http/Controllers/Estudiante/ModuloController.php
@@ -0,0 +1,34 @@
+<?php
+
+namespace App\Http\Controllers\Estudiante;
+
+use App\Http\Controllers\Controller;
+use App\Models\FamiliaProfesional;
+use App\Models\Modulo;
+use Illuminate\Http\Request;
+
+class ModuloController extends Controller
+{
+    /**
+     * Handle the incoming request.
+     */
+    public function __invoke(Request $request)
+    {
+        $familias = FamiliaProfesional::orderBy('nombre')->get();
+
+        $modulos = Modulo::with([
+                'cicloFormativo.familiaProfesional',
+                'ecosistemasLaborales' => fn($q) => $q->where('activo', true),
+            ])
+            ->whereHas('ecosistemasLaborales', fn($q) => $q->where('activo', true))
+            ->whereHas('matriculas', fn($q) => $q->where('estudiante_id', auth()->id()))
+            ->when($request->filled('familia'), fn($q) =>
+                $q->whereHas('cicloFormativo',
+                    fn($q2) => $q2->where('familia_profesional_id', $request->familia))
+            )
+            ->orderBy('codigo')
+            ->paginate(15);
+
+        return view('publico.modulos.index', compact('modulos', 'familias'));
+    }
+}
diff --git a/app/Models/Modulo.php b/app/Models/Modulo.php
index a8705a4..85c93c5 100644
--- a/app/Models/Modulo.php
+++ b/app/Models/Modulo.php
@@ -26,4 +26,9 @@ public function resultadosAprendizaje(): HasMany
     {
         return $this->hasMany(ResultadoAprendizaje::class);
     }
+
+    public function matriculas(): HasMany
+    {
+        return $this->hasMany(Matricula::class);
+    }
 }
diff --git a/routes/web.php b/routes/web.php
index 8a2649d..275b0e0 100644
--- a/routes/web.php
+++ b/routes/web.php
@@ -26,6 +26,7 @@
     ->group(function () {
         Route::get('/dashboard',          Estudiante\DashboardController::class)->name('dashboard');
         Route::get('/perfil/{perfil}',    Estudiante\PerfilController::class)->name('perfil.show');
+        Route::get('/modulos',         Estudiante\ModuloController::class)->name('modulos.index');
     });
 
 // ─── Rutas del docente ────────────────────────────────────────────────────────
```

---
**Unidad anterior ←** [Unidad 1: Modelado de datos EAC en Laravel](./01_modelado_datos_eac.md)

**Siguiente unidad →** [Unidad 3: API REST EAC](./03_api_rest_eac.md)