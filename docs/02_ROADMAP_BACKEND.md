# 02 — Roadmap BACKEND | Adara Gestión

> Stack: Laravel 13 + PHP 8.4 + Eloquent + SQLite. Una instalación local, una usuaria, moneda ARS, sin conexión de red. **Este documento es una arquitectura propuesta, no una afirmación de que las tablas ya existen.** Codex debe auditar migraciones y código antes de implementarlo.

## Principios de diseño

1. Monolito modular, simple, sin capas ceremoniales ni repositorios genéricos sobre Eloquent: **Livewire → Action/Service → Eloquent → SQLite**.
2. Separar comandos (mutan datos) de consultas (read models/queries). Los cálculos monetarios y de stock **nunca dependen del frontend**.
3. Usar SQL y transacciones al registrar ventas, recepciones, anular ventas o ajustar stock. Tratar dobles clics, reintentos y fallos entre pasos.
4. Manejar dinero como `int` en centavos con helpers explícitos y redondeos definidos; no cast `float`, no `DECIMAL` tratado como float en PHP.
5. Movimientos de inventario auditables, costo en el momento de cada venta, modelo de estados. No hacer hard-delete de registros históricos.
6. Preferir lo sencillo y testeable para una sola Mac. **No** Redis, MySQL, microservicios, SaaS, colas obligatorias para operaciones que deben confirmarse al instante.
7. Todas las fechas guardadas con convención temporal consistente y mostradas en `America/Argentina/Buenos_Aires`.

## B0 — Descubrimiento técnico y ADR

- [ ] Revisar `composer.json`, lockfiles, `.env.example`, migraciones y SQLite actual, tests y config (`database.php`, `app.php`).
- [ ] Confirmar PHP extensions `pdo_sqlite`, `sqlite3`, intl, fileinfo, zip, mbstring según importaciones; probar capacidades en Windows.
- [ ] Revisar `php artisan migrate:status` y confirmar base de pruebas separada de la base real.
- [ ] Definir relaciones/estados/casos límite en un ERD simple o `docs/ERD.md` antes de migrar.
- [ ] Resolver con usuario decisiones comerciales marcadas como «pendientes» en `00_CONTEXTO_Y_DECISIONES.md` que condicionan contabilidad/operaciones.
- [ ] Crear ADR cortos si se cambia costo promedio, prorrateo de flete, estrategia del launcher o convenciones monetarias.

**Salida B0:** diagnóstico + ADR + propuesta de migraciones; no ejecutar migraciones destructivas.

## Esquema relacional propuesto (adaptar tras inspección)

| Entidad/tabla | Campos/relaciones principales | Invariantes |
| --- | --- | --- |
| `categories` | id, name, active, timestamps | nombre único normalizado por criterio acordado |
| `suppliers` | id, name, phone?, email?, notes?, active | nombre obligatorio |
| `customers` | id, name, phone?, email?, notes?, active | cliente opcional al vender |
| `products` | id, code unique, name, description?, category_id?, supplier_id?, image_path?, price_cents, minimum_stock, active, timestamps | SKU único, no borrar con historial |
| `inventory_balances` | product_id unique, quantity_on_hand int, stock_value_cents int, updated_at | jamás negativo salvo política explícita |
| `stock_movements` | id, product_id, type, quantity_delta int, value_delta_cents int, source_type?, source_id?, reason?, at | append-only; origen rastreable |
| `purchase_orders` | id, number unique, supplier_id, status, ordered_at, shipping_cents, allocation_method, note?, timestamps | no recibir pedido no confirmado |
| `purchase_order_items` | id, order_id, product_id, quantity_ordered, unit_cost_cents, allocated_shipping_cents, quantity_received | entrega parcial segura y trazable |
| `purchase_receipts` | id, number unique, order_id, idempotency_key unique, received_at, status | una recepción no se confirma dos veces |
| `purchase_receipt_items` | id, receipt_id, order_item_id, quantity, goods_cost_cents, shipping_allocated_cents, inventory_value_added_cents | suma de asignaciones reconciliada |
| `sales` | id, number unique, customer_id?, sold_at, status, payment_method, subtotal_cents, discount_cents, total_cents, idempotency_key unique, void_reason?, voided_at? | toda venta auditable; total consistente |
| `sale_items` | id, sale_id, product_id, product_name_snapshot, code_snapshot, quantity, unit_price_cents, gross_cents, allocated_discount_cents, net_cents, cost_total_snapshot_cents | precio y costo históricos inmutables |
| `settings` | key unique, value (typed/JSON), updated_at | defaults claros, validación |
| `import_batches` | id, filename, checksum, status, counts, warnings?, imported_at | idempotencia/seguimiento import |
| `backup_records` | id, filename, created_at, size_bytes, checksum, schema_version, status | trazabilidad sin guardar rutas inseguras |

