# T02 — Conflictos y decisiones pendientes para R01–R63

Fecha: 21/10/2026. Fuente normativa: [REGLAS_ECONOMICAS_ADARA.md](REGLAS_ECONOMICAS_ADARA.md). Este documento compara esas decisiones con el starter Laravel y con las propuestas previas de `01_ROADMAP_FRONTEND.md`, `02_ROADMAP_BACKEND.md`, `03_PLAN_DE_ENTREGAS.md`, `04_CALIDAD_Y_PRUEBAS.md`, `07_ESTADO_PROYECTO.md`, `08_INVENTARIO_VISUAL.md` y el prototipo original. **No hay datos comerciales que migrar hoy:** SQLite contiene solo tablas del starter. Las propuestas viejas no se convierten en reglas aprobadas por estar escritas en un roadmap.

## Conflictos que ya resuelven R01–R63

| Prioridad | Propuesta o diseño anterior | Regla aprobada y efecto para tickets futuros |
| --- | --- | --- |
| Bloqueante | `02_ROADMAP_BACKEND.md` B2/B3/B4, `03_PLAN_DE_ENTREGAS.md` T02 y `07_ESTADO_PROYECTO.md` proponen costo promedio ponderado y valor de inventario basado en promedio. | **R01:** último costo de reposición por SKU, aplicado a todas las unidades disponibles; snapshots de ventas inmutables. Los ejemplos y tests de promedio en `04_CALIDAD_Y_PRUEBAS.md` se deben sustituir cuando se implemente. La valorización por reposición es estimativa, no ganancia realizada. |
| Alta | B2 del roadmap propone precio sugerido editable o no actualizarlo al recibir; P03/M02 del diseño muestran precio y recargo como campos potencialmente editables. | **R03–R05, R32–R33:** la recepción confirmada recalcula costo/precio automáticamente; el catálogo tiene un único precio calculado, redondeado hacia arriba a ARS 100. Cambiar recargos sí exige vista previa y confirmación. El backend debe rechazar edición directa de precio. |
| Alta | Roadmaps e inventario antiguo tratan descuentos, pagos mixtos y fiados como opcionales o no definidos; la UI de venta del prototipo muestra un medio simple. | **R06–R08, R13, R23–R25:** descuento manual solo sobre el total, varios cobros reales y deuda de clienta identificada, con comisiones por cobro. El botón «Cobrar» asigna solo a la venta elegida; la cobranza conjunta de varias deudas queda pendiente de un flujo aparte. Venta y recaudación necesitan métricas distintas. |
| Alta | `02_ROADMAP_BACKEND.md` B3 sugiere repartir flete de pedido solo sobre cantidades previstas y bloquear toda recepción que exceda lo pendiente. | **R28–R30, R45:** recepciones parciales con costo/cantidad reales, envío general y adicional, más sobreentrega advertida y confirmada. Se requiere una representación del excedente que no haga negativo el pendiente. El remanente de flete tras cancelar pendientes sigue sin definición. |
| Alta | `02_ROADMAP_BACKEND.md` B4 y `07_ESTADO_PROYECTO.md` presentan anulación como inversión única de stock; M18 del prototipo solo descarta carrito. | **R09–R10, R21–R22, R51–R55:** distinguir descarte, anulación total, devolución parcial, estado vendible, reintegro real, cambio guiado y contracargo. Los eventos se reflejan en su fecha efectiva y preservan la venta original. |
| Alta | `02_ROADMAP_BACKEND.md` B1 sugiere archivar cualquier producto con historial y `08_INVENTARIO_VISUAL.md` propone no borrar literalmente M15. | **R34–R37:** desactivar SKU solo con stock cero; borrar definitivamente solo si nunca tuvo referencias operativas y stock cero. El catálogo distingue agrupador y presentaciones independientes con código único. |
| Media | P03/P14 y roadmaps mencionan 200 % de recargo y configuraciones de redondeo como posibles valores iniciales. | **R04–R05, R32:** recargo global y override por SKU; redondeo comercial fijo hacia arriba a ARS 100. El 200 % del prototipo es un ejemplo, no un default aprobado. |
| Media | `04_CALIDAD_Y_PRUEBAS.md` Q14 y reportes viejos podrían excluir anulaciones de la fecha de venta y rehacer totales históricos. | **R19–R21:** reportar el evento reversor en su fecha propia, con ventas, cobros, descuentos, comisiones, gastos e incobrables separados. Adaptar pruebas, sin reescribir septiembre por una devolución de octubre. |
| Media | El prototipo y los roadmaps no contemplan promociones, gastos simples, incobrables, cobros anulados, reintegros de proveedor ni cambios guiados. | **R18, R26, R38–R40, R44, R51, R56–R57:** incorporarlos en módulos posteriores. No deducir alcance funcional solo de las 17 pantallas SVG; hará falta diseño adicional. |
| Media | El documento nuevo propone como definición de fase `migrate:fresh + seed de prueba`, mientras `AGENTS.md` prohíbe comandos destructivos sin autorización. | Ejecutar pruebas con SQLite aislada y `RefreshDatabase` cuando se implemente. No ejecutar `migrate:fresh` ni seeders sobre la base local existente por esta frase de la propuesta. |
| Media | `.env.example` y el starter muestran Laravel/inglés; Vite configura Bunny Instrument Sans. | Contrato Adara `es-AR` y offline: adaptar nombre, locale y fuentes/assets empaquetados en los tickets de UI/despliegue. No cambiar `.env` real durante T02. |

