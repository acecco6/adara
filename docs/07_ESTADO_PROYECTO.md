# 07 — Estado del proyecto: auditoría T00 y T01

> **Actualización 21/10/2026:** este informe conserva el diagnóstico y las propuestas históricas de T00/T01. Las reglas comerciales R01–R63 aprobadas después están en [REGLAS_ECONOMICAS_ADARA.md](REGLAS_ECONOMICAS_ADARA.md) y prevalecen sobre propuestas incompatibles de costo promedio, precio manual, pagos o devoluciones. Consultar [CONFLICTOS_Y_PENDIENTES.md](CONFLICTOS_Y_PENDIENTES.md) y [09_PLAN_TECNICO_R01_R63.md](09_PLAN_TECNICO_R01_R63.md) antes de implementar.

Fecha de inspección: 20 de octubre de 2026. Alcance: lectura del proyecto `adara-gestion` y de los recursos visuales de su directorio padre. Este documento describe el estado observado; las rutas, clases y tablas comerciales indicadas como propuestas **no existen todavía**. No se modificó código, configuración ni la base de datos.

## A. Resumen ejecutivo

Adara Gestión es, hoy, una instalación funcional del starter Laravel/Livewire, sin módulos de gestión implementados. La aplicación ofrece únicamente la bienvenida de Laravel en `/`; las 17 pantallas y 20 modales son referencias de diseño. El roadmap es suficientemente detallado para iniciar el contrato visual, pero faltan decisiones de dominio antes de migraciones o cálculos económicos. La distribución offline para macOS tampoco está preparada.

**Estado para avanzar:** T00 y T01 documentados; pasar a T02 solo después de revisar las decisiones pendientes de este informe. T03 y los demás tickets de implementación requieren autorización posterior. La documentación faltante mencionada en `AGENTS.md` debe localizarse o redactarse en un ticket aprobado.

## B. Auditoría técnica

### Ubicación y documentación

- Aplicación: `adara-gestion/`; referencias originales en el directorio padre: `design_tokens.json`, `prototipo.html`, `LEEME_IMPORTAR_EN_FIGMA.md`, `Aura_vistas_generales.png`, `Aura_Diseno_Completo_Figma.zip` y 40 SVG (2 mapas/sistema, 17 pantallas, 20 modales y `00_inicio.svg` adicional).
- Presentes: `AGENTS.md`, `docs/01_ROADMAP_FRONTEND.md`, `02_ROADMAP_BACKEND.md`, `03_PLAN_DE_ENTREGAS.md`, `04_CALIDAD_Y_PRUEBAS.md`.
- Ausentes en las rutas anunciadas: `README.md`, `docs/00_CONTEXTO_Y_DECISIONES.md`, `docs/05_MACOS_OFFLINE.md` y `docs/06_PROMPT_INICIAL_CODEX.md`. El texto inicial fue aportado como adjunto de esta conversación, no existe como archivo del proyecto. `AGENTS.md` remite a los tres documentos de `docs/` ausentes.
- No se encontró `.ai/rules/`. Existen `.agents/skills/` (Boost) y `.codex/config.toml`.
- No se encontró `.git/` en la aplicación ni en la carpeta contenedora; `git status` y `git remote` devuelven “not a git repository”. Git instalado no equivale a control de versiones activo para este proyecto. `.gitignore` excluye `.env`, SQLite, `vendor`, `node_modules`, `public/build`, `public/hot`, `AGENTS.md`, `boost.json` y `.agents/`; revisar antes de una futura inicialización para preservar instrucciones compartidas sin exponer datos.

### Versiones realmente observadas

| Herramienta o paquete | Versión instalada | Evidencia |
| --- | --- | --- |
| PHP CLI | 8.4.13 | `php -v` |
| Composer | 2.8.12 | `composer --version` |
| Node.js / npm | 25.1.0 / 11.6.2 | `node --version`, `npm --version` |
| Laravel | 13.38.0 | `php artisan --version`, `composer show --direct` |
| Livewire / Blaze | 4.4.7 / 1.0.19 | `composer show --direct` |
| Laravel Boost | 2.10.3 | `composer show --direct` |
| PHPUnit / Pint / Larastan | 12.5.40 / 1.32.1 / 3.13.0 | `composer show --direct` |
| Tailwind CSS / `@tailwindcss/vite` | 4.3.3 / 4.3.3 | `npm ls --depth=0` |
| Vite / Vite Plus / Laravel Vite plugin | 8.3.4 / 0.3.0 / 3.2.0 | `npm ls --depth=0` |
| Alpine.js | Integrado en Livewire 4; versión autónoma no constatada | no figura como dependencia directa npm; no se atribuye una versión no verificada |
| SQLite usada por la conexión Laravel/PHP | 3.49.2 | MCP Boost `database-query`: `SELECT sqlite_version()`; una inspección previa con Python informó 3.50.4 para su propia librería |

