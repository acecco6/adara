ADARA GESTIÓN — Reglas económicas aprobadas (01–63) e instrucciones para Codex
Documento funcional y técnico de referencia. Estado: 63 decisiones aprobadas por el usuario. No existen reglas 64+ aprobadas en este documento.
Destino recomendado: docs/REGLAS_ECONOMICAS_ADARA.md en la raíz real del proyecto Laravel.
Última consolidación: 21/10/2026. Moneda: peso argentino (ARS). Idioma de la interfaz: español de Argentina.

0. Cómo debe interpretar este archivo Codex
1. Las secciones R01–R63 contienen decisiones de negocio aprobadas. Implementarlas sin sustituirlas por alternativas que parecieran «mejores».
2. Las secciones Especificación de implementación, Propuesta de entidades y Plan de trabajo son instrucciones/propuestas técnicas; no deben interpretarse como nuevas decisiones comerciales aprobadas cuando señalan algo pendiente.
3. Ante cualquier diferencia entre un prototipo visual, código preexistente, tickets anteriores o esta especificación, detectar y documentar el conflicto antes de cambiar reglas económicas. No borrar funcionalidades existentes sin evaluar datos y dependencias.
4. Las exclusiones son expresas: no implementar módulos rechazados «por si acaso».
5. Para cada historia de implementación: agregar migración si aplica, validación, lógica de dominio, operación transaccional, interfaz Livewire, pruebas automatizadas y actualización de documentación.
6. No crear supuestos sobre reglas monetarias pendientes: marcarlas con PENDIENTE_DEFINICIÓN y ofrecer alternativas concretas cuando impidan implementar con seguridad.
Contexto del producto y restricciones
- Adara Gestión es una aplicación de gestión para un pequeño negocio de cosmética/estética; la usa principalmente la madre del usuario.
- Uso completamente offline: funciona localmente sin internet, servicios SaaS, API bancaria, CDNs ni fuentes remotas obligatorias.
- Stack acordado: PHP 8.4, Laravel 13, Livewire 4, Blade, Alpine integrado, Tailwind CSS 4, SQLite, Vite, iconografía local (p. ej. Lucide) y Chart.js instalado/bundled localmente cuando corresponda. No agregar React o Vue.
- Desarrollo en Windows; ejecución local en una MacBook mediante un servidor PHP/Laravel en localhost y un lanzador macOS (Automator o script .command). No asumir un .bat en macOS ni exigir Apple Developer Program o NativePHP.
- Interfaz muy clara, cálida y fácil de operar; confirmar acciones económicas importantes. Los informes son operativos/comerciales, no un libro contable o sistema fiscal completo.
- Comprobar la estructura real del repositorio antes de agregar AGENTS.md, directorios o rutas. Mantener compatibilidad con Laravel Boost si está instalado; verificar las herramientas MCP disponibles en lugar de asumir comandos específicos.
1. Glosario e invariantes transversales
- Presentación (SKU): unidad comercial vendible con código propio, stock, costo de reposición, porcentaje de recargo y precio de venta. Puede pertenecer a un producto agrupador (R37).
- Costo de reposición vigente: costo unitario de la última recepción válida con costo, incluidos los gastos de envío asignados y los impuestos incorporados al precio pagado. No es costo promedio ponderado (R01).
- Precio vigente de catálogo: derivado del costo vigente y el recargo aplicable; redondeo hacia arriba a múltiplos de ARS 100. No se escribe manualmente (R03–R05, R33).
- Instantánea de venta (snapshot): importe y costo histórico de cada renglón, congelados al confirmar la venta; también se guarda el descuento efectivamente aplicado y las comisiones en sus operaciones reales.
- Venta / cobro / devolución / reintegro / contracargo / incobrable / recuperación: acontecimientos distintos, con fechas e impactos distintos. No sustituir unos por otros para cerrar saldos.
- Stock disponible: cantidad física vendible que puede comprometerse al confirmar. Sin reservas, sin backorders y nunca negativo.
- Importes de dinero: pesos con centavos (2 decimales); costos unitarios internos: 4 decimales. Distribuciones con conservación exacta de totales monetarios (R31).
- Trazabilidad: una operación confirmada no se «borra» si intervino en stock, dinero, deuda o historial; se revierte/compensa mediante operación vinculada cuando la regla lo permita.
- Integridad e idempotencia: no descontar dos veces stock, no cobrar dos veces una deuda, no devolver la misma unidad dos veces, no recuperar dos veces una comisión ni un incobrable.
2. Reglas aprobadas: texto normativo y pruebas de aceptación
R01 — Último costo de reposición, no costo promedio
Decisión. El costo vigente de cada SKU es el de su última recepción válida, incluyendo el envío asignado. Reemplaza el costo anterior para todas las unidades disponibles, incluso las más antiguas. Las ventas ya realizadas conservan su snapshot de costo y precio; su ganancia histórica no cambia.
Aceptar cuando: una recepción a costo diferente cambia el costo vigente de todo el SKU, no el de ventas anteriores; reportes distinguen margen histórico congelado de valorización estimada por reposición. No implementar promedio móvil ni FIFO para valoración de margen.
R02 — Método de prorrateo de envío elegible por compra
Decisión. En cada compra, elegir cómo distribuir el envío entre mercadería: por cantidad de unidades o proporcional al costo de la mercadería. Persistir el método elegido y la distribución aplicada. Compatible con entregas parciales (R28, R30).
Aceptar cuando: el costo total de envío asignado coincide exactamente con el ingreso real del envío, sin duplicados; compras con distintos métodos producen distribuciones distintas y auditables.
R03 — Actualización automática al recibir mercadería
Decisión. Al confirmar una recepción, no al crear el pedido, Adara actualiza automáticamente el último costo de reposición y el único precio vigente de venta de cada SKU afectado, sin pedir aprobación adicional.
Aceptar cuando: crear/editar un pedido sin recibir no cambia stock, costo ni precio; recibir efectivamente sí. Los ajustes de stock no deben confundirse con recepciones con costo (R44, R48, R50).
R04 — Porcentaje de recargo general y por SKU
Decisión. Existe un porcentaje global de recargo predeterminado y un porcentaje opcional por SKU que, si existe, prevalece sobre el global. Fórmula base: precio_sin_redondear = costo_reposicion × (1 + recargo_porcentaje / 100).
Aceptar cuando: cambiar el global no cambia el porcentaje particular; una presentación con recargo propio calcula con él y otra usa el general.
R05 — Redondear el precio comercial hacia arriba a $100
Decisión. El precio final automático se redondea hacia arriba al siguiente múltiplo de ARS 100, salvo que ya sea múltiplo exacto. Fórmula conceptual: precio_venta = CEIL(precio_sin_redondear / 100) × 100.
Aceptar cuando: $14.250 → $14.300; $15.000 → $15.000; nunca redondear hacia abajo. Usar aritmética decimal exacta (R31), no float.
R06 — Descuento manual solo sobre el total de venta
Decisión. Se permite un descuento general porcentual o de importe fijo, sin descuentos manuales por línea. No exceder el subtotal ni crear total negativo. El catálogo no cambia; el descuento sí disminuye ingreso real y margen de la venta.
Aceptar cuando: se conserva importe final aplicado y base de cálculo; reportes y devoluciones usan descuento histórico asignado a la venta, sin recalcular desde los precios actuales.
R07 — Medios de pago combinados
Decisión. Una venta admite varios cobros por efectivo, transferencia, Mercado Pago o tarjeta. Registrar cada medio, importe y fecha. En ventas completamente pagadas, sumar el importe requerido; en fiados R13 se permite un importe pagado inicial menor (incluso cero).
Aceptar cuando: venta por $20.000 puede recibir $5.000 efectivo y $15.000 transferencia; venta fiada puede cobrar $5.000 y dejar $15.000 pendientes. No permitir sobrecobros como saldos a favor (R59, R61).
R08 — Comisiones por medio y recargos al cliente opcionales
Decisión. Registrar comisiones/costos de procesamiento por medio de pago (reducen ganancia neta), y recargos a clienta cuando correspondan y sean legalmente admisibles. Son conceptos distintos; evitar duplicidad y no agregar un recargo automático no aprobado.
Aceptar cuando: informe distingue importe de venta, cobros, comisiones y eventuales recargos; no alterar precio de catálogo para simular comisión o recargo. Detalles de tasas y condiciones legales específicos quedan pendientes.
R09 — Anulación total de venta y devolución parcial de productos
Decisión. Permitir anular una venta completa y devolver parte de los artículos de una venta. Nunca borrar la operación histórica: guardar eventos reversores vinculados. No devolver cantidades superiores a lo vendido menos devoluciones anteriores.
Aceptar cuando: una segunda devolución de la misma unidad se rechaza y la venta original continúa consultable.
R10 — Stock de artículos devueltos según estado de venta
Decisión. Preguntar si la unidad devuelta está apta para volver a venderse. Solo si es apta se suma al stock disponible; si no, registrar motivo y unidad no vendible, sin incrementar stock disponible.
Aceptar cuando: 2 unidades devueltas, 1 apta y 1 dañada → solo +1 stock vendible.
R11 — Prohibir stock negativo
Decisión. Bloquear confirmación de ventas que excedan el stock disponible y validar también al añadir al carrito. La comprobación definitiva debe ser transaccional en servidor.
Aceptar cuando: dos ventas concurrentes no pueden confirmar la misma última unidad; devoluciones, ajustes y cambios también conservan stock ≥ 0.
R12 — Sin reservas ni pedidos pendientes de clientas
Decisión. No permitir reservas de unidades ni backorders de ventas. Stock disponible = stock físico vendible. No implementar estados «reservado» o «a entregar después» para clientas.
Aceptar cuando: un carrito sin confirmar no aparta stock y un producto agotado no puede venderse; los pedidos pendientes a proveedores (R28) son otra entidad.
R13 — Ventas fiadas y pagos parciales
Decisión. Permitir ventas total o parcialmente fiadas únicamente si se identifica una clienta. La venta descuenta stock al confirmarse aunque no se pague. Registrar deuda y cobros posteriores con fecha y medio; inicial puede ser $0; historial separado de ventas y caja.
Aceptar cuando: venta $30.000, cobro inicial $10.000 → saldo $20.000, stock descontado. No confundir «venta efectuada» con «dinero cobrado».
R14 — Ajustes manuales de stock
Decisión. Permitir ajustes de entrada/salida con cantidad, motivo obligatorio e historial. Prohibir resultado negativo. No se aprobó un módulo avanzado de inventario físico/arqueo.
Aceptar cuando: ajuste −3 desde stock 2 bloqueado; motivo vacío bloqueado; auditoría registra origen, fecha y cantidades.
R15 — Mermas sin valoración monetaria automática
Decisión. Pérdidas, roturas o vencimientos se registran como salidas con motivo; no calcular ni contabilizar automáticamente una pérdida económica en dinero y no alterar márgenes de ventas históricas.
Aceptar cuando: ajuste «dañado» reduce unidades pero no crea gasto de $X ni venta ficticia. No extrapolar valoración contable de mermas.
R16 — Sin seguimiento de fechas de vencimiento
Decisión. No registrar vencimientos por lote ni emitir alertas de caducidad. Los productos caducados se retiran manualmente mediante ajustes (R15).
Aceptar cuando: no aparecen formularios ni procesos automáticos de lotes/fechas de caducidad.
R17 — Compras sin cuentas por pagar a proveedores
Decisión. Registrar pedidos y recepciones, pero no gestionar pagos, pagos parciales, deuda ni cuenta corriente de proveedores. La recepción no depende de registrar un egreso bancario.
Aceptar cuando: una compra se recibe aunque no haya campo de «pagado»; no implementar supplier_payables ni circuito de vencimiento de proveedores.
R18 — Gastos del negocio simples
Decisión. Registrar concepto, categoría, importe, fecha y notas opcionales; incluirlos en reportes. No alteran stock. El envío ya incorporado al costo de mercadería no debe registrarse nuevamente como gasto operativo para evitar doble contabilización.
Aceptar cuando: gasto de alquiler reduce resultado operativo del período; compra/envío capitalizado no se resta una segunda vez como gasto simple.
R19 — Dashboard y reportes comerciales completos
Decisión. Incluir cuentas por cobrar, reintegros/devoluciones, descuentos, comisiones, productos más vendidos, margen por producto, ventas vs cobros, gastos y rentabilidad estimada. No asumir exportaciones, comparativas anuales ni BI avanzado no aprobados.
Aceptar cuando: métricas distinguen venta, recaudación y resultado; cada tarjeta puede rastrearse hasta movimientos base.
R20 — Reportes por fecha de cada operación
Decisión. Filtrar por fechas arbitrarias utilizando la fecha de cada operación; no exigir cierres de caja diarios/mensuales.
Aceptar cuando: informar período no modifica/transfiere operaciones a otro día; no existen cierres obligatorios.
R21 — Devoluciones y anulaciones afectan fecha efectiva
Decisión. Una devolución o anulación se refleja en la fecha real de su ejecución, no reescribe el importe original en la fecha de venta. Historial de venta inmutable.
Aceptar cuando: venta de septiembre y devolución de octubre → septiembre conserva venta histórica y octubre muestra ajuste negativo correspondiente.
R22 — Devolución reduce deuda antes de crear reintegro
Decisión. Valor de devolución se aplica primero a deuda pendiente de esa venta. Solo el excedente genera obligación de devolver dinero; permitir reintegros parciales, fechas/medios reales e historial. No duplicar créditos ni reversos.
Aceptar cuando: devolución $8.000 y deuda original $5.000 → deuda se reduce $5.000 y quedan $3.000 a reintegrar. No crear saldo a favor de compras futuras (R59).
R23 — Comisión sugerida, editable al cobrar
Decisión. Cada cobro real, incluidos cobros posteriores de fiados, calcula comisión sugerida por medio. Antes de guardar permitir editar porcentaje o importe efectivo. Guardar importe final, fecha, medio y parámetros/snapshot históricos; nunca aplicar más de una vez al mismo cobro.
Aceptar cuando: anticipo inexistente (R59), pago parcial de deuda de $10.000 con comisión distinta → gasto de comisión corresponde solo a ese cobro y fecha; editar tasas futuras no altera pasado.
R24 — Deudas sin vencimientos ni intereses
Decisión. Registrar saldos pendientes de clientas sin fecha obligatoria ni automática de vencimiento y sin intereses moratorios o recargos por antigüedad.
Aceptar cuando: deuda puede permanecer abierta indefinidamente; no hay proceso de mora automática.
R25 — Cobro único distribuido por antigüedad de deudas
Decisión. Un cobro puede aplicarse a varias ventas fiadas de la misma clienta, primero a las deudas más antiguas. Guardar asignaciones por venta. La comisión del cobro es única, no una por asignación.
Aceptar cuando: deudas $12.000 (anterior) y $18.000 (posterior), cobro $20.000 → $12.000 a primera y $8.000 a segunda; saldo $10.000. Definir desempate estable de misma fecha en código.
R26 — Anular cobros equivocados y registrar uno nuevo
Decisión. No sobreescribir un cobro incorrecto. Anular con motivo obligatorio e historial; revertir asignaciones y comisiones afectadas, después registrar el cobro correcto. Una anulación administrativa no es reintegro real de dinero.
Aceptar cuando: error $25.000 efectivo en vez de $20.000 transferencia → cobro anterior anulado, saldos/fees corregidos, nuevo cobro registrado; no doble contabilización. Validar encadenamiento de cobros posteriores.
R27 — Corrección controlada de recepciones confirmadas
Decisión. Permitir ajustes/reversiones vinculadas a recepción con motivo e historial; recalcular stock, costo y precio vigentes cuando corresponda, sin cambiar ventas históricas. Bloquear si produce stock negativo o si no puede resolver con seguridad movimientos posteriores.
Aceptar cuando: 12 cargadas vs 10 reales → corrección de −2 si hay stock suficiente; no alterar snapshot de ventas ya realizadas.
R28 — Recepciones parciales de pedidos
Decisión. Un pedido admite varias entregas. Cada recepción suma solo cantidades efectivamente recibidas y actualiza costos/precios en esa fecha. Mantener cantidades pendientes.
Aceptar cuando: pedido 12, recepción 8 → stock +8, pendiente 4; recepción 4 posterior → +4, pendiente 0.
R29 — Cancelar cantidades pendientes sin borrar lo recibido
Decisión. Permitir cerrar pedidos parcialmente recibidos cancelando unidades pendientes con motivo obligatorio. Mantener pedido original, recepciones y unidades realmente recibidas.
Aceptar cuando: pedido 12, 8 recibidas, 4 canceladas → estado «cerrado parcialmente», stock +8 total, historial original visible.
R30 — Envío general más envíos adicionales por recepción
Decisión. Registrar envío general por pedido, distribuirlo automáticamente entre las recepciones según método R02, y permitir envío adicional específico por entrega, sin duplicar importes. Un envío asignado integra costo de adquisición, no gasto operativo duplicado.
Aceptar cuando: envío general $12.000 entre 8+4 unidades y $4.000 adicional segunda entrega → asignaciones suman $16.000. Pendiente de definición: tratamiento del remanente del envío general si se cancelan unidades que nunca llegaron.
R31 — Centavos, 4 decimales de costo y distribución exacta
Decisión. Dinero con 2 decimales; costo unitario interno con 4; distribuir importes con diferencia de redondeo determinística y suma igual al total original. Mantener redondeo comercial a $100 (R05). Evitar float.
Aceptar cuando: repartir $1.000 entre 3 unidades → $333,33, $333,33, $333,34 (en orden determinista); suma $1.000 exactos. Los costos de 4 decimales no deben perderse prematuramente.
R32 — Cambiar recargos con vista previa y confirmación
Decisión. Al cambiar el recargo global o el particular, calcular precios nuevos, mostrar anterior/nuevo y aplicar una vez tras confirmación. Un cambio global no afecta presentaciones con recargo propio. Conservar historial de precios y snapshots de ventas.
Aceptar cuando: cambiar global de 50 % a 75 % en SKU costo $10.000: $15.000 → $17.500 tras confirmar; SKU con override permanece igual. Recepciones siguen recálculo automático R03.
R33 — Prohibir edición manual del precio de catálogo
Decisión. Ningún usuario podrá escribir directamente un nuevo precio de catálogo. Para alterarlo, cambiar el recargo aplicable (R32) o registrar recepción válida (R03). No implementar «precio protegido/manual».
Aceptar cuando: backend rechaza modificación directa del precio; interfaz no muestra editor libre de precio; descuentos de ventas no afectan catálogo.
R34 — Desactivar SKU solo cuando stock = 0
Decisión. Activación/desactivación manual. Solo permitir desactivar una presentación/producto vendible con stock cero; conservar historial. Stock cero no desactiva automáticamente. La presentación inactiva no aparece en ventas y puede reactivarse.
Aceptar cuando: SKU con 1 unidad no se desactiva; con 0 sí; reactivar no recrea ni pierde relaciones. Al tener agrupadores, evaluar sus presentaciones activas antes de desactivar grupo.
R35 — Borrar solo productos jamás utilizados
Decisión. Permitir eliminar definitivamente un producto creado por error si stock = 0 y no tiene ninguna referencia operativa (compras, pedidos, recepciones, ventas, devoluciones, ajustes, etc.), tras confirmación explícita. Todo producto con historial debe conservarse y solo puede desactivarse bajo R34.
Aceptar cuando: producto sin movimientos eliminado; uno con stock 0 pero venta histórica no puede eliminarse; comprobar relaciones también en servidor. Los grupos con presentaciones referenciadas no se borran en cascada.
R36 — Código único generado o manual
Decisión. Cada SKU requiere código único; generación automática por defecto y entrada manual alternativa. Rechazar duplicados. Los nombres pueden repetirse.
Aceptar cuando: dos SKU «Perfume Floral» con códigos diferentes son válidos; código repetido se rechaza incluso en solicitudes concurrentes (índice UNIQUE).
R37 — Productos agrupadores y presentaciones independientes
Decisión. Un producto principal agrupa presentaciones; cada SKU mantiene código, stock, costo, recargo y precio independientes. El agrupador no posee stock compartido.
Aceptar cuando: vender perfume 50 ml no resta stock del 100 ml; reportes pueden agrupar por familia y desglosar por presentación.
R38 — Promociones automáticas por fecha sobre el total
Decisión. Promociones con nombre, inicio, fin y descuento porcentual o fijo; se aplican automáticamente al total de ventas realizadas durante su vigencia. No cambian catálogo; conservar promoción e importe efectivo en cada venta.
Aceptar cuando: subtotal $40.000 bajo promo 10 % → venta $36.000; fuera de fechas no aplica; conservar snapshot aun si promoción se edita o termina.
R39 — No acumular promoción y descuento manual
Decisión. Promoción automática se aplica por defecto. Tu mamá puede reemplazarla por descuento manual mediante confirmación explícita; nunca sumarlas. Persistir origen/tipo del descuento aplicado.
Aceptar cuando: subtotal $40.000 promo 10 % ⇒ $36.000; cambiar con confirmación a descuento manual 15 % ⇒ $34.000, no $30.600.
R40 — Bloquear promociones con períodos solapados
Decisión. No permitir promociones vigentes/configuradas activas con rangos de fecha que se superpongan. Mostrar conflicto; exigir modificar fechas o desactivar una. No implementar prioridades ni mayor descuento automático.
Aceptar cuando: promoción activa del 10 al 15 y otra activa del 15 al 20 requieren resolver el día coincidente si intervalos son inclusivos (criterio técnico de inclusión documentado); promociones inactivas no afectan ventas.
R41 — Advertir por venta debajo del costo, no bloquear
Decisión. Comparar importe de productos tras descuento con costo total de reposición snapshot, antes de comisiones. Si es menor, mostrar pérdida estimada y exigir confirmación adicional; permitir vender. Conservar autorización.
Aceptar cuando: subtotal $40.000, descuento $12.000, costo $30.000 → aviso pérdida $2.000; tras confirmar venta válida con snapshot e historial.
R42 — Mínimo por presentación y alertas locales
Decisión. Stock mínimo configurable por SKU. Si disponible ≤ mínimo, avisar en inventario y dashboard; no incluir unidades de pedidos pendientes. No crear compras automáticas ni stock objetivo obligatorio.
Aceptar cuando: disponible 2 y mínimo 5 → aviso; disponible 5 con mínimo 5 → aviso; pedidos pendientes de proveedor no lo eliminan.
R43 — Devoluciones a proveedores como ajustes manuales
Decisión. No implementar módulo específico de devoluciones a proveedores. Reducir stock mediante ajuste manual con motivo «Devolución al proveedor» y trazabilidad (R14). No crear deuda/reintegro automático.
Aceptar cuando: −2 del stock con motivo, sin afectar ventas ni calcular automáticamente egreso/ganancia.
R44 — Reintegros simples de proveedor y reemplazos gratuitos
Decisión. Reintegros monetarios reales de proveedor: movimiento independiente de ventas y gastos, con fecha, importe, medio y observación; no alterar automáticamente margen ni costo de reposición. Reemplazos gratuitos ingresan por ajuste positivo con motivo, sin recalcular costo/precio a cero (ni tratar como nueva compra a $0).
Aceptar cuando: proveedor devuelve $16.000 → movimiento «reintegro proveedor», no venta; envío de 2 reemplazos → +2 stock, costo/precio iguales.
R45 — Diferencias reales de cantidad/costo al recibir
Decisión. Registrar cantidades y costos reales aunque difieran del pedido; advertir y pedir confirmación si cambió el precio o se excede la cantidad pendiente. Conservar datos originales; confirmar recepción dispara cambios de stock y costo. Mantener R27–R29.
Aceptar cuando: pedido 10×$8.000, llegada 8×$8.500 → aviso de cambio +$500/unidad; tras confirmar entran 8 y quedan 2 pendientes. Sobreentrega requiere confirmación específica; representación del excedente pendiente no puede tornarse negativa.
R46 — Precio de compra con impuestos incluidos
Decisión. Registrar precio final de compra con todos los impuestos incluidos, sin desglose de IVA ni gestión de créditos fiscales. Agregar distribución de envío una sola vez.
Aceptar cuando: factura $12.100 total → costo mercadería $12.100 antes de envío; no sumar nuevamente IVA.
R47 — Descuentos de proveedor integrados al precio neto
Decisión. Tu mamá ingresa directamente costo neto pagado, con descuentos ya incluidos. No hay campos ni historial específico de descuentos de proveedor. Luego se agrega envío correspondiente; no volver a descontar.
Aceptar cuando: precio lista $10.000 con descuento y costo neto ingresado $9.000 ⇒ base de costo $9.000, no $8.100 por descuento extra.
R48 — Bonificaciones comerciales como ajustes positivos
Decisión. Unidades de regalo comercial del proveedor ingresan por ajustes positivos de stock con motivo «Bonificación comercial del proveedor». No distribuir entre ellas el costo de unidades pagadas; no modificar costo/precio vigente y no generar compra a costo cero.
Aceptar cuando: compra 10 unidades a $10.000 y 2 gratis → 10 entran por recepción y 2 por ajuste; costo vigente $10.000 (más envío asignado según compra), no $8.333,33 ni $0.
R49 — Sin módulo de packs ni combos
Decisión. Productos se agregan individualmente al carrito; sin entidad de combo, precio de pack ni stock ficticio. Descuento solo sobre total de venta según R06/R38/R39.
Aceptar cuando: kit «crema + sérum» se vende como dos líneas normales, restando stock de cada SKU.
R50 — Regalos y muestras a clientas mediante ajustes
Decisión. Entrega gratuita como ajuste negativo de stock con motivo obligatorio «Regalo o muestra gratuita». No generar venta, deuda, ingreso, gasto monetario automático ni recálculo de costo.
Aceptar cuando: muestra entregada → −1 unidad, venta $0 inexistente; costeo histórico no alterado.
R51 — Cambios guiados entre productos
Decisión. Asistente de cambio vincula venta original, devolución y nueva venta; reduce deuda de venta original primero (R22), usa crédito de devolución solo para compensar el cambio y cobra/reintegra diferencia real. Stock vuelve solo si apto; conservar eventos y fechas sin duplicar dinero.
Aceptar cuando: cambio de producto pagado $18.000 por uno de $23.000 ⇒ diferencia a cobrar $5.000, no salida $18.000 + entrada ficticia $23.000. Cambio inverso genera reintegro pendiente del diferencial. No convertir diferencia en saldo general a favor R59.
R52 — Buscar venta para devoluciones y cambios
Decisión. No exigir comprobante físico: buscar venta por número, clienta, fecha, producto o importe y vincular obligatoriamente devolución/cambio a una venta existente. Validar unidades restituibles y precios/descuentos históricos. No devoluciones «sin venta identificada».
Aceptar cuando: clienta sin ticket encuentra operación; devolución excedente o de producto no vendido queda bloqueada.
R53 — Libre elección de medio al reintegrar
Decisión. Un reintegro real puede realizarse por cualquier medio habilitado, no tiene que coincidir con el medio original y no exige advertencia por diferencia. Guardar fecha, medio, importe y vínculo al origen; registrar salida solo cuando realmente se efectúa. Permitir parciales (R22).
Aceptar cuando: pago original por transferencia y devolución en efectivo $6.000 válida; pendiente de devolución no descuenta caja hasta efectuarse.
R54 — Reversión real de comisiones y cargos, no automática
Decisión. Conservar comisión original. Registrar separadamente recuperaciones efectivamente otorgadas por plataforma y cargos adicionales efectivos de un reintegro, con fecha/importe/operación. No revertir comisión proporcional automáticamente ni duplicar movimientos.
Aceptar cuando: comisión $1.000, plataforma devuelve $600 → costo neto comisión $400; si devuelve $0, no crear recuperación teórica.
R55 — Contracargos como movimientos independientes
Decisión. Contracargo total/parcial se vincula a cobro confirmado y registra dinero realmente retirado; reabre deuda correspondiente sin alterar venta ni stock. Pago rechazado antes de acreditarse no cuenta como cobro. Comisiones siguen R54.
Aceptar cuando: venta $20.000 pagada y contracargo de $20.000 ⇒ venta original sigue, stock igual, deuda $20.000 reabierta. Contracargo parcial $8.000 ⇒ solo $8.000 reabiertos, cuidando distribuciones del cobro original.
R56 — Bajas de deuda incobrable
Decisión. Dar de baja total o parcialmente una deuda activa con motivo y confirmación obligatorios. Disminuir saldo pendiente, sin registrar cobro ficticio, devolver stock ni alterar venta original. Mostrar baja como pérdida por incobrabilidad en reportes, evitando restarla también como gasto simple.
Aceptar cuando: venta $30.000, cobrado $10.000, deuda $20.000 dada de baja ⇒ pendiente $0, cobros efectivos $10.000, pérdida por incobrabilidad $20.000 en la fecha de baja.
R57 — Recuperación de una deuda incobrable
Decisión. Permitir recuperar total/parcialmente importes previamente dados de baja, vinculándolos a venta y baja original, sin reabrir automáticamente deuda. Registrar dinero efectivamente recibido, fecha, medio, comisión, recuperación en reportes de fecha real. Límite = baja aún no recuperada. No duplicar venta ni cambiar períodos históricos.
Aceptar cuando: baja $20.000 y pago posterior $8.000 ⇒ recuperación $8.000, saldo de baja aún no recuperado $12.000, deuda activa $0; reportes muestran recuperación aparte.
R58 — Límite de crédito opcional por clienta, con advertencia
Decisión. Límite configurable por clienta; antes de confirmar fiado, comparar deuda_activa_actual + saldo_financiado_nuevo con límite. Si excede, advertir y permitir continuar con confirmación explícita registrada. Venta totalmente pagada no sujeta a bloqueo. Bajas incobrables no cuentan; contracargos que reabren deuda sí.
Aceptar cuando: deuda $20.000, límite $30.000, nueva venta $15.000 y pago inicial $3.000 ⇒ deuda proyectada $32.000, aviso $2.000 de exceso, venta confirmable si autoriza.
R59 — Sin anticipos ni saldos a favor de clientas
Decisión. Todo cobro se aplica a venta/deuda existente. No recibir depósitos para compras futuras ni administrar crédito disponible general de la clienta. Los reintegros pendientes son obligaciones de devolución, no saldos aplicables a futuras compras (salvo compensación específica aprobada en cambio guiado R51).
Aceptar cuando: intento de registrar cobro sin venta/deuda aplicable rechazado. No construir billetera/saldo positivo de clienta.
R60 — Vuelto en efectivo calculado y confirmado
Decisión. Registrar efectivo entregado, calcular vuelto y confirmar su entrega. Ingreso neto de la venta es el importe debido, no todo el billete recibido. Compatible con efectivo dentro de pago combinado. Vuelto no es devolución de venta ni saldo a favor.
Aceptar cuando: importe efectivo $18.000, clienta entrega $20.000, vuelto $2.000; cobro neto registrado $18.000, detalle conserva entregado/vuelto.
R61 — Excedente de transferencia fuera del sistema
Decisión. En transferencias electrónicas superiores al importe debido, registrar únicamente la porción aplicada a ventas/deudas. Tu mamá resuelve y devuelve excedente fuera de Adara. No generar anticipos ni saldos a favor. Esta es una limitación consciente: los movimientos registrados no concilian el total del movimiento bancario real.
Aceptar cuando: venta $18.500, transferencia efectiva $22.000 ⇒ cobro Adara $18.500; los $3.500 excedentes no aparecen como operación interna ni ingreso. No pretender saldos bancarios exactos.
R62 — Sin transferencias internas ni conciliación de cuentas
Decisión. No registrar traslados entre efectivo, banco y Mercado Pago; nunca interpretarlos como ingresos/gastos. No crear módulo de transferencias propias ni conciliación bancaria. Registrar sí cobros, reintegros, comisiones, gastos y movimientos aprobados.
Aceptar cuando: depositar $30.000 de efectivo a banco no crea una venta ni una transacción de transferencia en Adara.
R63 — Sin saldos iniciales por medio de pago
Decisión. No registrar saldos de apertura de efectivo, banco o Mercado Pago. El sistema solo informa operaciones que se cargaron desde el inicio de uso; no calcular ni afirmar saldos actuales de esas cuentas.
Aceptar cuando: dashboard muestra ventas, cobros, devoluciones y gastos del período, pero no «Saldo bancario disponible» inferido ni saldos iniciales obligatorios.
3. Qué tiene que hacer Codex
3.1. Primera tarea: auditar el proyecto y planificar, antes de escribir código
1. Abrir la raíz de Laravel (donde están artisan, composer.json, app/, routes/, database/), no la carpeta contenedora.
2. Revisar AGENTS.md, composer.json, package.json, bootstrap/app.php, migraciones, modelos, rutas, componentes Livewire, pruebas y documentación presente. No asumir que el proyecto está vacío ni reemplazar pantallas ya funcionales a ciegas.
3. Comprobar versiones instaladas y herramientas existentes; si Laravel Boost está operativo, usar las consultas de contexto disponibles. No asumir que existe una herramienta list-routes; se puede ejecutar php artisan route:list.
4. Identificar diferencias entre código existente, UI/prototipo y R01–R63. Crear docs/CONFLICTOS_Y_PENDIENTES.md con severidad y propuesta sin tomar decisiones de negocio por cuenta propia.
5. Crear backlog incremental con prioridades, dependencias, migraciones y pruebas. Trabajar módulo a módulo y producir cambios revisables.
6. Verificar que todos los recursos necesarios para la app (JS, CSS, fuentes, íconos, gráficos) puedan servirse localmente. Sustituir dependencias a CDNs, Bunny Fonts u otros hosts externos para producción offline.
7. No introducir módulos de vencimientos de deudas, cuentas corrientes de proveedores, anticipos, packs, transferencia entre cuentas, apertura de caja, conciliación, vencimientos de productos ni edición libre del precio de catálogo.
3.2. Organización recomendada del dominio
No es un esquema cerrado; adaptar nombres al repositorio real y migrar datos existentes sin pérdida. Separar operaciones confirmadas de sus eventos de reversión. Posibles agregados:
Agregado	Entidades / tablas sugeridas	Responsabilidad
Catálogo	product_groups, product_variants, price_history, markup_settings	Grupo y SKU, código único, costo vigente, recargo y precio automático, baja/activación
Proveedores / compras	suppliers, purchase_orders, purchase_order_lines, purchase_receipts, purchase_receipt_lines, receipt_corrections, purchase_shipping_allocations, purchase_pending_cancellations	Pedido, recepción parcial, costos reales, envío, cancelaciones y correcciones
Stock	stock_movements (y stock consolidado por SKU, si conviene)	Entradas/salidas históricas, ajustes, bonificaciones, regalos, devoluciones y protección de no-negativos
Ventas	sales, sale_lines, sale_cancellations, sale_returns, sale_return_lines, sale_exchange_links	Venta con snapshots, descuento global, anulación, devolución y cambio guiado
Promociones	promotions, referencias/snapshots en sales	Vigencia sin superposición, aplicación automática o reemplazo confirmado
Clientas/deudas	customers, customer_credit_limits, sale_receivables, receivable_writeoffs, writeoff_recoveries	Fiados, saldos activos, incobrables, recuperaciones, límites opcionales
Dinero/cobros	payments, payment_allocations, payment_cancellations, chargebacks, refund_obligations, refund_disbursements, commission_adjustments, supplier_refunds, business_expenses	Cobros reales, métodos combinados, distribución automática FIFO cronológico, devoluciones y comisiones
Auditoría	operation_audit_logs / eventos del dominio	Quién/cuándo/por qué; valores originales y reversos, confirmaciones extraordinarias