## Criterio R31 ya confirmado

El 21/10/2026 el usuario eligió **«Última unidad según orden estable (coincide con el ejemplo)»** para asignar centavos sobrantes. Para formular el algoritmo, conservar el orden persistido de las líneas/unidades de la operación y asignar todo el remanente a la última posición elegible de ese orden; un empate no depende del orden de una consulta SQL. El caso de aceptación es ARS 1.000 entre tres unidades: 333,33 + 333,33 + 333,34. I01a ya implementa y prueba el reparto puro con enteros y suma exacta, incluido el caso de dos centavos residuales. Antes de integrarlo con flete, descuento general y otros repartos, cada operación deberá persistir y pasar su orden estable; la distribución por costo debe respetar los pesos elegidos en R02.

T12 aplica ese criterio al envío de compras sin expandir todas las unidades en memoria: calcula la cuota truncada de cada unidad y agrega todos los centavos restantes a la última unidad elegible del orden estable de líneas. Por cantidad, cada unidad pesa 1; por costo, pesa su costo neto unitario pagado en centavos. Las líneas sin costo pagado reciben cero en el segundo método. La suma por línea conserva exactamente el envío ingresado. El costo de reposición unitario se calcula con `(mercadería pagada + envío asignado) / cantidad recibida` y se redondea una sola vez a cuatro decimales, al más cercano con empate hacia arriba. T13–T15 deberán persistir el orden y las asignaciones efectivas; esta regla no resuelve el remanente de envío al cancelar unidades pendientes.

## Carga inicial confirmada

El 21/10/2026 el usuario indicó que cargará catálogo y stock **manualmente desde cero** y que no hace falta importar Excel. También confirmó que incorporará unidades físicas existentes indicando cantidad y costo real por presentación. T21 queda diferido. Cada SKU se crea con saldo cero; la incorporación real requiere un evento inicial auditable con ese costo, sin importar registros ni presuponer saldos monetarios. El formulario y el primer precio calculado dependen del ticket de catálogo/recargos e inventario inicial.

## Recargo general inicial confirmado

El 22/10/2026 el usuario aprobó **80 %** como recargo general inicial para calcular el precio en la primera recepción. T14 lo guarda en la configuración de precios y respeta el recargo particular de cada SKU cuando existe. Los cambios posteriores del recargo general o particular requieren la vista previa, confirmación e historial previstos en R32; T14 no implementa ese flujo.

## Cierre parcial y sobreentrega en T15

El 22/10/2026 el usuario eligió registrar el envío general remanente de unidades canceladas como **envío sin asignar**, sin cambiar los costos de recepciones anteriores. T15 registra ese importe en el evento de cierre, junto al motivo y las cantidades canceladas. En una compra cerrada, envío general asignado más envío sin asignar conservan el importe general original.