`composer.json` exige PHP `^8.3` aunque el CLI actual es 8.4.13. Dependencias directas adicionales: `laravel/tinker`, `laravel/pail`, `laravel/pao`, `laravel/sail` (solo desarrollo), `faker`, `mockery`. npm incluye `chart.js`, `lucide`, `concurrently`. Las dependencias opcionales Linux que npm marca como no instaladas son esperables en Windows; no se detectó con ello una instalación fallida.

### Estructura y ejecución actual

| Área | Estado observado |
| --- | --- |
| Rutas | `routes/web.php`: solo `Route::view('/', 'welcome')->name('home')`; `route:list --except-vendor`: 1 ruta. Health `/up` está configurado en `bootstrap/app.php`. |
| Modelos | Solo `App\Models\User`; no hay relaciones de negocio, Actions, Services ni componentes Livewire de Adara. |
| Migraciones | Tres migraciones del starter, todas registradas en lote 1: usuarios/sesiones, caché y jobs. |
| Vistas y JS | Solo `resources/views/welcome.blade.php` (pantalla Laravel), `resources/css/app.css` y `resources/js/app.js` vacío. No hay layout Adara, componentes Blade compartidos ni pantallas. |
| Pruebas | PHPUnit configurado con SQLite `:memory:`; dos tests de ejemplo, sin cobertura comercial. `tests/Unit/ExampleTest.php` usa `RefreshDatabase` con `PHPUnit\Framework\TestCase`: revisar cuando se active el trabajo de pruebas. No se ejecutaron tests en esta auditoría de solo lectura. |
| SQLite | `database/database.sqlite` existe (622.592 bytes). `migrate:status`: solo 3 migraciones starter ejecutadas. 9 tablas: `users`, `password_reset_tokens`, `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `migrations`. Sin tablas comerciales ni FK declaradas en el esquema actual; índices starter para email, sesión, colas y caché. `PRAGMA integrity_check` sobre conexión `mode=ro`: `ok`. No se leyeron registros personales. |
| Configuración | `.env` local declara nombre Adara Gestión, `APP_DEBUG=true`, `APP_URL=http://127.0.0.1:8000`, locale `es`, SQLite y drivers `database` para sesión/caché/cola. `.env.example` conserva nombre Laravel, URL localhost e idioma inglés. `config/database.php` activa FK para la conexión Laravel por defecto; `busy_timeout`, `journal_mode` y `synchronous` son `null`, transacción `DEFERRED`. |
| Build | `npm run build` usa `vp build`; no hay `public/build` observado. `public/hot` señala un Vite dev server en `[::1]:5173`; eso es un artefacto de desarrollo, no de distribución. |

La conexión externa de Python utilizada para examinar el archivo SQLite devolvió `PRAGMA foreign_keys=0` (valor de **esa conexión**, no prueba de que Laravel los desactive). Devolvió `journal_mode=delete` y `busy_timeout=5000` para esa conexión. Confirmar las pragmas efectivas de Laravel antes de desarrollar operaciones críticas y definir una política de concurrencia apropiada para SQLite.

## C. Estado de Laravel Boost

`boost.json` indica guidelines, MCP y skills instalados para Codex. `.codex/config.toml` define el servidor `laravel-boost` mediante `php artisan boost:mcp`. Se localizaron las skills `infer-conventions`, `laravel-best-practices`, `testing-best-practices`, `livewire-development`, `tailwindcss-development` y `deploying-to-cloud`; para esta auditoría se leyeron las guías aplicables de Laravel, Livewire, Tailwind y pruebas. No existe `.ai/rules/`.

