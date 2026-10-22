# 03 — Plan maestro de entregas | Adara Gestión

**Regla:** implementar verticalmente; diseñar y probar un flujo real antes de agregar el siguiente. Codex debe terminar **solo el ticket solicitado** y entregar pruebas. Nada de «construir todo el CRM y los 20 modales en un único commit».

## Estado al inicio del roadmap

- [x] PHP/Composer/Node/npm/Git presentes en Windows (capturas iniciales).
- [x] Laravel accesible en `127.0.0.1:8000` y SQLite habilitado.
- [x] Livewire 4.4.7 instalado; `composer dev` en funcionamiento.
- [ ] Auditoría real del código y fuentes de diseño por Codex (ticket T00).
- [ ] Decisiones de dominio críticas cerradas con dueño del producto.
- [ ] Pantallas Adara de producción implementadas.

No confundir «servidor responde» con «sistema terminado».

## Hitos con entregables, dependencias y puertas de calidad

| Fase | Tickets | Requiere | Frontend | Backend | Entrega verificable |
| --- | --- | --- | --- | --- | --- |
| 0 — Audit & arquitectura | T00–T02 | proyecto existente | auditoría de UI/tokens | auditoría DB/reglas | brechas, decisiones, mapa rutas/tablas |
| 1 — Base visual | T03–T06 | fase 0 | shell + componentes + modales + estados | estructura mínima/rutas | navegación real con datos vacíos |
| 2 — Catálogo e inventario | T07–T11 | fase 1 | catálogo/producto/categorías/stock | tablas + acciones + movimientos | alta/edición/consulta con tests |
| 3 — Proveedores y compras | T12–T15 | fase 2 | pedido/recepción/detalle | flete + valoración + transacciones | compra completa, luego parcial |
| 4 — Ventas | T16–T20 | fase 3 | nueva venta, historial, modal confirmación | stock debitado, snapshots, anulaciones | venta E2E + transacciones + tests |
| 5 — Datos y reportes | T21–T24 | fase 4 | dashboard, reportes, ajustes; T21 sujeto a nueva necesidad | exportación y reporting | métricas consistentes y exportables |
| 6 — Respaldos / Mac | T25–T29 | fases anteriores | backups, ayuda, pulido | snapshot, restauración, release | app usable en Mac offline, backup recuperable |

**Dependencia crítica:** no implementar «Ganancia histórica» en reportes sin primero guardar `sale_items.cost_total_snapshot_cents`. No implementar «Recepción» sin prorrateo en centavos resuelto.

## Backlog detallado, con criterio de finalización por ticket

### Fase 0 — Inspección

- [ ] **T00 — Auditar repositorio**: listar versiones/requisitos, qué hay implementado, `.env.example`, rutas, migraciones, tests, archivos de diseño disponibles. No borrar nada. **Aceptación:** informe con comandos, hallazgos y riesgos.
- [ ] **T01 — Contrato visual**: revisar `design_tokens.json`, HTML y SVG, registrar tabla componentes/rutas, diferencias Windows→Mac y Aura→Adara. **Aceptación:** documento de mapping comprobable.
- [ ] **T02 — Decisiones de dominio/ERD**: aplicar último costo de reposición R01 (no promedio ponderado), registrar reglas de recargo, redondeo, descuentos, parcialidad de recepción y anulación, y resolver los pendientes antes de cada módulo afectado. **Aceptación:** esquema de SKU y stock aceptado antes de migrar; reglas económicas restantes trazadas a sus tickets.

### Fase 1 — Frontend base

- [x] **T03 — Tokens y layout**: variables CSS, header, sidebar, logo ADARA, responsive notebook, fuentes offline. **Aceptación:** capturas verificadas a 1366×768 y 1440×900, guardadas fuera del repositorio; recursos de producción empaquetados localmente. Los elementos de navegación aún sin ruta se muestran inactivos hasta sus tickets.
- [x] **T04 — Componentes básicos**: botones, inputs/textarea/moneda, selector con lista estilizada, badges, alertas y estados loading/empty como componentes Blade. **Aceptación:** galería de estados/validaciones accesibles en `/dev/componentes` solo para entorno local y pruebas; ruta ausente en producción. El selector conserva un `<select>` real para enviar el valor y como alternativa si JavaScript no carga; la lista visual flota sobre el formulario sin desplazar el contenido. El campo ARS presenta el símbolo y el teclado decimal, sin convertir ni calcular importes en el navegador.
- [x] **T05 — Modales y navegación**: modal genérico, confirm dialog, toast y `wire:navigate` en las rutas existentes. **Aceptación:** galería local con apertura/cierre, foco y teclado; avisos de éxito/error; navegación Inicio ↔ Componentes sin listeners duplicados. Las rutas de pantallas comerciales pertenecen a T06.
- [x] **T06 — Shell de 17 pantallas**: Inicio y galería local existentes más 15 rutas nombradas para secciones y detalles. Listados con columnas, búsqueda y acciones inactivas; detalles con resumen e historial pendientes; otras pantallas con estado explícito «En preparación». **Aceptación:** enlaces y URL directas verificados, títulos/breadcrumbs/estado activo coherentes y ningún dato comercial ficticio. Las rutas de detalle aún son estructura visual: la consulta real y el 404 de ID inexistente corresponden a sus tickets de dominio.