Observaciones críticas:
- stock_movements debe identificar tipo, SKU, cantidad con signo, operación origen, ID origen, fecha, motivo, usuario, clave idempotente, y enlazar el evento reversor cuando exista. No permitir editar/borrar arbitrariamente movimientos confirmados.
- sale_lines debe conservar cantidad, precio unitario vigente en ese momento, costo unitario snapshot (4 decimales), descuento histórico asignado —si se necesita para devoluciones parciales— e importes redondeados. El modelo de descuentos sigue siendo general de venta, aunque internamente se distribuya para hacer devoluciones exactas.
- payments describe el efectivo realmente aplicado a ventas/deudas, medio, comisión definitiva y detalles de vuelto en efectivo. Una asignación a varias ventas no es otro cobro.
- refund_obligations son importes a devolver, no anticipos ni saldo a favor para futuras compras; la excepción estrictamente autorizada es la compensación atómica de un cambio guiado R51.
- supplier_refunds son recuperación de dinero de compras, separados de venta y gasto operativo. No confundir con «pagos a proveedores» (no aprobados).
- product_groups no tiene stock ni costo; esos datos están en cada product_variant/SKU.
- Priorizar referencias explícitas, integridad referencial y CHECK/índices UNIQUE donde SQLite lo permita, además de validaciones en servicios y tests.
3.3. Arquitectura Laravel/Livewire
- Controladores/acciones Livewire: validar entrada, consultar servicios, presentar resultados. No depositar reglas contables ni transacciones grandes dentro de componentes.
- Servicios/acciones de dominio sugeridos (nombres orientativos):
  - ReceivePurchase, CorrectReceipt, CancelPendingPurchaseQuantities, AllocateShipping.
  - RecalculateCatalogPrice, PreviewMarkupChange, ConfirmMarkupChange, AdjustStock.
  - ConfirmSale, ApplyPromotion, AssessBelowCostWarning.
  - RegisterPayment, AllocatePaymentOldestFirst, CancelPayment, RegisterChargeback.
  - ReturnSaleItems, CancelSale, ProcessExchange, RecordRefund.
  - WriteOffReceivable, RecoverWrittenOffReceivable, RegisterSupplierRefund, RecordCommissionRecovery.