Opcionales según decisiones: `payments` si se admiten múltiples medios por venta, `sale_returns`/`return_items` si se admiten devoluciones parciales; no introducir sin aprobación. Brands como texto en productos al inicio si el catálogo no requiere mantenimiento separado.

**SQLite:** activar claves foráneas (`foreign_key_constraints`), crear índices en códigos/estados/fechas/foreign keys, revisar `busy_timeout`, journal mode y transacciones; pruebas con SQLite real. Si las escrituras van a ser infrecuentes, mantener diseño simple. Nunca confiar en `lockForUpdate()` como única protección: SQLite no implementa bloqueo de filas equivalente a motores cliente-servidor.

## B1 — Catálogo, categorías, proveedores y clientes

- [ ] Migraciones y factories de `products/categories/suppliers/customers` y stocks.
- [ ] `CreateProductAction`, `UpdateProductAction`, `ArchiveProductAction` con validaciones únicas de SKU, nombre y precios positivos/no negativos según negocio.
- [ ] SKU normalizado consistentemente (`trim`, case policy), rechazo de duplicados y validación de relaciones activas.
- [ ] `InitialStockAction` que registre saldo inicial como **movimiento**, con origen importación/ajuste justificado; evitar `products.stock = ...` sin auditoría.
- [ ] Consultas paginadas, filtros por categoría/nombre/código/estado, eager loading y orden estable.
- [ ] Borrado lógico/archivado de productos con historial. Categorías o proveedores relacionados: bloquear delete físico o reasignación explícita.

**Pruebas:** SKU duplicado por normalización, stock inicial auditable, archivo conserva ventas, alta inválida no cambia DB, filtros/paginación.

## B2 — Servicio monetario, valoración y precios

**Definiciones:**

- `cents` = enteros; parsear ingresos «12.500,50» de forma explícita (evitar `float`); formatear en es-AR solo para mostrar.
- Costo de compra = suma de cantidades × costo unitario en centavos.
- Flete por pedido = `shipping_cents` asignado **exactamente una vez** entre líneas; `sum(allocated_shipping_cents) == shipping_cents`.
- Métodos: **por cantidad** o **proporcional al valor de mercadería**. Asignación por pesos enteros, repartir centavos residuales determinísticamente (mayor resto con desempate estable por ID/posición) y preservar la suma.
- Precio sugerido inicial (pendiente de validación): costo promedio × 3 para recargo del 200%; redondeo configurable al múltiplo $100. Por defecto NO sobrescribir silenciosamente el precio de venta actual al entrar stock; proponer/confirmar actualización.
- Margen bruto sobre ventas = `(ventas netas - costo vendido) / ventas netas`. Recargo sobre costo = `(precio-costo)/costo`. Son conceptos distintos; denominador 0 se maneja sin división por cero.

**Valoración recomendada:**

- Guardar **cantidad e importe total del valor de inventario** como enteros (`inventory_balances.quantity_on_hand`, `stock_value_cents`). Esto permite costear sin acumular error por costos medios fraccionarios.
- En entrada: sumar unidades y `goods_cost_cents + shipping_allocated_cents` (datos realmente recibidos, no estimados del pedido).
- En salida de `q` unidades con saldo previo `Q` y valor previo `V`: asignar `cost_removed_cents` proporcional mediante una función entera determinística, **sin pasar `float`**. Al vender la última unidad, remover exactamente el remanente de valor. Guardar costo total retirado en `sale_items`.
- Si un comprobante pide costo unitario, usar cociente redondeado **solo para visualización**; el reporte financiero debe usar costo total congelado exacto.