### Fase 2 — Catálogo y stock

- [x] **T07 — Migraciones catálogo**: esquema de categorías, proveedores y clientes añadido al esquema existente de agrupadores, presentaciones SKU, saldos y movimientos; factories sintéticas para las entidades de catálogo. La categoría del agrupador es opcional y su eliminación se restringe cuando está en uso. **Aceptación:** migraciones y relaciones verificadas con SQLite `:memory:` en pruebas; la base local real no se migró. Los saldos y movimientos se crean mediante trigger/servicio transaccional, sin factories que inventen historial. La relación comercial entre proveedor y producto queda para T09, junto con los formularios.
- [x] **T08 — CRUD catálogo**: alta de agrupador y primera presentación, nuevas presentaciones, edición de nombres/códigos, búsqueda, filtro por estado, detalle con movimientos recientes, archivo/reactivación del SKU y borrado confirmado solo si no tiene stock ni historial. El código se genera por defecto o se ingresa manualmente; se normaliza a mayúsculas y conserva un índice `UNIQUE`. **Aceptación:** flujo Blade/Livewire → validación servidor → SQLite verificado con tests; la edición no cambia costo, precio ni movimientos. La migración aditiva de T07 se aplicó en la base local tras guardar una copia ignorada por Git. Categorías/proveedores/clientes se conectarán en T09; carga inicial con costo y mínimo quedan en T10. La política de inactivar un agrupador completo sigue pendiente y no se implementó.
- [x] **T09 — Categorías/proveedores/clientes**: listados buscables y modales para crear/editar categorías, proveedores y clientas; archivo/reactivación sin borrar vínculos. El alta y edición de producto permiten elegir una categoría y varios proveedores activos; los vínculos previos archivados se conservan. Una tabla intermedia con claves foráneas y par único registra proveedores por producto. **Aceptación:** flujos Livewire y SQLite aislada probados, y migración aditiva aplicada a la base local tras copia ignorada por Git. Compras y relación de clientas con ventas quedan en sus tickets posteriores.
- [x] **T10 — Inventario inicial/ajustes**: carga manual única de existencias con cantidad y costo real por presentación, mediante operación y movimiento auditables; ajustes de entrada/salida con motivo y clave idempotente; mínimo configurable por SKU y aviso al alcanzarlo. **Aceptación:** el saldo solo cambia con movimiento, la carga inicial duplicada no suma de nuevo y el precio queda pendiente hasta definir el recargo. Flujos Livewire y SQLite aislada verificados con pruebas.
- [x] **T11 — Stock UI**: pantalla de saldos por SKU con búsqueda, estado y disponibilidad; alerta al alcanzar el mínimo, historial paginado general o por presentación y modal de ajuste con motivo. **Aceptación:** un ajuste cambia el saldo exactamente una vez, rechaza stock negativo y conserva el costo vigente. Verificado con pruebas Livewire y SQLite aislada.

### Fase 3 — Compras

- [x] **T12 — Motor económico de compras**: `AllocateShippingService` distribuye envío por cantidad o costo pagado, con orden estable y sobrante en la última unidad elegible; `InventoryCostService` obtiene costo unitario interno de cuatro decimales. **Aceptación:** asignaciones en centavos suman exactamente el envío y las pruebas cubren ambos métodos, redondeos, entradas inválidas y desbordes. La persistencia de pedidos/recepciones corresponde a T13–T15.
- [x] **T13 — Modelo de pedidos**: tablas aditivas `purchase_orders` y `purchase_order_lines`, número único, proveedor, método y envío general, renglones en orden estable, estados borrador/confirmado e instantáneas comerciales al confirmar. `PurchaseOrderService` crea y edita borradores y confirma una vez, sin tocar stock, costo ni precio. **Aceptación:** flujo y restricciones verificados con SQLite aislada; las pantallas y recepciones siguen en T14–T15. La representación de sobreentregas y el remanente de envío tras cancelaciones permanecen pendientes.
- [x] **T14 — Recepción completa**: `ReceivePurchaseOrderAction` registra cantidades y costos reales de todas las líneas confirmadas, envío general y adicional aplicados, movimiento de stock y último costo/precio por SKU en una transacción. El recargo general inicial aprobado es 80 %, con prioridad del recargo particular. El cambio de costo requiere confirmación explícita. **Aceptación:** un reintento no duplica saldo ni flete y un fallo revierte todo. Parciales, sobreentregas, correcciones y pantallas quedan pendientes.
- [x] **T15 — Parciales y pantallas**: recepciones sucesivas con pendiente por línea y remanente del envío general asignado en la última; sobreentregas confirmadas mediante evento de ampliación que conserva el pedido original; cierre parcial con motivo y envío sobrante sin asignar, sin recalcular costos anteriores. Pedidos y recepciones tienen listados, detalle, formularios en modal y estados vacíos/carga/error. **Aceptación:** pruebas de flujo Livewire y SQLite aislada verifican cantidad, costo, envío e idempotencia. La corrección reversora de una recepción confirmada (R27) requiere un ticket posterior.