- Servicios aritméticos independientes y testeables: Money, UnitCost, Percentage, Allocation, Rounding (o equivalentes coherentes con el proyecto).
- Operaciones con varios efectos: DB::transaction(), validación definitiva en servidor, control de reintentos/doble clic, secuencias de reversión con referencias estables y exactamente una contabilización de cada evento.
- En SQLite, establecer y verificar PRAGMA foreign_keys=ON en la conexión; comprobar el comportamiento real de bloqueo/transacciones, evitando depender de SELECT ... FOR UPDATE como si fuera MySQL/PostgreSQL. Pruebas de concurrencia/reintentos cuando haya escenarios simultáneos.
- Nunca confiar en totales calculados únicamente en JavaScript, formularios ocultos o Livewire. El backend recalcula cada importe a partir de datos aprobados y snapshots.
- Auditabilidad: guardar created_at, effective_at/occurred_at, reason, created_by si aplica, source_type/source_id o referencias equivalentes; toda confirmación extraordinaria (bajo costo, sobrelímite, cambio de precio proveedor, sobreentrega, sustitución de promo) queda registrada.
3.4. Representación monetaria y fórmulas
Reglas de negocio cerradas: centavos, costos unitarios con 4 decimales, conservación exacta de totales, recargo configurable y redondeo hacia arriba a $100. Decisiones puramente técnicas recomendadas, a verificar contra los datos existentes:
1. Evitar float en PHP/SQL. Se recomiendan enteros de centavos para importes ARS (ej.: $18.500,25 = 1850025) y enteros en diezmilésimos de peso para costo unitario (ej.: $8.453,2750 = 84532750), o cadenas decimales procesadas con BCMath. No confiar en la coerción NUMERIC de SQLite para operaciones críticas: puede introducir aritmética binaria.
2. Definir biblioteca única para porcentajes decimales precisos. Recargos configurados pueden tener decimales; persistir su valor/snapshot utilizado. Fórmula comercial obligatoria:
   recargo_efectivo = recargo_SKU_si_existe ?? recargo_global
   precio_crudo = costo_unitario_vigente * (1 + recargo_efectivo / 100)
   precio_catalogo_ARS = techo_estricto_a_multiplo_de_100(precio_crudo)
   No utilizar round(..., -2) (puede redondear hacia abajo). Tampoco usar «precio calculado sobre unidades antiguas» al cambiar costo: R01 establece costo de reposición vigente único por SKU.
