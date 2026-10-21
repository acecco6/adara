# T02 — Plan técnico incremental para R01–R63

Fecha: 21/10/2026. Fuente vinculante: [REGLAS_ECONOMICAS_ADARA.md](REGLAS_ECONOMICAS_ADARA.md); diferencias y preguntas: [CONFLICTOS_Y_PENDIENTES.md](CONFLICTOS_Y_PENDIENTES.md). Este plan es una **propuesta de implementación**, no una migración aprobada ni una ampliación de las 63 reglas. Complementa `03_PLAN_DE_ENTREGAS.md`; antes de cambiar el orden de los tickets existentes hay que actualizar ese plan con aprobación explícita. T00/T01 son auditorías históricas, y T02 sigue abierto hasta aceptar el esquema y las decisiones que afecten el primer módulo.

## Punto de partida comprobado

- Starter Laravel 13.38.0 / PHP 8.4.13 / Livewire 4.4.7 / Tailwind 4.3.3 / Vite 8.3.4 / PHPUnit 12.5.40; SQLite predeterminada. Laravel Boost MCP responde a información de aplicación, conexiones, esquema, consulta de solo lectura y búsqueda de documentación; no expone `list_routes`.
- Ruta propia actual: solo `/` con vista `welcome`. Modelo: solo `User`. Tres migraciones iniciales registradas, nueve tablas de infraestructura; ningún dato comercial o componente Livewire de Adara observado.
- El diseño original tiene 17 pantallas y 20 modales, pero no cubre todas las operaciones ahora aprobadas. Las nuevas vistas deberán conservar sus tokens y lenguaje visual, con flujos adicionales diseñados antes de implementarse.
- `vite.config.js` configura Bunny Instrument Sans; resolver fuentes locales/fallback antes de declarar funcionamiento offline. No depender de `public/hot` en la Mac.

## Secuencia propuesta de incrementos revisables

| Orden | Entrega y reglas | Esquema candidato (solo propuesta) | Prueba de aceptación mínima y dependencia |
| --- | --- | --- | --- |
| I00 | Cerrar T02: aprobar ERD y términos de eventos; resolver solo pendientes que afecten el primer incremento. | Diagrama/contrato de grupos, SKU, movimientos y referencias; sin migración. | Revisión de reglas R01–R63 frente a la propuesta y ausencia de módulos descartados. |
| I01a | Fundamento monetario puro: importar cadenas ARS, centavos, costo unitario a 4 decimales, porcentajes decimales, techo comercial y asignación determinista R31. | Ninguna. Clases pequeñas bajo `app/` según convención elegida al comenzar. | PHPUnit: `14.250→14.300`, `15.000→15.000`, `1.000/3→333,33+333,33+333,34`, límites de rango, entrada inválida, suma exacta y sin `float`. No requiere SQLite real. |
| I01b | Registro transaccional de stock, saldo no negativo e idempotencia de ajustes R11/R14–R15/R42/R43/R48/R50. | `product_groups`, `product_variants`, `stock_movements` y saldo por SKU o cálculo con restricción transaccional; evaluar `operation_keys`/índice único. | SQLite de prueba: entrada/salida, intento de negativo, motivo obligatorio, repetición de clave, rollback y dos intentos sobre última unidad. Depende de ERD I00 e I01a. |
| I02 | Catálogo: agrupadores/presentaciones, código único, costo de reposición, recargos, precio calculado, historial, mínimo, activar/desactivar y borrar solo no usado R01/R03–R05/R32–R37/R42. | Variantes, categorías/proveedores si hacen falta para el flujo, `price_history`, configuración de recargo. | UI→validación→DB: SKU único, override, vista previa y confirmación masiva, precio no editable, inactivación solo stock 0, borrado solo sin referencias. Depende de I01a/I01b. |
| I03 | Compras y recepciones: pedido, entrega parcial, costo real, flete general/adicional, advertencias, cancelación de pendiente y corrección R02/R27–R30/R45–R47. | `purchase_orders`, líneas, recepciones, líneas recibidas, asignaciones de flete, cancelaciones y correcciones vinculadas. | Suma de flete exacta, recepciones 8+4 de 12, sobreentrega confirmada sin pendiente negativo, costo/precio solo al recibir, corrección segura. Resolver pendientes R30/R45 antes de esta migración. |
| I04 | Ventas, promoción/descuento global, pagos mixtos, fiados, comisiones, crédito opcional y vuelto R06–R08/R11–R13/R23–R25/R38–R41/R58–R61. | `sales`, líneas snapshot, promociones, cobros, asignaciones a ventas, cuentas por cobrar o saldos derivados y confirmaciones auditadas. | Venta atómica con stock y snapshots; una sola modalidad de descuento; fiado con clienta; cobro único distribuido por antigüedad; límites y avisos; ninguna doble confirmación. Resolver recargos R08 y vigencia R40 antes de activarlos. |
| I05 | Anulación, devoluciones, reintegros, cambios y contracargos R09–R10/R21–R22/R26/R51–R55. | Eventos reversores, devoluciones/líneas, obligaciones y pagos de reintegro, vínculos de cambio, anulaciones de cobros y contracargos. | Devolución no excede unidades; deuda baja antes de reintegro; fechas reales; cambio 18k→23k cobra 5k; sin doble stock ni dinero. Resolver imputaciones R22/R26/R55 primero. |
| I06 | Gastos, reintegros de proveedor, bajas y recuperaciones de deuda R18/R44/R56–R57. | Gastos, reintegros proveedor, bajas y recuperaciones vinculadas. | Ningún ingreso se disfraza de venta; baja 20k/recuperación 8k deja 12k no recuperados; no duplica gastos ni costo de envío. |
| I07 | Dashboard y reportes trazables R19–R21/R63, luego importación/exportación autorizada y respaldos. | Consultas agregadas e índices de fechas/referencias; tablas auxiliares solo si la medición demuestra necesidad. | Conciliar cada KPI con eventos base, períodos y reversos fechados; sin saldo bancario ficticio. Respaldar y restaurar copia aislada antes de entrega Mac. |
| I08 | Verificación offline y lanzamiento local macOS. | Sin nuevo dato comercial por defecto. | Build empaquetado, sin requests externos, teclado y responsive, apertura por acceso directo, respaldo/recuperación en Mac real. |