**Conexión actual:** Boost responde en esta sesión. `mcp__laravel_boost__application_info` informó PHP 8.4, Laravel 13.38.0, Livewire 4.4.7, Boost 2.10.3, Tailwind 4.3.3 y Vite 8.3.4. `mcp__laravel_boost__database_connections` informó SQLite como conexión predeterminada; MySQL, MariaDB, PostgreSQL y SQL Server figuran solo como conexiones configuradas, no como motores en uso. `mcp__laravel_boost__database_schema` mostró las nueve tablas starter, sin tablas comerciales; el detalle de `users` informó índice único en `email` y ninguna FK. `mcp__laravel_boost__database_query`, con una consulta `SELECT` de solo lectura, informó SQLite 3.49.2 y tres migraciones registradas. `mcp__laravel_boost__search_docs` respondió con documentación correspondiente a Laravel 13 y Livewire 4, incluida la API `Route::livewire()` para páginas y transacciones de base de datos. Ninguna de estas consultas aplicó migraciones ni cambió registros.

**Herramientas expuestas:** `application_info`, `browser_logs`, `database_connections`, `database_query`, `database_schema`, `get_absolute_url`, `last_error`, `read_log_entries`, `record_rule` y `search_docs`, todas bajo el prefijo `mcp__laravel_boost__`. No se expone `list_routes`; por eso la ruta actual se verificó con el comando informativo `php artisan route:list --except-vendor --no-interaction`. La conexión MCP es operativa para las consultas realizadas, pero no puede calificarse de completa respecto de `list-routes` solicitado. Las herramientas restantes figuran disponibles, pero no se ejecutaron ni se les atribuye funcionamiento comprobado.

## D. Auditoría del diseño

### Fuentes y sistema visual

El JSON aporta valores base; el SVG `00_Design_System.svg`, la plancha PNG y el HTML muestran su aplicación. Los SVG 01–17 y M01–M20 son exportaciones vectoriales; gran parte de sus textos son contornos y el HTML es la referencia legible para etiquetas e interacción. El nombre `Aura` en estos originales es histórico: toda UI nueva deberá mostrar **Adara Gestión**, incluido logo, título, ayuda, comprobantes y archivos de respaldo, sin sobrescribir los originales.

| Grupo | Contrato observado |
| --- | --- |
| Fondo / superficies | `#F8F5F2` fondo, `#FFFEFD` tarjetas/diálogos, `#EBE3E5` bordes |
| Texto / navegación | `#302936` tinta, `#8D818D` atenuado, `#312631` sidebar |
| Marca | `#8B4969` primario, `#793B5A` hover, `#F7EAF0` lavado |
| Estados | éxito `#24785D`/`#E7F5EE`, advertencia `#A66F1D`/`#FFF3DA`, peligro `#A94555`/`#FBEAED` |
| Tipos | UI `Segoe UI / Inter` con fallback de sistema; títulos y logo `Georgia`. El HTML usa base 13 px, textos auxiliares 10–12 px, títulos principales 30 px, KPIs 26 px; pesos mayormente 650–800. No hay escala tipográfica completa en JSON. |
| Espaciado y forma | base 8 px; control 10 px, tarjeta 18 px, diálogo 20 px. HTML: sidebar 246 px, barra superior 74 px, contenido con padding 38 px y ancho máximo 1520 px. Sombra principal `0 12px 45px rgba(59,36,55,.045)`; diálogos más pronunciados. |
| Responsive | referencia 1440×900; `@media` en 1170, 800 y 480 px. En ≤1170 sidebar 212 px; en ≤800 se oculta y aparece selector superior. Tablas conservan contenedor con scroll horizontal propio. Validar también 1366×768 y zoom 125 % en T03. |

La navegación agrupa **General** (Inicio, Productos, Stock y movimientos), **Operaciones** (Pedidos a proveedor, Recepción de compras, Ventas, Nueva venta), **Administración** (Clientes, Proveedores, Categorías, Reportes) y **Sistema** (Configuración, Copias de seguridad, Estados de interfaz, Ayuda). El header contiene breadcrumb, fecha, iconos y aviso “datos de ejemplo”; este aviso se elimina o sustituye por estado real cuando exista producto. El área principal usa encabezado con eyebrow/título/subtítulo y acciones, grillas de KPI, tarjetas y tablas. La barra lateral es sticky; el scroll principal corresponde a la página, y las tablas desbordan dentro de su propio contenedor.