**Pruebas B2 obligatorias:** envíos con $0, 3 líneas y resto impar de centavos, cantidades y valores iguales/desiguales, recepción parcial, stock en cero, retiro de todas las unidades, coste fraccionario, precio sugerido con distintos redondeos.

## B3 — Pedidos y recepción (núcleo del stock)

Estados propuestos: `draft`, `ordered`, `partially_received`, `received`, `cancelled`. Ajustar si el circuito real requiere menos.

- [ ] `CreatePurchaseOrderAction` valida proveedor, líneas, costos, cantidades, método de flete, número único.
- [ ] `AllocateShippingService` asigna flete a líneas al confirmar el pedido; **nunca lo vuelve a sumar completo por recepción**.
- [ ] `ReceivePurchaseOrderAction` valida cantidades pendientes por línea y totales recibidos, genera `receipt` + ítems + movimientos, incrementa saldos e importes de stock en **una transacción**.
- [ ] Entregas parciales: prorratear la asignación original de flete de cada línea entre recepciones; entregar el remanente de centavos en la **última recepción** de la línea, de manera que la suma final coincida.
- [ ] Idempotencia: `idempotency_key` único, restricción de no exceder `quantity_ordered`, actualización condicional de recibido para prevenir carreras. En SQLite evaluar `BEGIN IMMEDIATE`/serialización por transacción si es necesario, y probar con reintentos.
- [ ] Estados se derivan de recibido real, nunca solo de un flag cambiado desde Livewire; no editar una recepción confirmada, usar reversión/auditoría si se habilita más adelante.

**Pruebas B3:** recepción completa, parcial 2 veces, reintento misma clave, exceso de cantidad, caída a mitad de transacción, flete asignado exactamente 1 vez, saldos y valor de stock reconciliados.

## B4 — Venta, pago registrado y anulación

Flujo: carrito solo como intención → servidor recarga precios/stock → valida → confirma operación atómica → registra ítems y costo → descuenta inventario → responde con ID.

- [ ] `CreateSaleAction`: validar cantidades enteras positivas, productos activos, stock suficiente, precio/desc por regla de negocio, medio de pago permitido; calcular totales **en servidor**.
- [ ] Generar `sales.number` único legible, garantizado por índice. La idempotency key se genera por intento de venta y permanece durante reintentos de esa confirmación.
- [ ] En `DB::transaction()` crear sale, sale_items con snapshot, debitar balance y agregar stock_movements. Si una línea falla, revertir todo. Actualizar stock mediante condición atómica (`quantity_on_hand >= quantity`) y validar filas afectadas; no asumir exclusividad por `lockForUpdate()`.
- [ ] Si se define descuento sobre venta, repartir los descuentos a líneas en centavos y guardar monto neto real por línea; documentar política antes de implementar.
- [ ] `VoidSaleAction`: confirmar razón, status, reversión de cantidad y valor **con movimientos inversos**, una sola vez; preservar registro de venta, no borrar. Si las compras posteriores alteraron valoración, la reintegración usa valor/costo histórico de esa venta y se documenta impacto de costeo.
- [ ] Registro de método de pago es informativo: efectivo, transferencia, tarjeta u «otro», según decisión; no procesar pagos ni acceder a bancos.
- [ ] Comprobante imprimible simple/PDF opcional, **no fiscal**, con identificador, fecha, ítems y total.

**Pruebas B4:** carrito multítem, una unidad restante, stock cero, precio alterado en navegador, doble clic/reintento, error DB parcial, varias ventas concurrentes simuladas, anulación doble, valor histórico tras comprar más caro, recarga de página al confirmar.

## B5 — Importación inicial y exportaciones

