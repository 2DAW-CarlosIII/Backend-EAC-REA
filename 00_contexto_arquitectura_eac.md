# Unidad 0: Contexto y Arquitectura del Backend EAC

Esta unidad tiene como objetivo situarte en el contexto del proyecto, familiarizarte con el vocabulario esencial del Modelo EAC y entender la arquitectura técnica del sistema que vas a construir. Es fundamental que domines estos conceptos antes de empezar a escribir código, porque toda la práctica se basa en ellos.

No obstante, te ofrecemos [una presentación](documentos/backend_eac_presentacion.html) que puedes revisar para tener una visión general rápida antes de profundizar en el texto. Si quieres pasar directamente a la parte técnica, puedes saltar al [apartado 0.6](#06-preparación-del-entorno-con-laradock), donde te guiaremos para preparar tu entorno de desarrollo con Laradock.

## Objetivos de esta unidad

Al finalizar esta unidad serás capaz de:

- Explicar qué es el **Vocational Federated Dataspace (VFDS)** y qué papel juega el Backend EAC dentro de él.
- Identificar y definir con precisión el **vocabulario del ecosistema EAC**: SC, Grafo de Precedencia, Perfil de Habilitación, ZDP, Umbral de Maestría, Gradiente de Autonomía y Huella de Talento.
- Comprender la relación entre el currículo oficial de FP (RA, CE) y la arquitectura técnica del sistema que vas a construir.
- Reconocer los componentes FIWARE con los que interactuará el backend y el flujo de datos entre ellos.
- Entender qué vas a construir en cada unidad y por qué ese orden tiene sentido.

---

## 0.1. El Vocational Federated Dataspace (VFDS)

### ¿Qué es un espacio de datos federado?

Un **espacio de datos** es una infraestructura que permite que múltiples organizaciones compartan datos de forma controlada, con soberanía sobre lo que comparten, con quién y bajo qué condiciones. No es una base de datos centralizada: es un ecosistema de confianza en el que cada participante conserva el control de sus propios datos.

El adjetivo **federado** indica que no existe una autoridad central que lo posea todo. Cada nodo (cada institución participante) es autónomo y aplica las reglas comunes del espacio, pero mantiene su propia infraestructura.

El **Vocational Federated Dataspace (VFDS)** es un espacio de datos federado específicamente diseñado para el ecosistema de la **Formación Profesional**. Sus participantes son centros educativos, empresas, administraciones y otros agentes del mercado laboral que comparten datos sobre competencias, itinerarios formativos, empleabilidad y evaluación.

```
┌─────────────────────────────────────────────────────────────────┐
│                  VOCATIONAL FEDERATED DATASPACE                             │
│                                                                             │
│   ┌────────────────┐   ┌───────────────┐   ┌───────────────┐       │
│   │  Nodo Centro     │    │  Nodo Centro    │   │  Nodo Empresa    │       │
│   │  Educativo A     │    │  Educativo B    │   │    / RRHH        │       │
│   │                  │    │                 │   │                  │       │
│   │ Backend EAC ◄───┼───┼─ Orion NGSI-LD──┼──┼─► Catálogo       │       │
│   │ (lo que vas      │    │                 │   │                  │       │
│   │  a construir)    │    │                 │   │                  │       │
│   └────────────────┘   └───────────────┘   └───────────────┘       │
│                                                                             │
│            Servicios habilitadores comunes (FIWARE)                         │
│         Keyrock · Orion-LD · Wilma PEP Proxy · Catálogo                     │
└─────────────────────────────────────────────────────────────────┘
```

La infraestructura técnica del VFDS se implementa con componentes **FIWARE**, que son componentes de software libre diseñados para construir plataformas de datos interoperables. En las unidades siguientes trabajarás directamente con **FIWARE Orion Context Broker** (gestión de entidades de contexto en tiempo real) y **FIWARE Keyrock** (gestión de identidad y autorización).

### El rol del Backend EAC dentro del VFDS

El **Backend EAC** es la aplicación Laravel que vas a construir a lo largo de esta práctica. Es el sistema de información de un nodo del VFDS — concretamente, el nodo de un **centro educativo de FP**.

Su función es triple:

1. **Gestionar internamente** el espacio competencial del centro: el grafo de Situaciones de Competencia, los perfiles de los estudiantes y el proceso de evaluación.
2. **Publicar en el VFDS** los estados de competencia de los estudiantes como entidades NGSI-LD en el Context Broker, para que otros participantes del espacio (empresas, administraciones) puedan consultarlos con los permisos adecuados.
3. **Exponer una API REST** que sirva de interfaz tanto para el frontend del marketplace como para otros sistemas del ecosistema.

```
                        ┌──────────────────────────────────┐
                        │        BACKEND EAC (Laravel)           │
                        │                                        │
    Docente/Estudiante  │  ┌─────────┐   ┌─────────────┐    │
         ──────────────►│  Vistas   │   │  API REST     │    │
                        │  │  Blade    │   │  /api/v1/     │    │
                        │  └────┬────┘   └──────┬──────┘    │
                        │        │                 │             │
                        │  ┌────▼───────────────▼──────┐   │
                        │  │      Lógica de negocio          │   │
                        │  │  Grafo · ZDP · Evaluación       │   │
                        │  └────────────┬───────────────┘   │
                        │                 │                      │
                        │  ┌────────────▼───────────────┐  │
                        │  │  MariaDB (base de datos)        │   │
                        │  └────────────────────────────┘   │
                        └──────────────┬───────────────────┘
                                          │ HTTP (NGSI-LD)
                                          ▼
                        ┌──────────────────────────────────┐
                        │   Nodo FIWARE (ya desplegado)          │
                        │  Orion-LD · Keyrock · Wilma PEP        │
                        └──────────────────────────────────┘
```

> **Nota sobre el entorno:** A lo largo de esta práctica dispondrás de un nodo FIWARE ya desplegado al que conectar el backend. No tendrás que instalarlo tú: solo necesitas las URLs de sus endpoints y las credenciales de acceso, que se proporcionarán en el fichero `.env` de referencia.

---

## 0.2. El Modelo EAC: vocabulario esencial

El Backend EAC implementa el **Modelo de Espacio de Aprendizaje Competencial (EAC)**, que es una adaptación de la *Knowledge Space Theory* (KST) de Doignon y Falmagne orientada al desempeño profesional en FP.

Antes de escribir una sola línea de código necesitas dominar el vocabulario de este modelo, porque los nombres de las tablas, los modelos Eloquent, los endpoints de la API y los atributos NGSI-LD que publicarás en Orion se corresponden directamente con estos conceptos.

### 0.2.1. Las entidades del dominio

#### Ecosistema Laboral (Dominio)

Es la representación completa y estructurada del mapa de competencias que conforma un **título oficial de FP**. En la práctica piloto trabajarás con el módulo *Técnicas Básicas de Merchandising*, pero el modelo es genérico y aplicable a cualquier módulo o ciclo.

En el código representará la entidad raíz: toda SC, todo Nodo de Requisito y todo Perfil de Habilitación pertenece a un Ecosistema Laboral concreto.

#### Situación de Competencia (SC)

Es la **unidad mínima de desempeño profesional**. No es un tema ni un contenido: es una tarea compleja (un reto, un proyecto, un problema real) que integra de forma coherente varios Resultados de Aprendizaje y Criterios de Evaluación del currículo oficial.

Ejemplos para el módulo piloto:

| Código | Situación de Competencia |
|--------|--------------------------|
| SC-01 | Diseñar la disposición de productos en un lineal aplicando criterios de visual merchandising |
| SC-02 | Elaborar un planograma básico para un punto de venta |
| SC-03 | Analizar el rendimiento de una zona caliente/fría mediante indicadores de ventas |

Una SC **no es un examen**: es el contexto en el que el estudiante demuestra que sabe hacer algo con autonomía y calidad profesional.

#### Nodos de Requisito (Base)

Son los **elementos atómicos** que un estudiante debe dominar antes de poder abordar una SC. Se dividen en dos tipos:

- **Conocimientos (Saber):** hechos, leyes, teorías, protocolos. Ejemplos: *"Conocer los principios del color en escaparatismo"*, *"Conocer la normativa de etiquetado"*.
- **Habilidades (Saber hacer):** destrezas técnicas y operativas. Ejemplos: *"Manejar software de planogramas"*, *"Calcular el índice de rotación de stock"*.

En la base de datos serán registros de la tabla `nodos_requisito`, con un campo `tipo` que los distingue.

#### Umbral de Maestría (Mastery Threshold)

Es el **estándar mínimo de calidad** exigido para que una SC se considere *conquistada*. No es simplemente un porcentaje de aciertos: incluye criterios de rigor técnico, eficiencia y adecuación al estándar profesional.

Define la línea entre "lo ha intentado" y "es capaz de hacerlo como un profesional". En el sistema se implementa como un conjunto de criterios evaluables vinculados a cada SC, con umbrales configurables por el docente.

#### Gradiente de Autonomía

Es el indicador de en qué medida el estudiante puede ejecutar una SC **sin apoyo externo**. Evoluciona a través de cuatro niveles:

| Nivel | Descripción |
|-------|-------------|
| `asistido` | Necesita guía continua para completar la tarea |
| `guiado` | Puede avanzar con instrucciones paso a paso |
| `supervisado` | Ejecuta de forma independiente pero requiere revisión |
| `autonomo` | Ejecuta con calidad profesional sin ningún apoyo |

En el código este campo aparecerá en la tabla pivot `perfil_situacion` y condicionará la calificación final del estudiante.

#### Perfil de Habilitación (Estado)

Es el **subconjunto exacto de Situaciones de Competencia** que un estudiante domina de forma autónoma en un momento dado. Es una fotografía de su nivel competencial actual.

Dos estudiantes con el mismo número de SCs conquistadas pueden tener Perfiles de Habilitación completamente distintos, porque han navegado el grafo por caminos diferentes.

#### Grafo de Precedencia

Es la **estructura matemática** que define las dependencias entre SCs. Si para abordar SC-03 es necesario haber conquistado SC-01 y SC-02, esa relación se registra en el grafo.

```
SC-01 ──────► SC-03
                 ▲
SC-02 ─────────┘

SC-01 y SC-02 son requisitos de SC-03.
Un estudiante no puede acceder a SC-03 si no ha superado ambas.
```

En la base de datos se implementará como una tabla de adyacencia (`sc_precedencia`) sobre la entidad `situaciones_competencia`. En la Unidad 5 implementarás el algoritmo de recorrido de este grafo.

#### Zona de Despliegue Proximal (ZDP / Franja)

Es el conjunto de SCs que el estudiante **está en condiciones de abordar ahora mismo**, dado su Perfil de Habilitación actual. Son las SCs cuyos requisitos previos el estudiante ya ha conquistado, pero que él mismo aún no ha completado.

La ZDP es el motor de la recomendación personalizada: el sistema mostrará al estudiante únicamente las SCs de su ZDP, evitando tanto el aburrimiento (tareas demasiado fáciles) como la frustración (tareas inaccesibles).

#### Politopía de Aprendizaje

Es el principio que reconoce que **existen múltiples rutas válidas** dentro del grafo para alcanzar un mismo estado de competencia final. No hay un único camino correcto: el sistema lo respeta y lo facilita.

#### Huella de Talento

Es el producto final del recorrido del estudiante por el espacio: un **registro exportable** que documenta no solo qué SCs ha conquistado, sino cómo las ha conquistado (qué Gradiente de Autonomía alcanzó en cada una, cuánto tiempo tardó, qué itinerario siguió).

A diferencia de un título tradicional, la Huella de Talento es granular, verificable y publicable en el VFDS para que las empresas puedan consultarla.

---

## 0.3. De la norma a la arquitectura: cómo se construye el espacio

Antes de modelar la base de datos en la Unidad 1, es importante entender el proceso de **ingeniería del EAC**: cómo se transforma el currículo oficial en la arquitectura técnica que vas a implementar.

### Paso A: Deconstrucción curricular

El punto de partida son los **Resultados de Aprendizaje (RA)** y sus **Criterios de Evaluación (CE)** del módulo. Se agrupan por afinidad funcional para formar las Situaciones de Competencia.

```
RA 1: Organiza productos en el punto de venta...
  CE 1a: Se han descrito los principios del visual merchandising
  CE 1b: Se han identificado las zonas calientes y frías          ──► SC-01
  CE 1c: Se ha elaborado un planograma básico

RA 2: Analiza el rendimiento del punto de venta...
  CE 2a: Se han calculado índices de rotación
  CE 2b: Se han identificado productos de baja rotación           ──► SC-03
  CE 2c: Se han propuesto acciones de mejora
```

Una SC integra múltiples CE, dándoles **sentido profesional**: ya no es "superar el criterio 1a", sino "ser capaz de diseñar la disposición de un lineal".

### Paso B: Definición de umbrales y nodos de requisito

Para cada SC se identifican sus Nodos de Requisito (los conocimientos y habilidades previas necesarias) y se define el Umbral de Maestría (el estándar mínimo para considerarla conquistada).

### Paso C: Construcción del Grafo de Precedencia

La unión de todas las SCs con sus requisitos y umbrales genera el grafo. Este grafo es el **núcleo del sistema**: todo el motor de recomendación, toda la lógica de evaluación y toda la sincronización con FIWARE parten de él.

---

## 0.4. Arquitectura del sistema que vas a construir

### Componentes y su relación

```
┌───────────────────────────────────────────────────────────────────┐
│                        BACKEND EAC (Laravel)                                  │
│                                                                               │
│  ┌──────────────────┐  ┌──────────────────┐  ┌──────────────────┐  │
│  │  Unidad 2           │  │  Unidades 3-4       │  │  Unidades 5-7        │  │
│  │  Vistas Blade       │  │  API REST           │  │  Lógica de           │  │
│  │  Layout             │  │  /api/v1/           │  │  negocio EAC         │  │
│  │  Marketplace        │  │  Autenticación      │  │  Grafo · ZDP         │  │
│  │  Dashboard          │  │  OAuth2/Keyrock     │  │  Evaluación          │  │
│  └────────┬─────────┘  └────────┬─────────┘  └────────┬─────────┘   │
│             │                        │                         │              │
│  ┌────────▼─────────────────────▼─────────────────────▼─────────┐ │
│  │                    Unidad 1: Modelos Eloquent                            │  │
│  │  EcosistemaLaboral · SituacionCompetencia · NodoRequisito                │  │
│  │  PerfilHabilitacion · GrafoPrecedencia · HuellaTalento                   │  │
│  └──────────────────────────────┬────────────────────────────────┘  │
│                                       │                                        │
│  ┌──────────────────────────────▼────────────────────────────────┐ │
│  │                    MySQL (base de datos local)                            │ │
│  └───────────────────────────────────────────────────────────────┘  │
│                                                                                 │
│  ┌───────────────────────────────────────────────────────────────┐  │
│  │  Unidad 6: Servicio FIWARE (OrionService)                                │  │
│  │  Publica entidades NGSI-LD en el Context Broker externo                  │  │
│  └──────────────────────────────┬────────────────────────────────┘  │
└─────────────────────────────────┼───────────────────────────────────┘
                                        │ HTTP/NGSI-LD
                      ┌──────────────▼───────────────┐
                      │  Nodo FIWARE (desplegado)          │
                      │                                    │
                      │  Orion-LD :1026                    │
                      │  Keyrock  :3000                    │
                      │  Wilma PEP:1027                    │
                      └──────────────────────────────┘
```

### Stack tecnológico

| Capa | Tecnología | Versión |
|------|-----------|---------|
| Framework backend | Laravel | 11.x |
| Lenguaje | PHP | 8.2+ |
| Base de datos | MariaDB | 10.6+ |
| Autenticación API | Laravel Sanctum | integrado |
| Autenticación OIDC | FIWARE Keyrock | nodo desplegado |
| Context Broker | FIWARE Orion-LD | nodo desplegado |
| PEP Proxy | FIWARE Wilma | nodo desplegado |
| Frontend | Blade + Bootstrap 5 | - |
| Gráficas (Unidad 8) | ConsoleTVs/Charts | 6.x |
| Testing | PHPUnit | integrado |
| Gestor de dependencias | Composer | 2.x |

### Entidades NGSI-LD que publicará el backend en Orion

Cuando el estado competencial de un estudiante cambie (conquiste una SC, actualice su Gradiente de Autonomía), el backend sincronizará esa información con el Context Broker en formato NGSI-LD. Estas son las entidades que gestionarás en la Unidad 6:

```json
{
  "id": "urn:ngsi-ld:PerfilHabilitacion:estudiante-42",
  "type": "PerfilHabilitacion",
  "estudiante": {
    "type": "Relationship",
    "object": "urn:ngsi-ld:Estudiante:42"
  },
  "situacionesConquistadas": {
    "type": "Property",
    "value": ["SC-01", "SC-02"]
  },
  "gradienteAutonomia": {
    "type": "Property",
    "value": {
      "SC-01": "autonomo",
      "SC-02": "supervisado"
    }
  },
  "zonaDespliegueProximal": {
    "type": "Property",
    "value": ["SC-03", "SC-05"]
  },
  "@context": "https://vfds.example.org/ngsi-ld/eac-context.jsonld"
}
```

---

## 0.5. Flujo de trabajo completo: de la evaluación a la Huella de Talento

Para que entiendas el sistema de un vistazo, aquí tienes el flujo completo que implementarás unidad a unidad:

```
1. El DOCENTE diseña el espacio competencial
   └─► Crea el Ecosistema Laboral, las SCs y el Grafo de Precedencia
       (Unidades 1 y 2)

2. El ESTUDIANTE accede al sistema
   └─► Se autentica via Keyrock (OAuth2)
       El backend verifica el token y carga su Perfil de Habilitación
       (Unidad 4)

3. El sistema calcula la ZDP del estudiante
   └─► Recorre el Grafo de Precedencia con el Perfil actual
       Devuelve las SCs accesibles
       (Unidad 5)

4. El estudiante trabaja en una SC de su ZDP
   └─► Envía evidencias de desempeño al endpoint de evaluación
       El sistema aplica el Umbral de Maestría
       (Unidad 7)

5. Si supera el Umbral de Maestría
   └─► El Perfil de Habilitación se actualiza (nueva SC conquistada)
       El Gradiente de Autonomía se registra
       El backend publica la actualización en FIWARE Orion-LD
       (Unidades 6 y 7)

6. El sistema recalcula la ZDP
   └─► Nuevas SCs pueden haberse desbloqueado
       (Unidad 5)

7. La Huella de Talento se genera y puede exportarse
   └─► JSON exportable, visible en el marketplace del VFDS
       (Unidades 2 y 7)
```

---

## 0.6. Preparación del entorno con Laradock

El entorno de desarrollo se gestiona con **Laradock**, un conjunto de imágenes Docker preconfiguradas para proyectos Laravel. Si ya has trabajado con `marcapersonalFP_REA`, tienes Laradock instalado y solo necesitas añadir el proyecto `backend-eac` dentro de la misma instalación.

### Estructura de carpetas objetivo

```
└── laravel
    ├── marcapersonalfp      ← proyecto anterior (si existe)
    ├── backend-eac          ← proyecto que vas a crear ahora
    └── laradock
```

### Paso 0.6.1: Descargar Laradock (si no lo tienes ya)

> _Si utilizas la máquina virtual del curso o ya tienes Laradock instalado de prácticas anteriores, salta directamente al paso [0.6.2. Generar el proyecto Laravel](#062-generar-el-proyecto-laravel)._

```bash
cd ~/Documentos/laravel/
git clone https://github.com/Laradock/laradock.git
cd laradock && cp .env.example .env && cd ..
```

Edita `laradock/.env` con los siguientes ajustes:

```ini
# Versión de PHP
PHP_VERSION=8.2

# Driver de phpMyAdmin
PMA_DB_ENGINE=mariadb

# Habilitar XDebug
WORKSPACE_INSTALL_XDEBUG=true
PHP_FPM_INSTALL_XDEBUG=true
WORKSPACE_XDEBUG_PORT=9003
PHP_FPM_XDEBUG_PORT=9003
```

Edita tanto `laradock/php-fpm/xdebug.ini` como `laradock/workspace/xdebug.ini`:

```ini
xdebug.client_host="host.docker.internal"
xdebug.discover_client_host=1
xdebug.client_port=9003
xdebug.idekey=vsc
xdebug.mode=debug
xdebug.start_with_request=trigger
xdebug.var_display_max_children=-1
xdebug.var_display_max_data=-1
xdebug.var_display_max_depth=-1
```

Edita `laradock/mariadb/my.cnf` y ajusta:

```ini
innodb_log_file_size = 256M
```

Edita `laradock/mariadb/Dockerfile` y sustituye:

```dockerfile
# antes:
CMD ["mysqld"]
# después:
CMD ["mariadbd"]
```

### Paso 0.6.2: Generar el proyecto Laravel

Desde `~/Documentos/laravel/` ejecuta:

```bash
docker run -it --rm --name php-cli \
    -v "$PWD:/usr/src/app" thecodingmachine/php:8.2-v4-slim-cli \
    composer create-project --prefer-dist laravel/laravel backend-eac
```

### Paso 0.6.3: Arrancar los contenedores

Desde el directorio `laradock`:

```bash
cd ~/Documentos/laravel/laradock
docker compose up -d nginx mariadb php-fpm phpmyadmin workspace
```

> La primera ejecución descarga las imágenes y puede tardar varios minutos.

### Paso 0.6.4: Crear la base de datos

Accede a [phpMyAdmin](http://localhost:8081/) con usuario `root` / contraseña `root`, ve a _Cuentas de usuario → Agregar cuenta de usuario_ y crea:

- **Usuario:** `backend_eac`
- **Contraseña:** `backend_eac`
- ✅ Marca _Crear base de datos con el mismo nombre y otorgar todos los privilegios_

O bien ejecuta directamente desde el contenedor de MariaDB:

```bash
docker exec -ti laradock-mariadb-1 /bin/bash
mariadb -p   # contraseña: root
```

```sql
CREATE USER 'backend_eac'@'%' IDENTIFIED VIA mysql_native_password USING PASSWORD('backend_eac');
GRANT USAGE ON *.* TO 'backend_eac'@'%';
CREATE DATABASE IF NOT EXISTS `backend_eac`;
GRANT ALL PRIVILEGES ON `backend_eac`.* TO 'backend_eac'@'%';
```

### Paso 0.6.5: Configurar el fichero `.env` de la aplicación

Edita `backend-eac/.env`:

```ini
APP_NAME="Backend EAC"
APP_URL=http://backend-eac.test

APP_LOCALE=es
APP_FALLBACK_LOCALE=es
APP_FAKER_LOCALE=es_ES

DB_CONNECTION=mysql
DB_HOST=mariadb
DB_PORT=3306
DB_DATABASE=backend_eac
DB_USERNAME=backend_eac
DB_PASSWORD=backend_eac

# Endpoints del nodo FIWARE (los proporcionará el instructor)
FIWARE_ORION_URL=https://orion.vfds.example.org
FIWARE_KEYROCK_URL=https://keyrock.vfds.example.org
FIWARE_CLIENT_ID=
FIWARE_CLIENT_SECRET=
```

Edita también el fichero `backend-eac/.env.example` para añadir las variables de entorno relacionadas con FIWARE, para que cualquiera que clone el proyecto tenga la referencia de qué variables configurar:

```ini
# Endpoints del nodo FIWARE (los proporcionará el instructor)
FIWARE_ORION_URL=https://orion.vfds.example.org
FIWARE_KEYROCK_URL=https://keyrock.vfds.example.org
FIWARE_CLIENT_ID=
FIWARE_CLIENT_SECRET=
```

### Paso 0.6.6: Ejecutar las migraciones iniciales

En Laravel 12 las sesiones se almacenan en base de datos por defecto, por lo que es necesario ejecutar las migraciones antes de acceder a la aplicación:

```bash
cd ~/Documentos/laravel/backend-eac
php artisan migrate
```

Deberías ver algo como:

```
   INFO  Preparing database.  

  Creating migration table ............................................ 43.41ms DONE

   INFO  Running migrations.  

  0001_01_01_000000_create_users_table ................................ 64.48ms DONE
  0001_01_01_000001_create_cache_table ................................ 40.10ms DONE
  0001_01_01_000002_create_jobs_table ................................. 59.21ms DONE
```

### Paso 0.6.7: Definir el servidor virtual en nginx

1. Ve a `laradock/nginx/sites/` y duplica `laravel.conf.example` con el nombre `backend-eac.conf`.

2. Edita `backend-eac.conf` y modifica estas dos líneas:

    ```nginx
    server_name backend-eac.test;
    root /var/www/backend-eac/public;
    ```

3. Añade la entrada en `/etc/hosts` (con `sudo`):

    ```
    127.0.0.1  backend-eac.test
    ```

4. Reinicia el contenedor de nginx desde la carpeta `laradock`:

    ```bash
    docker compose restart nginx
    ```

### Paso 0.6.8: Verificar que todo funciona

Accede a [http://backend-eac.test](http://backend-eac.test). Deberías ver la pantalla de bienvenida de Laravel.

Para confirmar la versión:

```bash
# Dentro del workspace de Laradock
php artisan --version
# Laravel Framework 12.x.x
```

### Paso 0.6.9: Instalar dependencias de frontend

```bash
# Desde el directorio backend-eac
npm install
npm run dev
```

---

## ✅ Verificación final de la Unidad 0

Antes de continuar con la Unidad 1, confirma que:

- [ ] Puedes explicar con tus propias palabras qué es el VFDS y qué papel tiene el Backend EAC dentro de él.
- [ ] Puedes definir sin consultar: SC, Grafo de Precedencia, Perfil de Habilitación, ZDP, Umbral de Maestría, Gradiente de Autonomía y Huella de Talento.
- [ ] Entiendes la diferencia entre un Conocimiento y una Habilidad como tipos de Nodo de Requisito.
- [ ] Puedes describir el flujo completo del sistema: desde que el docente diseña el espacio hasta que el estudiante exporta su Huella de Talento.
- [ ] Los contenedores de Laradock están en marcha (`nginx`, `mariadb`, `php-fpm`, `phpmyadmin`, `workspace`).
- [ ] La base de datos `backend_eac` existe y el usuario `backend_eac` tiene todos los privilegios sobre ella.
- [ ] Las migraciones iniciales de Laravel se han ejecutado sin errores.
- [ ] El servidor virtual `backend-eac.test` está configurado en nginx y en `/etc/hosts`.
- [ ] La aplicación es accesible en [http://backend-eac.test](http://backend-eac.test) y muestra la pantalla de bienvenida de Laravel.
- [ ] El fichero `.env` tiene configurados los endpoints del nodo FIWARE.

---

## 📖 Referencias

- [Repositorio del caso de uso VFDS-EAC](https://github.com/C3-VFDS/use_case_pkst)
- [Marco Teórico EAC](https://c3-vfds.github.io/use_case_pkst/03_01_marco-teorico-eac.html)
- [Arquitectura del sistema](https://c3-vfds.github.io/use_case_pkst/05-arquitectura-del-sistema.html)
- [Stack tecnológico](https://c3-vfds.github.io/use_case_pkst/053-stack-tecnologico.html)
- [Especificación de APIs](https://c3-vfds.github.io/use_case_pkst/10-especificacion-de-apis.html)
- [Integración con FIWARE](https://c3-vfds.github.io/use_case_pkst/11-integracion-con-fiware.html)
- [Documentación FIWARE Orion-LD](https://fiware-orion.readthedocs.io/)
- [FIWARE Keyrock](https://fiware-idm.readthedocs.io/)

---

**Siguiente unidad →** [Unidad 1: Modelado de datos EAC en Laravel](./01_modelado_datos_eac.md)