R45 ya aprueba recibir unidades excedentes con advertencia y confirmación, preservando el pedido original. T15 representa técnicamente ese excedente mediante un **evento de ampliación** vinculado a la línea original y a la recepción confirmada. La cantidad solicitada originalmente permanece intacta y el pendiente no se vuelve negativo. El evento no crea una nueva regla comercial.

## Descuento histórico en T16

El 22/10/2026 el usuario aprobó distribuir el descuento general **en proporción al precio de cada renglón**. T16 calcula el descuento desde el subtotal vigente en el servidor, asigna la cuota truncada a cada unidad en el orden persistido y entrega los centavos restantes desde la última unidad hacia atrás, sin descontar a ninguna más que su precio. Conserva la base, el tipo, el valor y el importe aplicado en la venta, además de la asignación y el costo histórico de cada renglón. La selección de unidades concretas para una devolución parcial se resolverá al implementar ese flujo; nunca se recalculará el descuento desde el catálogo actual.

## Pendientes que sí pueden cambiar resultados

| Prioridad | Reglas | Decisión pendiente y alternativas acotadas |
| --- | --- | --- |
| Alta | R06/R22/R52 | **Devolución parcial de unidades.** El prorrateo proporcional por precio quedó aprobado para T16. Falta decidir el orden de unidades devueltas cuando la última conserva centavos distintos, y cómo vincular beneficios diferentes de una misma presentación. No recalcular importes históricos. |
| Alta | R25/R26/R55 | **Anular/contracargar un cobro antiguo con cobros posteriores.** Definir orden de reconstrucción y qué hacer si una reasignación cambia ventas afectadas. Propuesta a evaluar: reconstrucción transaccional por fecha efectiva e ID, con auditoría de asignaciones originales y nuevas, sin tocar movimientos de dinero de cobros posteriores. |
| Alta | R08/R39 | **Recargos a clienta por medio.** Medios, tasas, base, orden respecto de descuentos y admisibilidad legal no están definidos. No aplicar recargo automático hasta que se aprueben. La comisión de procesamiento es un concepto distinto. |
| Alta | R22/R51/R53/R54 | **Compensación en cambio guiado.** Precisar orden entre reducción de deuda, crédito exclusivo del cambio, reintegro pendiente y comisión sobre dinero efectivo. No crear cobro/reintegro ficticio. |
| Media | R19/R21/R44/R54/R56–R57 | **Rótulos y fórmulas de reportes.** Separar ventas, cobros, margen de productos, comisiones netas, gastos e incobrables; acordar fórmula de «resultado estimado». No afirmar saldo disponible por cuenta. |
| Media | R34–R37 | **Grupo padre.** Elegir condiciones de inactivación/eliminación de agrupador considerando SKU activos, stock e historial. No borrar en cascada presentaciones usadas. |
| Media | R38/R40 | **Vigencia de promociones.** Propuesta: fechas locales `America/Argentina/Buenos_Aires`, inicio y fin inclusivos a nivel día; cualquier día compartido es solapamiento. Confirmar antes de guardar promociones. |
| Media | R55 | **Contracargo de venta sin clienta identificada.** R13 exige clienta para fiado, pero R55 reabre deuda tras un pago inicialmente completo. Definir identificación previa al cobro o tratamiento operativo del caso sin inventar una cuenta ficticia. |
| Media | R43/R44 | **Reintegro de proveedor.** Registrar entrada monetaria real sin submayor de deuda ni reajuste de costo; precisar etiquetas de reporte para que no parezca venta. |
| Baja | R61–R63 | **Ayuda al usuario.** Explicar que el exceso electrónico se gestiona fuera de Adara y que no hay conciliación ni saldos bancarios inferidos. |

## Alcance de la próxima aprobación

R01–R63 desplazan las propuestas incompatibles de T00/T01 y de los roadmaps. Para comenzar el primer incremento de código bastan el contrato monetario R31 ya aclarado, el esquema mínimo de SKU/stock y la autorización del ticket correspondiente. Los pendientes de compras, devoluciones y reportes se resuelven antes de implementar esos módulos; no bloquean por sí solos una biblioteca aritmética pura. No se creó ninguna regla R64.