La numeración I00–I08 evita confundir este plan con los tickets T00–T29 existentes. **Siguiente cambio de código sugerido:** I01a, después de aceptar la ubicación/nombres de clases y los casos de borde monetarios; no iniciar I01b hasta aprobar el ERD de stock. Cada incremento exige revisión de código existente, tests aislados, validación en servidor cuando haya UI, documentación y verificación manual. Nunca usar `migrate:fresh` sobre la base local de la usuaria.

## Modelo de eventos y límites de responsabilidad

- `product_groups` agrupa; `product_variants`/SKU posee stock, costo de reposición con cuatro decimales, recargo y precio. El costo vigente cambia con recepción válida, no con ajuste gratuito. Las ventas guardan costo y precio de su fecha.
- `stock_movements` guarda delta, SKU, fecha efectiva, tipo, motivo, operación origen y referencia al reversor. Saldo no negativo se protege dentro de transacciones; índice/clave de operación evita repetir entradas o salidas. Una vista nunca decide el saldo final.
- Pedido y recepción son hechos distintos. El pedido no cambia stock/costo/precio. Cada recepción conserva cantidad y costo reales, distribución de flete y confirmación de diferencias; eventos posteriores corrigen sin sobrescribir snapshots históricos.
- Venta, cobro, asignación de cobro, devolución, obligación de reintegro, pago de reintegro, contracargo y baja incobrable son hechos distintos. No guardar «pagado» como sustituto del historial de cobros ni calcular recaudación sumando ventas.
- Reglas comerciales en acciones/servicios transaccionales; Livewire coordina formularios y estados, Blade presenta y Alpine maneja interacción efímera. `Route::livewire()` se verificó en documentación de Livewire 4, pero aún no se añadió ninguna ruta.
- SQLite: FK activas en la conexión Laravel; comprobar pragmas/timeout y ensayar contención con base de prueba antes de confirmar stock o dinero. `lockForUpdate()` por sí solo no demuestra exclusión en SQLite. Índices únicos y condiciones atómicas acompañan validación PHP.

## Estrategia de pruebas y diseño

- PHPUnit existente y `DB_DATABASE=:memory:` en `phpunit.xml`; usar factories y datos sintéticos. Unitaria para aritmética sin framework; feature para restricciones, transacciones, Livewire y reportes. Cubrir fallos intermedios, reintentos y fechas límite, además del camino feliz.
- Los casos E01–E35 del documento normativo son la matriz de regresión objetivo. Las pruebas Q01–Q28 de `04_CALIDAD_Y_PRUEBAS.md` se reinterpretan según R01–R63; Q06/Q13, que asumen promedio/cierre de valor promedio, no deben perpetuar el modelo rechazado.
- Antes de pantallas repetitivas, adaptar tokens existentes, shell y componentes. Diseñar flujos nuevos de cobros, devolución, promociones, gastos e incobrables con estados vacío/carga/error/éxito y foco/teclado. No copiar JS del prototipo ni instalar dependencias nuevas por adelantado.
- Para entorno offline, sustituir Bunny antes del build final, empaquetar fuentes/iconos/gráficos, validar `Network` sin internet y probar respaldo/restauración sobre copia en Mac real. Ningún test en Windows acredita por sí solo el lanzador Mac.

## Puerta para iniciar implementación

El contrato R31 de asignación del centavo residual a la última posición estable quedó aprobado el 21/10/2026. I01a puede especificarse sin decidir aún R30, R45 o R55. I01b requiere aprobar cómo se representa SKU/saldo/movimiento y cómo se mantiene el costo vigente cuando un stock llega a cero. El resto de los pendientes se resuelve antes del módulo afectado. El alcance de este documento termina en planificación: **no se crearon clases, migraciones, componentes ni tests**.