- [ ] Antes de instalar dependencias, evaluar si `PhpSpreadsheet` es indispensable; para XLSX sí, para CSV usar herramientas PHP estándar; usar Composer y fijar versión en lockfile.
- [ ] Nunca cargar ciegamente todo el Excel. Primero analizar hojas, encabezados, fórmulas y distinguir valores calculados de campos editables.
- [ ] Asistente `upload → validar MIME/extensión/tamaño → preview → mapear columnas → detectar duplicados → confirmar`.
- [ ] No importar automáticamente entradas `EJ-*`, `Test` o datos ilustrativos; ofrecer vista previa y filtro consciente.
- [ ] Importación transaccional por lotes pequeños, con `import_batches` y conteo importadas/omitidas/error; proteger de reimportaciones duplicadas por checksum y SKU.
- [ ] Preguntar si se importa únicamente el stock vigente o también pedidos/ventas históricos. No inferir rentabilidad pasada sin costo de la fecha original.
- [ ] Exportar CSV UTF-8 con BOM si Excel lo requiere, separador apropiado para región; proteger contra CSV formula injection en campos controlados por usuario (`=`, `+`, `-`, `@`, tab, CR). XLSX/PDF opcional según alcance confirmado.

**Pruebas B5:** encabezados desordenados, vacíos, duplicados, flete inconsistente, archivo malicioso/inválido, 0 filas, importación repetida, CSV injection, encoding acentos y fechas.

## B6 — Reportes y lecturas consistentes

- [ ] `ReportService` sin lógica monetaria duplicada por componentes.
- [ ] Filtros por período explícitos (inclusive start y fin exclusivo, usando timezone correcto), categoría/producto/medio de pago.
- [ ] KPIs: ventas brutas y netas, número de operaciones, costo histórico vendido, **ganancia bruta**, margen, unidades, bajo stock.
- [ ] Estados `completed` y `voided` definidos: excluir anuladas de ventas netas o reflejar ajustes de forma trazable; no sumar como ventas activas.
- [ ] Agrupar fechas según `America/Argentina/Buenos_Aires`, limitar cardinalidad para gráficos y paginar detalle.
- [ ] Si no hay datos, mostrar cero/estado vacío en vez de muestras aleatorias; no mezclar registros de prueba con reportes reales.
- [ ] Índices en rangos/orden, evitar N+1; probar con volumen representativo (p. ej. 5.000 productos, 10.000 ventas sintéticas en entorno de prueba).

**Pruebas B6:** cambio de zona horaria, fin de mes, cero ventas, ventas anuladas, diferencia entre margen/recargo, correlación de KPI y detalle.

## B7 — Configuración, ayuda y robustez

- [ ] Tipar claves `settings` con defaults documentados: `business_name`, `markup_percent`, `price_rounding_cents`, `low_stock_threshold`, `backup_preferences`.
- [ ] Validar configuración antes de escribir, no alterar ventas históricas.
- [ ] Configurar locale español y manejo amable de validaciones excepciones; jamás mostrar stacktrace a usuaria.
- [ ] Logs locales mínimos, rotados, sin teléfonos de clientes ni secretos, con IDs de operación para diagnóstico.
- [ ] Si no hay conexión, seguir funcionando; no usar colas diferidas para ventas/recepciones críticas.

## B8 — Respaldos y restauración: **condición de salida del MVP**

- [ ] `BackupService` crea **snapshot consistente de SQLite** (SQLite backup API de PHP `SQLite3::backup` cuando sea viable, o mecanismo soportado y probado; no copiar base abierta arbitrariamente con WAL activo).
- [ ] Comprimir/cifrar solo si hay política de clave/recuperación clara; no incluir `.env` ni archivos secretos en un backup sin necesidad. Si hay imágenes de productos, incluirlas con manifiesto.
- [ ] Metadatos (`schema_version`, `app_version`, `created_at`, `sha256`, tamaño), comprobar espacio disponible, `PRAGMA integrity_check` y/o abrir la copia para validar lectura.
- [ ] Rotación local configurable; opción manual guardar a pendrive; no automatizar subidas a nube.
- [ ] Restauración **fuera de operaciones activas**: preflight versión/formato/checksum, respaldo preventivo, reemplazo atómico en condiciones seguras, migración si compatible, health check y reversión frente a fallo.
- [ ] No confiar en nombre de archivo/rutas ingresadas por UI sin normalizar y validar. Los scripts de restauración deben operar solo en carpeta de datos habilitada.
- [ ] Simular fallo por cierre abrupto, interrupción de copia, DB dañada, espacio insuficiente y versión incompatible.