3. Prorrateo monetario de envío o descuento histórico: calcular cuotas proporcionales usando precisión suficiente, asignar primero centavos enteros truncados, luego distribuir centavos residuales determinísticamente (por resto fraccionario mayor y clave estable, por ejemplo línea ID). Es una política técnica propuesta; documentar sus consecuencias y no alterar totales aprobados.
4. Repartir el descuento general entre líneas solamente para permitir devoluciones parciales y reportes históricos consistentes. No exponer ni habilitar descuentos manuales por producto (R06). La suma de asignaciones debe coincidir con el descuento global exacto.
5. No usar importe cobrado para inferir venta: venta_neta_operativa y cobros_efectivos son dimensiones separadas. Una venta fiada se reconoce como venta al confirmarla; su dinero entra cuando se cobra. Devoluciones, incobrables, recuperaciones, reintegros, comisiones y gastos conservan sus fechas propias.
6. Comisiones: por cobro real, sobre base que corresponda al medio y editables antes de confirmar (R23); reversos de cobros y recuperaciones reales nunca alteran silenciosamente la comisión original (R26, R54, R55). No cobrar comisión sobre dinero ficticio por compensación de cambio o vuelto.
7. Márgenes: al confirmar venta, guardar costo/precio/desc snapshots y resultado de productos; distinguir margen bruto de productos, comisiones, gastos, bajas por incobrables y recuperación de incobrables. Evitar restar devoluciones/gastos/comisiones dos veces. No presentar valuación de inventario como ganancia realizada.
8. Fechas de promoción: documentar convención de inicio/fin y zona horaria local antes de migrar; comprobar solapamiento en backend para toda alta/edición/activación (R40).
3.5. Flujos obligatorios que Codex debe implementar
A. Alta y mantenimiento de producto: agrupador → SKU con código único automático/manual → recargo (global u override) → precio automático → mínimo de stock. No precio manual, no borrado si usado, desactivación solo stock cero.
B. Compra y recepción: pedido sin efecto en inventario → recepción real total/parcial → diferencias de cantidad y costo advertidas → aplicar envío general/adicional sin duplicar → confirmar transacción → nuevo costo de reemplazo/precio → registrar pendiente. Cancelar pendientes con motivo. Correcciones posteriores por evento compensatorio, no reescritura de ventas pasadas.
C. Venta: seleccionar SKU y validar stock → promoción automática si aplica, o descuento manual que la reemplace con confirmación → calcular advertencia bajo costo → cobrar con uno o varios medios o dejar deuda con clienta → advertir si límite opcional excedido → confirmar transacción → congelar snapshots → descontar stock → crear deuda y cobros reales. No permitir sobrepagos aplicados ni reservas.
D. Cobranza: entrada real de dinero → aplicar cronológicamente a deudas activas más antiguas de la misma clienta → guardar distribución exacta y comisión sugerida/editada una sola vez. Error de carga ⇒ anulación motivada y nuevo cobro; contracargo real ⇒ movimiento distinto vinculado y reapertura de saldo.
E. Devolución/cambio: localizar venta original → calcular máximo devolvible, precio/descuento/costo histórico → indicar aptitud de artículo → crear reverso efectivo por fecha de devolución → reducir deuda primero → crear obligación de reintegro por excedente → registrar pago de reintegro realmente hecho. Para cambio guiado, nueva venta más compensación únicamente de importe disponible proveniente de esa devolución; no crear billetera global.
F. Incobrables: baja total/parcial con razón y confirmación → separar pérdida de dinero cobrado → recuperación posterior vinculada sin reabrir deuda ni generar segunda venta. No mezclar con deuda activa ni con ingreso comercial normal.
G. Ajustes y reintegros de proveedor: bonificación/obsequio/merma/devolución a proveedor se expresa como ajuste de stock, con motivo obligatorio; no cambia costo vigente ni crea ganancia/pérdida monetaria automática. Reintegro de proveedor es movimiento monetario independiente documentado.
H. Reportes: por fechas de operación y filtros; conservar snapshots históricos; separar ventas, cobros, devoluciones, pagos de reintegros, comisiones netas y recuperaciones, incobrables/recuperaciones, gastos, productos y rentabilidad estimada. Sin cierres obligatorios, saldos iniciales, saldos actuales de cuentas, conciliación ni traspasos internos.
3.6. Etapas de implementación sugeridas
Fase	Entrega verificable	Dependencia / control
00	Auditoría de proyecto, inventario de archivos y diferencias, pruebas de arranque offline	Leer este MD y AGENTS.md existentes
01	Tipos monetarios, servicios de redondeo y prorrateo, auditoría y stock transaccional	Tests de precisión + no negativos
02	Catálogo, presentaciones, códigos, recargos, precio automático, mínimos e inactivación	R01, R03–R05, R31–R37, R42
03	Pedidos, recepciones parciales, envíos, ajustes, bonificaciones y correcciones	R02, R14–R17, R27–R30, R43–R48, R50
04	Ventas, descuentos/promociones, pagos mixtos, fiados, comisiones y vuelto	R06–R08, R11–R13, R23–R25, R38–R41, R58–R61
05	Anulaciones, devoluciones, reintegros, cambios y contracargos	R09–R10, R21–R22, R26, R51–R55
06	Incobrables, recuperaciones, gastos y movimientos monetarios admitidos	R18, R44, R56–R57
07	Dashboard y reportes consistentes y comparados con eventos base	R19–R20, R63 y efectos fechados de todas las demás
08	Pruebas integrales, datos de ejemplo, UI responsive, empaquetado offline y guía macOS	Todas las anteriores