El inventario verificable de las **17 pantallas**, **20 modales** y piezas compartidas está en [`08_INVENTARIO_VISUAL.md`](08_INVENTARIO_VISUAL.md). P16 es una galería de estados para desarrollo, no una función comercial que deba exponerse en producción. Se detectaron overlays extra en el HTML (`guia`, `galeria-modales`, editar categoría/cliente/proveedor/pedido), sin SVG numerado independiente; no se cuentan dentro de los 20 diseños formales.

### Brechas de accesibilidad y comportamiento

- El prototipo usa botones laterales y `innerHTML`, sin rutas ni persistencia. Sus `label` no tienen asociación `for/id` consistente. El diálogo declara `role=dialog` y `aria-modal`, pero no demuestra captura/restauración de foco ni navegación Tab contenida. El Escape cierra globalmente sin discriminar confirmaciones sensibles. Rehacer estos comportamientos con Blade, Livewire y Alpine; no copiar el JS de demostración.
- Verificar contraste de texto atenuado y navegación, especialmente 10–12 px; estados no deben depender solo del color. Áreas clicables de iconos de 35 px del HTML deberían revisarse frente al objetivo de 40 px indicado en roadmap. Incluir `:focus-visible`, mensajes `aria-live`, errores vinculados al campo y `prefers-reduced-motion`.
- Motion propuesto a partir del roadmap: diálogos 160–200 ms al abrir y 120–150 ms al cerrar; menús 120–150 ms; navegación 150–200 ms; drawer 180–220 ms; toast 160–200 ms. CSS/Tailwind y `x-transition` solo para estado local; `wire:loading` y feedback de tabla sin animar cifras contables. Al reducir movimiento, eliminar transformaciones no esenciales.

## E. Arquitectura propuesta

### Frontend, comparado con el starter

El starter no establece una convención propia de componentes Livewire. Para T03–T06, proponer **una** convención de clase PHP + vista Blade (como sugieren los roadmaps) y comprobar las APIs de Livewire 4 instalado antes de generarla. `layouts/app.blade.php` contendría navegación, barra superior, área principal y raíz de notificaciones; `resources/views/components/adara/` alojaría primitivas Blade con variantes explícitas; `app/Livewire/` y `resources/views/livewire/` agruparían páginas y formularios por módulo. Centralizar tokens semánticos en `resources/css/app.css` con Tailwind 4; usar rutas nombradas. `resources/js/app.js` queda para interacciones cliente estrictamente necesarias y gráficos Chart.js inicializados una sola vez y limpiados en navegación. No crear carpetas hasta T03 autorizado.

Construir solo componentes observados: botón/icon button, campo de texto/textarea/select, moneda/cantidad/fecha según flujo, búsqueda, badge, card/stat card, tabla paginada, filtros y tabs, menú desplegable, modal/confirmación, toast/alert, estados vacío/carga/error, page header/breadcrumbs. `Checkbox`/`Radio` aparecen en preferencias u opciones; `Drawer`, `Skeleton` y `Tooltip` requieren confirmar su necesidad concreta antes de implementarlos. La tabla, modal y campos deberán componer contenido sin duplicar lógica de negocio. Alpine debe tratar menú, foco y apertura local; carrito, precio y total los decide el servidor.

### Backend, para tickets posteriores

Monolito Laravel simple: **Livewire → Action/Service → Eloquent → SQLite**. Modelos para catálogo, proveedores/clientes, pedidos/recepciones, balances y movimientos, ventas/líneas, configuración, lotes de importación y respaldos según el esquema aceptado en T02. Usar Actions transaccionales para crear/recibir compras, ajustar stock, vender, anular y restaurar; servicios puros para prorrateo, valoración y precio sugerido; consultas paginadas/servicio de reportes cuando la complejidad lo exija. Enums solo para estados o métodos estables; Policies si aparecen permisos reales; reglas de validación y excepciones donde aporten claridad. Sin DTO ni Jobs por costumbre, ni capa Repository genérica.

Dinero en centavos enteros y cantidades enteras; guardar valor total de inventario y costo histórico de venta; garantizar unicidad e idempotencia con restricciones SQLite y transacciones. Decidir el algoritmo de valoración, restos de centavos, devoluciones y descuentos en T02. `lockForUpdate()` no reemplaza control de concurrencia de SQLite. Respaldos mediante snapshot consistente, verificación y restauración ensayada. La usuaria final necesita PHP y assets construidos localmente en macOS, pero el lanzador queda en T28.