**Pruebas B8:** copia válida abre y contiene cantidades/ventas exactas; prueba de restauración recupera estado; falla no sobrescribe DB original; rotación no borra última copia sana.

## B9 — Producción local Mac: compatibilidad y migraciones

- [ ] Separar `source/app` y SQLite/archivos de usuario; confirmar ruta en `docs/05_MACOS_OFFLINE.md`.
- [ ] `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=http://127.0.0.1:<puerto>` y `APP_KEY` única, no versionada; verificar middleware CSRF/cookies de sesión para localhost.
- [ ] Bundle local: `composer install --no-dev --optimize-autoloader`, `npm run build` en máquina de preparación (evitar necesitar Node en Mac), `php artisan migrate --force` luego de backup y prueba de versión.
- [ ] Probar en **Mac real**, incluida arquitectura Intel/Apple Silicon, PHP/SQLite extensions, permisos y corte de energía.
- [ ] No desplegar `vendor` de Windows en Mac sin verificar extensiones/binarios/dependencias nativas. Preparar Composer en macOS o construir `vendor` para el destino.
- [ ] Diagnóstico en caso de puerto ocupado o servidor caído; sin acceso a la red local (bind solo a loopback).

## Organización sugerida del código

```text
app/
  Actions/Catalog/{CreateProductAction,UpdateProductAction,ArchiveProductAction}.php
  Actions/Inventory/{InitialStockAction,AdjustStockAction}.php
  Actions/Purchases/{CreatePurchaseOrderAction,ReceivePurchaseOrderAction}.php
  Actions/Sales/{CreateSaleAction,VoidSaleAction}.php
  Actions/Backup/{CreateBackupAction,RestoreBackupAction}.php
  Services/Pricing/{AllocateShippingService,SuggestPriceService,InventoryCostService}.php
  Services/Reporting/ReportService.php
  Services/ImportExport/SpreadsheetImportService.php
  Models/{Product,Category,Supplier,Customer,InventoryBalance,StockMovement,
          PurchaseOrder,PurchaseOrderItem,PurchaseReceipt,PurchaseReceiptItem,
          Sale,SaleItem,Setting,BackupRecord,ImportBatch}.php
  Enums/{PurchaseOrderStatus,SaleStatus,StockMovementType,PaymentMethod}.php
  Livewire/...
```

Esto **no impone** patrón repository, DTO para cada campo ni interfaces donde solo haya una implementación. Crear clases solo si aíslan reglas, mejoran tests o evitan duplicación.

## Reglas generales de pruebas

- Unit: prorrateo determinista en centavos, costo promedio, precio sugerido, CSV sanitization.
- Feature (SQLite real): creación/recepción/anulación, stock, idempotencia, API Livewire/acciones, integridad FK, importación y restauración.
- Regresiones: al corregir bug económico crear test que reproduzca error antes del fix.
- Performance básica: índices y consultas, paginate; `DB::listen` / profiler **solo en desarrollo**.
- Verificar que `php artisan test` funcione en Windows antes de cualquier entrega.

## Definición de terminado de BACKEND

1. Ventas y recepciones actualizan exactamente una vez, nunca dejan saldo inconsistente.
2. La suma de fletes prorrateados coincide al centavo con el flete original.
3. El costo histórico de cada venta no cambia si se modifica el costo actual.
4. Sin `float` en cálculos monetarios; sin ventas de stock inexistente por error de concurrencia.
5. Operaciones irreversibles con estado, razón/auditoría y pruebas.
6. Importación revisable, informes reproducibles y respaldo restaurable offline.
7. Migraciones y factories reproducibles sin borrar datos del usuario.

## Fuentes técnicas oficiales

- Laravel 13: https://laravel.com/docs/13.x
- Database/testing: https://laravel.com/docs/13.x/database-testing
- SQLite transactions: https://www.sqlite.org/lang_transaction.html
- SQLite backup API: https://www.sqlite.org/backup.html
- PHP SQLite3 backup: https://www.php.net/manual/en/sqlite3.backup.php