### Fase 4 — Ventas

- [x] **T16 — Venta Action y modelo**: ventas y renglones con precio, costo de cuatro decimales y descuento histórico congelados; número único, descuento general proporcional al precio de los renglones, cobros iniciales por medios combinables, comisión real ingresada y saldo fiado con clienta identificada. `CreateSaleAction` recalcula precio y stock del servidor y confirma una sola vez en transacción; advierte venta bajo costo y exige autorización explícita. **Aceptación:** pruebas de stock insuficiente, costo y total históricos, pagos, fiado, vuelto, reintento y reversión total ante falla. El carrito, la vista previa y la confirmación visual siguen en T17–T18. La sugerencia automática de comisión espera tasas aprobadas; las promociones automáticas requieren resolver vigencia y gestión en un ticket posterior, antes de habilitar ventas con promociones desde la UI.
- [x] **T17 — Carrito y nueva venta**: búsqueda por código o nombre, agregado y retiro de presentaciones, cantidades editables, clienta opcional y cobros combinables preparados desde la pantalla. La cotización reutiliza el cálculo del servidor de T16 y advierte falta de stock, descuentos inválidos, cobros superiores al total y fiado sin clienta; preparar el carrito no guarda ventas ni descuenta existencias. **Aceptación:** pruebas de carrito, importe actualizado, límite de stock y persistencia sin cambios. La confirmación definitiva queda en T18.
- [x] **T18 — Confirmación idempotente**: modal con renglones, descuentos, cobros, vuelto y saldo; validación de servidor antes de abrirlo y al guardar; aceptación expresa de venta bajo costo; botón bloqueado durante el guardado y respuesta con número de venta. La cotización revisada se compara con la vigente dentro de la transacción y una clave única del intento impide duplicados. **Aceptación:** pruebas de reintento, cambio de precio entre revisión y confirmación, vuelto, venta bajo costo y persistencia única. Los límites de crédito y promociones automáticas requieren sus configuraciones y reglas de vigencia en tickets posteriores.
- [x] **T19 — Historial, detalle y cobranza posterior**: listado paginado con búsqueda y período local de Argentina, cantidades, total de venta separado de cobros y saldo, margen bruto calculado con costo histórico y comprobante interno de solo lectura en modal. Desde una venta fiada con saldo se registra un cobro posterior: revisión de medio, importe, comisión real y vuelto; aplicación exclusiva a la venta elegida, sin tocar otras deudas de la clienta; confirmación idempotente y actualización del saldo sin cambiar la venta histórica. **Aceptación:** pruebas con SQLite aislada verifican costo/precio y nombres congelados tras cambios de catálogo, fiado, filtros, límite de mes local, cobro solo de la venta elegida, rechazo de sobrecobro y reintentos. La cobranza conjunta de varias ventas queda para un flujo/ticket aparte, por definir. La exportación corresponde a T22 y la anulación de ventas a T20. La anulación de cobros R26, la sugerencia automática de comisión R23 y el límite de crédito R58 requieren tickets propios con sus decisiones pendientes.
  - Mejora posterior: Clientas ofrece una vista de saldos pendientes por clienta y venta, con acceso al cobro de la venta elegida; Cobros muestra pagos reales por fecha argentina y medio, sin mezclar ventas ni reintegros.