### Pruebas propuestas

PHPUnit es la herramienta instalada. En unitarias: prorrateo exacto (incluido $1,01 en 3 líneas), redondeo, costo/valor residual, sugerencia de precio y sanitización CSV. En feature con SQLite aislada: SKU único, stock inicial, recepción parcial y doble, venta multítem con fallo intermedio, insuficiencia de stock, reintento, costo histórico, anulación única, integridad referencial, reportes, importación idempotente, backup y restauración fallida/exitosa. Para frontend: renderizado Livewire, validaciones y estados; pruebas manuales de teclado/foco, diálogos, responsive 1366×768 y 1440×900, zoom 125 %, navegación repetida y funcionamiento sin red. La matriz Q01–Q28 de `04_CALIDAD_Y_PRUEBAS.md` es la referencia, adaptada a decisiones aprobadas. No se instalaron paquetes ni se añadieron tests en T00/T01.

## F. Mapa de navegación

Inicio `/` → Productos `/productos` → Detalle `/productos/{product}` → Stock `/stock`; Pedidos `/pedidos` → Detalle `/pedidos/{order}` → Recepciones `/recepciones`; Nueva venta `/ventas/nueva` → confirmación → Historial `/ventas` → detalle; Reportes `/reportes`; Configuración `/configuracion` → Respaldos `/respaldos`. Clientes `/clientes`, Proveedores `/proveedores`, Categorías `/categorias` y Ayuda `/ayuda` son destinos propios. `/dev/componentes` sería solo local para P16. Son **rutas propuestas** y no endpoints existentes; consultar la matriz por pantalla en `08_INVENTARIO_VISUAL.md`.

## G. Riesgos técnicos y brechas

| Prioridad | Hallazgo y efecto | Acción propuesta |
| --- | --- | --- |
| Bloqueante para T07+ | No están aprobados valoración, restos de centavos, descuentos, parcialidad y anulación; afectan tablas y resultados económicos. | Resolver T02 antes de migraciones comerciales. |
| Bloqueante para distribución Mac | No existe aún `docs/05_MACOS_OFFLINE.md`, lanzador, build final ni respaldo restaurable. | Documentar y ejecutar T25–T29, con prueba Mac real. |
| Alta | Vite declara fuente Bunny `Instrument Sans` mientras el diseño pide Segoe UI/Inter y Georgia; el plugin puede obtenerla en build. `@fonts`, `public/hot` y el manifest dev dependen del servidor Vite en desarrollo. | En T03 decidir fuentes locales/fallback y retirar HMR del paquete final; verificar Network sin internet. |
| Alta | `.env.example` conserva Laravel/inglés/localhost y la UI actual tiene marca Laravel, enlaces externos y diseño ajeno a Adara. | Corregir en tickets de shell y despliegue, sin tocar `.env` real durante esta auditoría. |
| Alta | Ausencia de Git activo; `AGENTS.md` y configuración Boost están ignorados por `.gitignore`. Riesgo de perder reglas y trazabilidad en otra máquina. | Definir repositorio y política de archivos compartidos en ticket aprobado, excluyendo datos y secretos. |
| Alta | SQLite usa `DEFERRED` y no define política explícita de timeout/journal; doble confirmación y dos instancias del lanzador pueden competir. | Ensayar serialización/idempotencia con SQLite antes de operaciones de stock y venta. |
| Media | M18 del HTML descarta venta en curso, mientras roadmap también habla de anular venta guardada; M15 dice eliminar aunque el dominio requiere archivar. | Separar acciones y vocabulario en T02/T05; no trasladar el botón destructivo literalmente. |
| Media | El prototipo no acredita foco atrapado, retorno de foco, asociación de labels ni contraste. | Incorporar criterios accesibles al sistema de componentes. |
| Media | `composer.json` mantiene Sail pese a prohibición de Docker en la solución; Chart.js y Lucide aún no son usados, y otros paquetes de desarrollo aumentan mantenimiento. | Evaluar dependencia por dependencia al construir; no quitar nada durante T00. |
| Baja | `00_inicio.svg` duplica referencia de inicio, y los SVG de pantallas son mayormente textos trazados. | Usar HTML/mapa y plancha como apoyo semántico, SVG como referencia visual. |

