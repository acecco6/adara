# 04 — Criterios de calidad y matriz de pruebas | Adara Gestión

## Puertas de calidad por cambio

- [ ] Código estilo PSR-12/Pint; funciones y variables explícitas; sin `dd()`, `dump()`, `console.log` de diagnóstico ni `TODO` oculto en operaciones críticas.
- [ ] Lógica validada servidor; no se confían totales/stock del navegador; excepciones traducidas a mensajes útiles sin stack traces visibles.
- [ ] Migrations y relaciones correctas (SQLite FK), pruebas con base **aislada** y datos sintéticos.
- [ ] Transacciones e idempotencia en movimientos; auditar side effects.
- [ ] `php artisan test` pasa; `npm run build` pasa (si la tarea afecta UI); Pint ejecutado si existe; rutas nuevas probadas con `route:list`.
- [ ] Navegación por teclado, foco, estado cargando/vacío/error/éxito, fecha y moneda `es-AR`.
- [ ] Sin requests a internet al navegar y guardar, con conexión desconectada (revisión Network y prueba real).
- [ ] La tarea fue comparada con referencia SVG/HTML si es visual.
- [ ] No modificación no aprobada de `composer.json`/`package.json`, `.env`, registros existentes o referencias del diseño.

## Matriz mínima de test automatizado + manual

| ID | Escenario | Resultado esperado | Tipo |
| --- | --- | --- | --- |
| Q01 | Crear 2 productos con mismo SKU (incluida variante en mayúscula/minúscula si aplica) | segundo rechazado con error entendible | Feature |
| Q02 | Producto con stock inicial | balance y movimiento inicial coinciden | Feature |
| Q03 | Intentar vender 4 unidades cuando hay 3 | venta no creada; stock/costo intactos | Feature |
| Q04 | Venta multítem, error de stock en segunda línea | se revierte toda la transacción | Feature |
| Q05 | Reenviar confirmación de venta con misma idempotency key | una venta, un débito por ítem | Feature |
| Q06 | Comprar mismo SKU en dos pedidos a distintos costos | valor de stock correcto; precio promedio mostrado coherente | Unit+Feature |
| Q07 | Flete $1,01 repartido entre 3 líneas | suma flete imputado == $1,01, sin doble imputación | Unit |
| Q08 | Recepción parcial 1/2, después 2/2 | saldos+flete exactos, estado final recibido | Feature |
| Q09 | Intentar recibir otra vez el mismo recibo | no sube stock ni costo dos veces | Feature |
| Q10 | Anular venta confirmada | stock reintegrado, movimiento inverso y estado anulado | Feature |
| Q11 | Anular por segunda vez | ninguna segunda reversión | Feature |
| Q12 | Cambiar costo promedio después de vender | ganancia histórica de venta previa **idéntica** | Feature |
| Q13 | Cerrar stock a 0 tras vender últimas unidades | valor de stock termina en 0 exacto | Unit+Feature |
| Q14 | Ventas anuladas en dashboard/reportes | no suman como ventas netas (según convención documentada) | Feature |
| Q15 | Importar archivo con productos `EJ-` y valores inválidos | preview/advertencias, sin importación silenciosa | Feature |
| Q16 | Importar dos veces mismo archivo | advertencia o política de upsert explícita, sin duplicados | Feature |
| Q17 | Export CSV con nombre `=2+2` | Excel no ejecuta fórmula | Unit |
| Q18 | Crear backup, inspeccionar integridad y restaurar en entorno de ensayo | productos, compras, ventas y saldos idénticos | Integration |
| Q19 | Intentar restaurar backup corrupto/incompatible | rechazo sin modificar la base original | Integration |
| Q20 | Sin internet al abrir y navegar | todos los assets cargan localmente y UI funciona | Manual Mac |
| Q21 | Mac: abrir dos veces el acceso directo | no levanta 2 servidores ni cambia puerto inesperadamente | Manual Mac |
| Q22 | Mac: abrir desde el Dock tras reinicio | abre con doble clic, sin Terminal visible | Manual Mac |
| Q23 | Modal: Tab / Shift+Tab / Esc / foco al cerrar | teclado y accesibilidad correctos | Manual UI |
| Q24 | Navegación Livewire entre reportes y ventas múltiples veces | no se duplican listeners ni instancias Chart.js | Manual UI |
| Q25 | Pantalla 1366×768 y zoom 125% | sin desbordamiento horizontal de la página ni acciones inaccesibles | Manual UI |
| Q26 | Cambio de mes, cambio horario local y rango vacío | totales por período correctos | Feature |
| Q27 | Cortar proceso durante transacción (entorno de pruebas) | ninguna venta/recepción parcial persistida | Integration |
| Q28 | Actualización posterior con migración y base real copiada | respaldo previo y datos intactos | Manual Mac |

## Fixture sintético base recomendado

- Categorías: Perfumes, Maquillaje, Skincare, Cremas.
- Productos: `TEST-LAB-001` con 3 unidades, `TEST-SER-002` con 0, `TEST-PER-003` con 10.
- Proveedor ficticio «Proveedor QA»; cliente opcional «Cliente QA».
- Pedidos: uno con envío a distribuir con resto de centavos y uno con 2 recepciones.
- Ventas: una con dos productos, una anulada, una con idempotency key reintentada.

**Prohibido usar los datos del Excel de la familia para pruebas automatizadas sin anonimización.** Los fixtures sintéticos no deben sembrarse en producción.

## Fórmulas e invariantes comprobables

```text
sum(purchase_order_items.allocated_shipping_cents) = purchase_orders.shipping_cents
sum(purchase_receipt_items.shipping_allocated_cents for order) <= shipping_cents
sum(shipping_allocated_cents when fully received) = shipping_cents
sale.total_cents = sum(sale_items.net_cents)  (sujeto a impuestos/fees definidos)
stock_balance = stock_initial + sum(stock_movements.quantity_delta)
stock_value_cents >= 0
quantity_on_hand >= 0
sale_item.cost_total_snapshot_cents >= 0
cost_history_before_purchase == cost_history_after_purchase
```

La implementación de descuentos/anulaciones puede requerir variantes de estas fórmulas: documentarlas explícitamente, no adaptar el test para tapar inconsistencia.

## Validación del lanzamiento en Mac real

- [ ] Modelo/chip/versión macOS identificados.
- [ ] PHP, extensiones y `vendor/` preparados para el **entorno Mac**; no reutilizar binarios Windows a ciegas.
- [ ] `npm run build` presente, recursos locales y sin HMR dev.
- [ ] URL en loopback; puerto controlado y diagnóstico de ocupado.
- [ ] Cierre/reinicio no pierde stock, pedidos, ventas ni configuraciones.
- [ ] Respaldo real a pendrive probado; restauración en copia separada probada.
- [ ] `APP_DEBUG=false`, errores amables; ninguna pantalla de debug expuesta.
- [ ] Actualización de versión no destruye datos ni sobrescribe backups.
- [ ] Guardar versión y pasos de instalación reproducibles sin licencia de Apple Developer.