- [x] **T20 — Anulación y reintegros efectivos**: motivo obligatorio, revisión de unidades aptas/no aptas por presentación, reversión auditada de stock y estado anulado conservando venta y cobros originales. El saldo impago se cancela y lo ya cobrado queda como obligación de reintegro pendiente. Desde el detalle se registran entregas reales parciales o completas, por cualquier medio, con fecha efectiva, historial y saldo actualizado. Las KPI de ventas vigentes excluyen anuladas. **Aceptación:** pruebas aisladas verifican doble confirmación sin duplicar stock o dinero, unidades no aptas sin reintegro de stock, deuda cancelada, límites del reintegro y flujos de ambos modales. Las recuperaciones de comisiones y cargos adicionales reales R54 siguen como eventos monetarios separados.

### Fase 5 — Datos, dashboard, configuración

- [ ] **T21 — Importación diferida**: el 21/10/2026 se decidió cargar catálogo y stock inicial manualmente desde cero, sin importar Excel. No implementar importador para el inicio; reabrir este ticket solo ante pedido posterior con fuente, validaciones y criterio de deduplicación definidos.
- [x] **T22 — Reportes económicos**: KPI por fecha efectiva de ventas, anulaciones, cobros, reintegros, comisiones y gastos; margen con costo histórico, saldos actuales diferenciados y exportación CSV protegida contra fórmulas. **Aceptación:** cifras reconciliadas con ventas y costos.
- [x] **T23 — Dashboard y gráficos**: Chart.js local, indicadores reales, alertas por mínimo de SKU, últimas ventas, accesos y filtros de período; sin datos ficticios. **Aceptación:** sin ventas ≠ números inventados.
- [x] **T24 — Configuración**: datos del negocio validados, recargo general y particular por presentación con vista previa de precios, confirmación idempotente e historial de cambios. La pantalla explica costo, precio, recargo, redondeo fijo a ARS 100 y mínimo de stock por SKU. **Aceptación:** pruebas aisladas verifican que cambiar el recargo general respeta overrides, que los precios vigentes se recalculan con aritmética exacta y que las ventas confirmadas conservan precio/costo históricos. El redondeo y la moneda no son editables por las reglas aprobadas; preferencias de respaldos y otras opciones visuales quedan para sus flujos propios.

### Fase 6 — Respaldo y despliegue Mac

- [ ] **T25 — Backups**: snapshot consistente, checksum, pruebas de restaurabilidad y registro. **Aceptación:** respaldo recuperable en base temporal.
- [ ] **T26 — Restauración segura**: preflight, copia preventiva, reemplazo/rollback. **Aceptación:** falla no destruye DB original.
- [ ] **T27 — Pantalla de respaldos y ayuda**: UX sencilla, avisos y pendrive. **Aceptación:** no requiere conocimiento técnico.
- [ ] **T28 — Lanzador macOS**: confirmar chip y versión, instalar PHP/Composer/deps aptas, Automator/launchd, ruta de datos, build offline. **Aceptación:** doble clic y abre, sin Terminal; no escucha fuera de loopback.
- [ ] **T29 — QA final**: restauración, apagón simulado, cambio de versión, smoke offline, release notes y manual breve. **Aceptación:** checklist de `04_CALIDAD_Y_PRUEBAS.md` completo.

## Formato de entrega obligatorio a Codex para CADA ticket

```markdown
## Ticket TXX — título
- Alcance autorizado (solo este ticket)
- Supuestos comprobados / decisiones pendientes
- Archivos modificados y por qué
- Cómo verificar a mano (pasos exactos)
- Pruebas ejecutadas + resultado real
- Riesgos conocidos / cambios fuera de alcance NO realizados
- Próxima tarea sugerida (sin ejecutarla)
```

## Política de dependencias y ramas

- Una funcionalidad terminada = commit(s) pequeños (si el flujo Git está habilitado); prohibido meter credenciales, `.env` o BD real.
- Mantener `composer.lock` y `package-lock.json` versionados; nuevas librerías requieren justificación y licencia compatible.
- No generar seeders con datos personales ni mezclar mockups de producción y pruebas. Usar factories sintéticas.
- Migrations posteriores al inicio de uso real en Mac requieren respaldo probado antes de ejecutar.
- Aplicar refactor general en ticket separado, luego de tests de regresión.

## Entregas sugeridas para revisión visual del desarrollador

1. **Luego de T03:** revisar sidebar, paleta, tamaños y comportamiento a 1366×768.
2. **Luego de T06:** recorrer pantallas y modales sin lógica real, corregir UX.
3. **Luego de T11:** cargar/editar producto y ajustar stock con datos de prueba.
4. **Luego de T15:** registrar pedido y recibirlo, comprobar cálculo del envío.
5. **Luego de T20:** simular venta y anulación, comprobar saldo/ganancia.
6. **Luego de T24:** reportes comparados con cálculos verificados.
7. **Luego de T29:** probar Mac real sin internet y restaurar un backup en copia de ensayo.