No se observó un problema de integridad en el archivo SQLite (`integrity_check=ok`), pero esto no valida operaciones comerciales que aún no existen ni constituye prueba de restaurabilidad.

## H. Decisiones pendientes

1. Confirmar valoración por costo promedio ponderado basado en valor total de inventario y política al volver stock a cero; elegir redondeo exacto y desempate de centavos para flete, recepciones parciales, descuentos y costos de salida.
2. Confirmar recargo predeterminado (el diseño propone 200 % sobre costo), múltiplo de redondeo de precio sugerido ($100), si se actualiza el precio de venta al recibir compras y qué hacer con cambios manuales.
3. Definir descuentos, impuestos o cargos, métodos de pago y si una venta admite varios medios; qué significa “anular” una venta confirmada, motivo obligatorio y cómo reingresa su valor al stock. Distinguirlo de descartar el carrito M18.
4. Precisar si hay recepciones parciales y costo real distinto al pedido; cómo corregir una recepción o pedido ya confirmado.
5. Confirmar marca como campo libre o entidad; política de SKU sin distinción de mayúsculas; archivo de productos/categorías vinculadas; cliente opcional y datos personales mínimos.
6. Acordar formato de importación/exportación realmente necesario (`.xlsx`, CSV, PDF), reglas para duplicados y destino del respaldo en macOS. El selector de carpeta del prototipo Windows no se traslada literalmente a Mac.
7. Acordar modo de instalación y actualización local, ubicación persistente de SQLite fuera del código versionado, una sola instancia del servidor, acceso solo por loopback y procedimiento de recuperación.
8. Confirmar si P16 debe quedar accesible únicamente en desarrollo, y si el modal de eliminación M15 se renombra a “Archivar producto” en producción.

## I. Próxima etapa

**T02 — Decisiones de dominio/ERD**, según `03_PLAN_DE_ENTREGAS.md`. Entregable esperado: respuestas aprobadas, relaciones/estados, reglas monetarias y esquema propuesto antes de crear migraciones. Paralelamente, recuperar o redactar `00_CONTEXTO_Y_DECISIONES.md`, `05_MACOS_OFFLINE.md` y `06_PROMPT_INICIAL_CODEX.md` en el alcance que autorice el siguiente ticket. T03 (tokens y layout) empieza después de aprobación del contrato visual y de su propio ticket; no se ejecutó aquí.

## J. Criterios de aceptación de T00/T01

- [x] Ubicación real, documentos presentes/ausentes y estado de Git identificados.
- [x] Versiones de herramientas y dependencias directas verificadas sin inferir desde el roadmap.
- [x] Ruta, estructura Laravel, migraciones y esquema SQLite inspeccionados sin modificar datos; `integrity_check=ok`.
- [x] Configuración y skills de Boost identificadas; consultas MCP principales ejecutadas y ausencia de `list_routes` declarada.
- [x] Tokens, layout, 17 pantallas, 20 modales y componentes trazados a recursos originales en el inventario asociado.
- [x] Arquitectura, estrategia de pruebas, riesgos y decisiones pendientes registrados.
- [ ] Comparación visual de la futura implementación a 1366×768/1440×900 y pruebas funcionales: corresponden a T03+ porque aún no existen pantallas Adara.

### Comprobaciones realizadas

Lecturas de `AGENTS.md`, documentación disponible, manifiestos, configuración, rutas, migraciones, tests, diseño JSON/HTML/SVG y plancha PNG; `php -v`, `composer --version`, `node --version`, `npm --version`, `composer show --direct`, `npm ls --depth=0`, `php artisan --version`, `php artisan route:list --except-vendor --no-interaction`, `php artisan migrate:status --no-interaction`, `php -m`; SQLite con URI `mode=ro` para `sqlite_master`, índices, FK y `PRAGMA integrity_check` en la inspección previa; `git status`/`git remote` (sin repositorio). En esta revisión se ejecutaron los MCP de Boost `application_info`, `database_connections`, `database_schema`, `database_query` (solo `SELECT`) y `search_docs`. No se ejecutaron migraciones, seeders, tests, build ni escrituras a SQLite.
