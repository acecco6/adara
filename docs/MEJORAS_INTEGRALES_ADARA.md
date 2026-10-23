# Seguimiento de auditoría y mejoras integrales — Adara

Actualizado: 23/10/2026. Este documento es un índice de continuidad, no una autorización para implementar todos los módulos en lote. Se trabaja por sección y ticket; las reglas R01–R63 de `REGLAS_ECONOMICAS_ADARA.md` son normativas.

## Estado general

- Versiones confirmadas por Laravel Boost: PHP 8.4, Laravel 13.38.0, Livewire 4.4.7, Tailwind 4.3.3, Vite 8.3.4; base configurada: SQLite.
- Rutas verificadas con `php artisan route:list --except-vendor --no-interaction`: 26 rutas de aplicación; incluyen catálogo, productos, stock, compras/recepciones, ventas/cobros, clientas/proveedores, categorías, reportes, configuración, respaldos, ayuda y autenticación.
- El plan maestro marca implementados T03–T20 y T22–T24A; T21 se pospone explícitamente (no importar Excel); T25–T29 siguen pendientes (backup/restauración, UI de backups/ayuda, lanzador y QA Mac).
- El proyecto usa SQLite por decisión de producto, aunque algunos documentos históricos mencionan motores adicionales. No cambiar el motor ni actualizar dependencias como parte de esta auditoría.
- `docs/00_CONTEXTO_Y_DECISIONES.md`, `docs/05_MACOS_OFFLINE.md` y `docs/06_PROMPT_INICIAL_CODEX.md` referenciados por `AGENTS.md` no están en `docs/`. `docs/07_ESTADO_PROYECTO.md` contiene una auditoría histórica de octubre 2026 y describe el starter anterior a la implementación actual; debe leerse como histórico, no como estado vigente.
- Las pruebas actuales cubren comportamientos de negocio mediante PHPUnit y SQLite aislada. La presencia de tests no sustituye los pasos manuales de Mac/offline indicados por `04_CALIDAD_Y_PRUEBAS.md`.

## Inventario por sección

| Sección | Rutas/clases principales | Estado observado | Prioridad / siguiente paso |
| --- | --- | --- | --- |
| Inicio / dashboard | `/`, `Dashboard` (verificar clase y vistas al iniciar T23) | T23 marcado implementado | Media: auditoría puntual de KPIs y rangos de fecha contra R19–R21. |
| Productos / catálogo interno | `/productos`, `/productos/{product}`, `ProductController`, Livewire Products | T07–T09 implementados | Media: auditar validación, límites de búsqueda y consultas en un ticket propio. |
| Categorías | `/categorias`, Livewire Categories | T09 implementado | Baja: probar archivado y asociaciones cuando se audite catálogo. |
| Stock | `/stock`, Livewire Inventory | T10–T11 implementados | Alta: conciliación de saldos con movimientos y política de ajustes conforme R. |
| Proveedores | `/proveedores`, Livewire Suppliers | T09 implementado | Media: verificar unicidad, archivo y consistencia con pedidos. |
| Compras | `/pedidos`, `/pedidos/{order}`, `/recepciones` | T12–T15 implementados | Alta: T25 no depende de compras; revisar límites de recepción solo en ticket específico. Pendiente normativo anotado en plan: reversión de recepción R27. |
| Ventas | `/ventas/nueva`, `/ventas`, Livewire Sales | T16–T20 implementados | Alta: cualquier auditoría debe leer R01–R63 y conflictos antes de cambiar lógica. |
| Cobros / clientas | `/cobros`, `/clientes`, `/clientes/deudas` | T19 implementado con pendientes documentados | Alta: cobros anulados R26 y sugerencia de comisión/límite de crédito requieren tickets y decisiones propias. |
| Reportes | `/reportes`, Reports | T22 implementado | Media: reconciliar por fecha efectiva, snapshots y anulaciones, sin redefinir métricas económicas. |
| Configuración / acceso | `/configuracion`, Auth controllers y middleware | T24 y autenticación implementados | Alta: revisar cobertura de rutas privadas y restricción del catálogo en auditoría separada. |
| Catálogo público | `/catalogo`, detalle, imágenes públicas | T24A implementado; detalle modal aislado en Livewire | En progreso: revisar secuencia de animación/estado del modal; pruebas funcionales existentes pasan. No declarar optimización medida sin trazas comparables. |
| Backups / restauración | `/respaldos` aún presenta estado en preparación; sin flujo confirmado | T25–T27 pendientes | Crítica antes de uso real con datos: comenzar por diseño/implementación verificable de snapshot y restauración en copia temporal, según plan. |
| Lanzador / QA Mac | Sin ruta; docs Mac citada ausente | T28–T29 pendientes | Bloquea entrega a Mac: recuperar/definir requisitos de entorno destino y ensayar backup/restauración antes de release. |