Definición de terminado por fase: migraciones reproducibles; migrate:fresh + seed de prueba; validaciones Livewire/servidor; test automático; transacciones con rollback; reporte de incidencias; funcionalidades de fases previas intactas; documentación y rutas reales actualizadas. No dar una fase por terminada solo porque la pantalla «se ve».
3.7. Matriz mínima de pruebas automatizadas
Usar Pest o PHPUnit según la configuración actual del proyecto. Preferir RefreshDatabase y SQLite de prueba, con datos que reflejen ARS, comisiones, líneas y fechas. Casos críticos:
ID	Escenario	Resultado esperado
E01	R01 recepción cambia costo después de venta anterior	Nuevo precio vigente; venta anterior conserva costo/precio/ganancia
E02	R02/R30 envío entre líneas y recepciones	Prorrateos suman al total exacto, envío adicional una sola vez
E03	R05/R31 precio de $14.250 y costo 4 decimales	$14.300; sin error float; distribución $1.000 = 333,33+333,33+333,34
E04	R04/R32 SKU con recargo propio y cambio del global	Vista previa correcta; SKU con override sin cambiar; historial íntegro
E05	R33 modificación directa de precio	Rechazo desde UI y backend
E06	R07/R13 venta fiada con dos medios y pago cero	Stock baja, deudas correctas, cobros solo por dinero recibido
E07	R11 confirmaciones simultáneas con una unidad disponible	Solo una venta confirma; stock nunca negativo
E08	R14/R15/R43/R48/R50 ajustes varios	Stock cambia; nunca se crean ventas/gastos monetarios no aprobados
E09	R22 devolución $8.000 con deuda $5.000	Deuda −$5.000 y obligación de reintegro $3.000, sin billetera
E10	R23/R25 cobro $20.000 aplicado a deudas 12k/18k	Asignaciones 12k+8k; comisión única, base del cobro real
E11	R26 anular cobro anterior con cobros posteriores	Reasignaciones/saldos consistentes; no doble dinero ni comisión
E12	R27 corrección de recepción que excede stock actual	Bloqueo explicativo, sin cambios parciales
E13	R28/R29 parcial y cancelación de pendiente	Stock solo recibido; pedido cerrado parcialmente
E14	R38/R39 venta con promo y descuento manual	Se aplica solo una modalidad, sustitución requiere confirmación
E15	R40 promociones con períodos superpuestos	Alta/edición/activación de la segunda promo bloqueada
E16	R41 venta por debajo del costo	Advertencia, consentimiento registrado y venta posible
E17	R42 stock exactamente en el mínimo	Alerta local visible, sin pedido automático
E18	R45 precio cambiado y sobreentrega	Advertencia y confirmación; stock y costo solo al confirmar
E19	R51 cambio guiado de 18k a 23k	Cobrar solo 5k; snapshots, devolución y venta vinculadas
E20	R52 segunda devolución de unidad ya devuelta	Rechazo; no doble reintegro ni devolución de stock
E21	R53 reintegro en efectivo de pago por transferencia	Permitido; salida registrada solo cuando realmente se entrega
E22	R54 comisión 1k y recuperación real 600	Costo comisión neto 400 sin alterar pago original
E23	R55 contracargo parcial	Se reabre solamente importe retirado, sin restituir stock
E24	R56/R57 baja 20k y recuperación posterior 8k	Baja restante 12k; recuperación fechada, sin nueva venta
E25	R58 crédito supera 2k el límite	Advertencia, override confirmado/auditado, venta válida
E26	R59 cobro sin venta existente	Rechazado; no saldo a favor
E27	R60 efectivo entregado 20k por cobro 18k	Vuelto 2k confirmado; ingreso neto 18k
E28	R61 transferencia excedente real	Solo importe aplicado en Adara; no se crea anticipo
E29	R62/R63 revisión de funcionalidades ausentes	Sin traspasos internos, saldos iniciales ni saldos bancarios ficticios
E30	R34–R37 inactivar/eliminar SKU con historial	Inactivar solo stock 0; borrar solo sin referencias; códigos únicos
E31	R18–R21 gastos y devoluciones en distinta fecha	Reportes no trasladan hechos a fecha anterior ni duplican envío
E32	Reintento de confirmación por doble clic	Una sola venta/cobro/recepción/reintegro, integridad intacta
E33	R44 proveedor reintegra dinero / reemplaza gratis	Ingreso distinto de venta; reemplazo +stock sin cambiar costo
E34	Pago combinado y comisión sobre medios	Suma cobros no excede aplicable; fees y medios reales separados
E35	Reiniciar app sin conexión a internet	UI, estilos, gráficos y operaciones funcionan; sin descargas CDN


Para cada cálculo económico agregar pruebas con fronteras: 0,01; múltiplos exactos de 100; importes negativos rechazados; descuentos del 100 %; devoluciones fraccionadas; cantidades exactas; cobranza repartida; anulación doble; porcentajes decimales; fechas al límite de promociones; SKU inactivo; compras posteriores entre recepción y corrección.
3.8. Requisitos de interfaz y seguridad operativa
- Confirmaciones explícitas para: recepción con cambio de precio/sobreentrega, recargo masivo, venta bajo costo, crédito sobre límite, reemplazo de promoción manual, baja de deuda, devolución y modificación de cobro, corrección de recepción.
- Buscar ventas/devos por número, clienta, fecha, SKU, importe; mostrar cuánto queda por devolver.
- Mostrar costos históricos vs vigentes, importes cobrados vs facturados, reintegros pendientes vs efectuados, bajas incobrables vs recuperadas.
- Mostrar advertencias con lenguaje claro (ej.: «Faltan 2 unidades en stock»), no excepciones SQL al usuario.
- Validar permisos/identidad de usuario según el diseño real del proyecto; no inventar roles complejos si no existen.
- Mantener formatos es-AR, $ con separadores locales y fechas comprensibles; guardar importes numéricos canónicos internamente.
- Accesibilidad básica: etiquetas, foco visible, errores por campo, botón deshabilitado mientras guarda sin confiar solo en ello.
- Operaciones irreversibles: no eliminar registros operativos; mostrar quién/por qué/cuándo y referencia relacionada.
4. Decisiones pendientes, limitaciones conscientes y puntos de cuidado
Estos puntos no son reglas nuevas aprobadas. Codex debe documentar alternativas y preguntar solo si realmente bloquean la implementación; puede proponer soluciones técnicas acotadas sin alterar los acuerdos:
1. Envío de pedidos parcialmente cancelados (R30): qué hacer con parte del envío general presupuestado/cobrado no asignada a unidades nunca recibidas. No redistribuir silenciosamente todo sobre unidades anteriores vendidas ni perder ese costo. Preparar escenarios y propuesta de regularización auditable.
2. Criterio preciso de redondeo y empates (R31): está aprobado el total exacto y asignación determinista, pero no el algoritmo específico ni momento exacto de redondear porcentajes. Documentar una política técnica única con casos de prueba; no cambiar totales monetarios sin aprobación.
3. Sobreentrega superior al pedido (R45): se permite con advertencia y confirmación, pero debe especificarse cómo queda formalizada la ampliación/corrección de la cantidad autorizada y cómo impedir cantidades pendientes negativas, preservando lo solicitado originalmente.
4. Reparto de descuentos históricos entre renglones (R06/R22/R52): es indispensable para devoluciones parciales; proponer distribución interna de importes exactos, sin habilitar descuentos manuales por línea.
5. Cobros posteriores, reversiones y reasignación (R25/R26/R55): definir desempate de ventas del mismo día y reconstrucción segura de asignaciones si se anula o contracarga un cobro anterior y hay cobros posteriores. Evitar pagos «huérfanos» o deuda negativa.
6. Resultado económico y clasificación (R15/R18/R19/R21/R44/R54/R56/R57): el producto excluye valoración monetaria automática de mermas, pero incluye estimaciones de beneficio, gastos, bajas/recoveries. Declarar fórmulas y rótulos para que usuario no confunda «ingresos», «recaudación», «margen comercial», «resultado estimado» y «dinero disponible»; evitar ajustes dobles.
7. Recargos al cliente por medios de pago (R08): concretar medios habilitados, porcentajes, base imponible y restricciones legales antes de implementar cobros automáticos de recargo; nunca asumir legalidad por defecto.
8. Reintegro parcial, compensaciones y comisiones de cambios (R22/R51/R53/R54): precisar orden de imputación del crédito de devolución, y cuándo se confirma dinero efectivamente entregado; nunca inventar entrada/salida ficticia por compensación interna aprobada de cambio guiado.
9. Productos agrupadores (R34/R35/R37): definir política de inactivar/eliminar grupo padre en función de sus SKU, siempre protegiendo historial y stock de cada presentación.
10. Devoluciones a proveedores / reintegros (R43/R44): el usuario descartó seguimiento de devolución a proveedor; permitir reintegros simples sin inventar un subledger de cuenta corriente de proveedor ni deducir automáticamente un costo de adquisición histórico.
11. Cobro efectivo vs transferencia excedente (R61): el usuario eligió deliberadamente no registrar el excedente bancario. No mostrar conciliación, saldo por cuenta ni métricas de movimiento bancario completo; informar en pantalla de ayuda que el sistema refleja solo lo aplicado.
12. Promociones y fechas (R38/R40): precisar rangos inclusivos, zona horaria y comportamiento en edición/activación; bloquear concurrencias de promociones activas de forma persistente.
13. Múltiples descuentos y cargos en venta (R06/R08/R39): no acumular promoción y descuento manual. Surcharges aprobados solo en principio, y pueden alterar total pagado: definir orden y control antes de usarlo en producción.
14. Granularidad de stock (R10/R14/R15): se aprobó solo stock vendible; no implementar sistema de lotes, seriales ni almacén separado de no vendibles a menos que ya exista o sea imprescindible para historial.
15. Sistema offline/macOS: no se conocen todavía versión exacta de macOS, arquitectura de procesador ni detalles del empaquetado; documentar requisitos, verificar en equipo real y no declarar instalador probado sin probarlo.
Decisiones negativas definitivas (no volver a proponerlas en esta entrega)
- No promedio ponderado de costos (R01).
- No descuentos manuales por artículo (R06).
- No stock negativo, reserva ni venta backorder (R11–R12).
- No vencimientos de productos ni de deudas / intereses (R16, R24).
- No pagos/deudas de proveedores (R17).
- No cierres diarios obligatorios (R20).
- No precios de catálogo manuales/protegidos (R33).
- No eliminar productos con historial; no desactivar con stock > 0 (R34–R35).
- No promociones concurrentes en fechas (R40).
- No seguimiento de devoluciones a proveedores (R43).
- No impuestos discriminados/créditos fiscales (R46).
- No desglose de descuentos de proveedores (R47).
- No packs/combos (R49).
- No cartera de anticipos/saldos a favor (R59).
- No control interno de excesos electrónicos (R61).
- No transferencias propias, conciliaciones ni saldos iniciales / saldo actual por cuenta (R62–R63).
5. Instrucción copiable para Codex
Estás trabajando en ADARA GESTIÓN, aplicación Laravel 13 / PHP 8.4 / Livewire 4 / Blade / Tailwind 4 / SQLite, totalmente offline, desarrollada en Windows y ejecutada localmente en macOS.