## Hallazgos y criterios de prioridad

1. **Alto — respaldo y recuperación no terminados:** T25–T27 están pendientes. Antes del uso con datos reales se necesita validar copia consistente y restauración en entorno temporal; no ejecutar migraciones destructivas sobre la base local.
2. **Alto — preparación Mac no verificada:** T28–T29 siguen pendientes y la guía `05_MACOS_OFFLINE.md` no existe en la ruta declarada. No se puede afirmar compatibilidad de distribución hasta realizar QA en Mac real.
3. **Medio — catálogo modal:** la implementación reciente separó el detalle en componente Livewire, valida catálogo habilitado y SKU activo, presenta skeleton y usa Web Animations API. Última revisión funcional: tests de `PublicCatalogTest` pasan (7 tests, 49 aserciones); falta registrar medición de red/servidor antes/después si se reclama mejora cuantitativa.
4. **Transversal — reglas pendientes:** `CONFLICTOS_Y_PENDIENTES.md` y el backlog T19/T20 enumeran asuntos que requieren tickets específicos (anulación de cobros, reinversión de recepción y otros). No tomar decisiones económicas durante auditorías generales.

## Registro de trabajo

| Fecha | Sección / ticket | Trabajo y archivos | Verificación | Estado |
| --- | --- | --- | --- | --- |
| 23/10/2026 | Catálogo público / T24A | Modal de producto aislado, skeleton, eventos y transición JavaScript en `resources/views/components/adara/modal.blade.php`; clase y vista `PublicCatalogProductDetail`; pruebas `PublicCatalogTest`. | 7 pruebas / 49 aserciones aprobadas en la última ejecución; Pint aprobado. No hay medición comparativa de rendimiento. | Implementado; verificación funcional aprobada; revisión visual de secuencia pendiente. |
| 23/10/2026 | Auditoría inicial | Este documento; inventario de rutas, fases del roadmap y ausencias documentales. | `route:list`; versiones por Boost; inspección de `03_PLAN_DE_ENTREGAS.md`, `04_CALIDAD_Y_PRUEBAS.md`, `07_ESTADO_PROYECTO.md`, `REGLAS_ECONOMICAS_ADARA.md` y `CONFLICTOS_Y_PENDIENTES.md`. | Inventario inicial; no equivale a auditoría exhaustiva de cada módulo. |

## Continuación ordenada

1. Completar el ticket en curso del modal de catálogo y su prueba visual, conservando foco/teclado y evitando respuestas tardías obsoletas.
2. Iniciar T25 conforme al `03_PLAN_DE_ENTREGAS.md`; añadir pruebas de integridad y restaurabilidad usando una base temporal y sin tocar la base real.
3. Seguir con T26–T27; no marcar backups terminados hasta restaurar una copia de prueba.
4. Resolver los pendientes normativos por ticket individual y antes de editar flujos económicos.
5. Completar T28–T29 en Mac real; documentar entorno y resultado manual.

## Límites del inventario

El listado de secciones se contrastó con rutas y roadmap. No se hizo revisión exhaustiva línea por línea de todos los modelos, componentes, consultas, permisos ni pantallas; las rutas `Route::view` pueden delegar en componentes/partials y requieren inspección por sección. La ausencia de mediciones detalladas de consultas y perfilado impide cuantificar mejoras de rendimiento fuera de las mediciones que se registren explícitamente.