Usá docs/REGLAS_ECONOMICAS_ADARA.md como especificación vinculante de las 63 reglas de negocio aprobadas. Antes de modificar código:
1) leé AGENTS.md, docs existentes, composer.json, package.json, migraciones, modelos, rutas, componentes Livewire y tests;
2) verificá qué partes ya existen; detectá diferencias y documentá riesgos / decisiones todavía pendientes, sin inventar reglas;
3) presentá un roadmap técnico por módulos y empezá por fundamentos monetarios/stock con pruebas;
4) implementá incrementalmente servicios de dominio transaccionales, migraciones, UI y tests;
5) conservá operaciones históricas mediante eventos de reversión, snapshots inmutables y referencias auditables;
6) no uses floats para dinero, ni permitas stock negativo, dobles comisiones, ventas sin cobrar confundidas con ingresos, o sobrescritura de ventas pasadas;
7) respetá TODAS las funcionalidades descartadas expresamente en el MD;
8) no agregues dependencias externas necesarias en tiempo de ejecución: la app debe funcionar offline en localhost.

Por cada incremento entregá: archivos modificados, explicación de decisiones técnicas, migraciones, tests ejecutados y su resultado, posibles conflictos pendientes y procedimiento para verificarlo manualmente.

No declares terminadas tareas que no probaste ni asumás que existe una herramienta de Laravel Boost sin verificarlo. NO implementes una regla 64 ni nuevas políticas de negocio sin autorización.
6. Instrucciones para instalar este documento en el repositorio
1. Guardar este archivo en la raíz Laravel como docs/REGLAS_ECONOMICAS_ADARA.md (crear docs/ si no existe).
2. En el AGENTS.md que ya usa Codex, añadir un enlace y prioridad, sin duplicar ni borrar sus otras instrucciones:
   ## Reglas de negocio de Adara
   Las reglas económicas aprobadas están en `docs/REGLAS_ECONOMICAS_ADARA.md`.
   Antes de implementar ventas, stock, compras, precios, cobros, devoluciones,
   promociones, deudas o reportes, leer ese archivo completo. No introducir
   funcionalidades descartadas ni decidir reglas pendientes sin autorización.
   Cada cambio necesita pruebas automatizadas y preservar el historial.
3. Abrir Codex en la carpeta raíz del proyecto Laravel; confirmar que ve AGENTS.md y este MD.
4. Pegar la Instrucción copiable para Codex de la sección 5 y pedir que empiece por auditoría/roadmap y etapa 01, no que reescriba toda la app de una sola vez.
5. Revisar docs/CONFLICTOS_Y_PENDIENTES.md cuando Codex lo genere antes de habilitar una lógica monetaria cuyo resultado no esté completamente definido.
6. Conservar una copia/versionado Git del documento y usarlo como referencia cuando una prueba o decisión de UX entre en conflicto con las reglas.
Fin de la especificación. Reglas aprobadas: R01–R63.