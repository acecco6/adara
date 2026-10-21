# 01 — Roadmap FRONTEND | Adara Gestión

> Stack: Laravel 13 + Livewire 4 (componentes PHP/Blade) + Tailwind CSS 4 + Alpine integrado + Vite. Referencias visuales en `../prototipo.html`, `../design_tokens.json`, `../svgs/` y `../LEEME_IMPORTAR_EN_FIGMA.md` (rutas desde la raíz Laravel). **No sustituirlos por plantillas genéricas.**

## Objetivo de experiencia

Que una persona no técnica pueda encontrar productos, registrar una venta en pocos pasos, comprar y recibir stock sin errores, visualizar ganancias y generar una copia, en una MacBook offline. Interfaz en español argentino, estética cálida y femenina sobria del diseño existente, usable a 1366×768 sin zoom ni scroll horizontal de página. La prioridad es **claridad y velocidad**, no animaciones vistosas.

## Decisiones del frontend

- Páginas completas Livewire 4 mediante `Route::livewire()`; adoptar UNA sola convención de componentes (preferir clase PHP + vista Blade) y respetar la estructura existente si ya hay código.
- El estado del negocio vive en PHP/servidor. Alpine maneja únicamente detalles transitorios: abrir/cerrar modal, dropdowns, focus, drawer, atajos. No mantener total de venta independiente del backend.
- Blade components parametrizados y slots para UI común; Tailwind v4 para estilos y tokens CSS semánticos en `resources/css/app.css`/`@theme` según configuración real. Evitar `@apply` masivo y colores hex duplicados.
- Iconos Lucide SVG empaquetados localmente; decidir una única forma de uso (render estático / sprite / helper) para evitar repetir 100 iconos en JS. Chart.js empaquetado mediante Vite solamente en páginas que lo necesiten.
- No instalar bibliotecas de componentes que reemplacen el diseño, salvo que se apruebe explícitamente. Las librerías se agregan después de verificar lockfiles y el bundle.
- `wire:navigate` para navegación cuando sea compatible; aplicar limpieza de inicializaciones JS y manejo del historial. No duplicar listeners al navegar.
- Fuentes locales/fallback del sistema; ningún `@import` desde URLs, CDN o fuentes externas.

## F0 — Auditoría visual y mapa de componentes (sin implementación todavía)

- [ ] Inspeccionar estructura real del repo (`resources/views`, `resources/css`, `resources/js`, routes, componentes ya generados).
- [ ] Abrir `../prototipo.html`; comparar layout, menú, modales y tipografías con los SVG 01–17 / M01–M20.
- [ ] Extraer y **mantener** design tokens a CSS variables (`--adara-*`) con nombres semánticos.
- [ ] Inventariar piezas comunes y pantallas; separar *demo visual* de datos reales.
- [ ] Acordar rutas, títulos en español, etiquetas de menú, breadcrumbs, sidebar y criterios de navegación.
- [ ] Crear snapshots de referencia para dashboard, catálogo, nueva venta y modal de producto a 1366×768/1440×900; comparar manualmente con las fuentes.

**Entrega F0:** documento de auditoría con brechas y lista de componentes; no modificar implementación comercial.

## F1 — Design system y shell

Componentes base:

| Componente | Estados obligatorios | Recomendación |
| --- | --- | --- |
| `AdaraButton` | primary, secondary, outline, ghost, danger; hover, focus, disabled, loading | evitar doble click en submit |
| `AdaraInput` / `Textarea` | vacío, con valor, foco, invalid, disabled | label, helper text y errores |
| `CurrencyInput` | valor ARS, formato presentación, validación | normalizar centavos al servidor; no float |
| `QuantityInput` | min/max, teclado, incrementos | feedback stock disponible |
| `Select` / `Combobox` | abierto/cerrado, sin resultados | búsqueda solo cuando aporta valor |
| `DataTable` | skeleton, empty, loading, error, pagination | cabeceras, acciones, filtros en servidor |
| `StatusBadge` | success, warning, neutral, danger | texto además del color |
| `KpiCard` | valor, descripción, tendencia opcional | nunca inventar estadísticas |
| `AdaraModal` / `ConfirmDialog` | open, close, blocked, error | focus trap, Escape, overlay |
| `Toast` / `Alert` | success, info, warning, error | `aria-live`; persistir errores relevantes |
| `EmptyState`, `Skeleton`, `PageHeader` | variantes contextuales | llamadas a la acción útiles |
| `SearchField`, `DateRange`, `FilterChip`, `Pagination` | empty/active/clear | filtros reflejados en URL si aporta |

- [ ] Crear layout `layouts/app.blade.php`: sidebar oscura, logo ADARA, navegación en grupos, toolbar, área principal, encabezado de página, notificaciones, modal-root.
- [ ] Construir shell fluido para ancho 1366–1440, menú colapsable en pantallas reducidas; evitar responsive móvil complejo en primera fase (MacBook primero).
- [ ] Definir tipografía, jerarquía, iconos, foco, 8px spacing grid, 10/18/20px radius y tokens exactos (ver `00_CONTEXTO_Y_DECISIONES.md`).
- [ ] Crear un catálogo de componentes/estados local accesible solo en entorno de desarrollo (sin ruta pública de debug en release) tomando `16_Estados_UI.svg`.
- [ ] Cambiar marca de UI «Aura» a **Adara**; los archivos de referencia no necesitan ser renombrados.

**Criterios F1:** 1366×768 sin desbordes generales; tabulación y foco visibles; ningún fetch remoto; transiciones suaves; layout y estilos reusables; sin imports duplicados Alpine/Livewire.

## F2 — Navegación y pantallas estáticas fieles al diseño

- [ ] Implementar las **17 rutas** (si corresponde) con componentes de página bien nombrados; cada una puede empezar vacía pero estructurada con encabezado, acciones, filtros y estados.
- [ ] Implementar `wire:navigate`, estado activo del sidebar, título de la ventana, breadcrumbs cuando correspondan y errores 404.
- [ ] Crear **las 20 variantes de modales** como composición/reutilización del modal base; NO crear 20 motores de modal ni duplicar lógica.
- [ ] Incorporar imágenes/iconos de producto locales o placeholders; definir fallback sin emojis si la fuente no existe.
- [ ] Asegurar buen comportamiento de overlays al cambiar de ruta, al finalizar guardados y al cerrar con teclado.
- [ ] Ajustar capturas de pantallas principales contra los SVG/HTML de referencia.

**Criterios F2:** cada menú navega, las páginas tienen sus estados iniciales, los modales abren/cierra de forma coherente y no hay «datos falsos» mostrados como verdaderos en release.

## F3 — Catálogo, categorías, proveedores y clientes

- [ ] Catálogo: tabla con código, producto, categoría, proveedor, precio, costo, rentabilidad, stock, estado; búsqueda con debounce razonable, filtros, orden y paginación **en servidor**.
- [ ] Detalle de producto: resumen y datos, costo/venta actual, movimientos, últimas compras, historial; acciones editar/archivar.
- [ ] Alta/edición producto: validaciones visibles, precios en ARS, campo costo vs precio claro, stock inicial como movimiento auditado y no edición arbitraria del saldo.
- [ ] Categorías: listado y modal nueva categoría, edición y archivado cuando corresponda.
- [ ] Proveedores: lista, ficha y modal alta; información comercial opcional.
- [ ] Clientes: lista, modal alta, cliente opcional al vender, privacidad y búsqueda simple.
- [ ] Importación Excel/CSV: paso 1 elegir archivo → 2 previsualizar → 3 mapear campos/errores → 4 confirmar y reportar; no importar automático al seleccionar archivo.

**Criterios F3:** CRUD funcional con permisos/validaciones de dominio, estados vacíos, mensajes claros, no se borra historial; búsqueda/paginación correctas con miles de filas simuladas.

## F4 — Stock y compras

- [ ] Stock: estado disponible/bajo/sin stock, movimientos auditables, filtros por fechas/tipo, ajuste con motivo obligatorio.
- [ ] Pedidos a proveedor: nuevo pedido con ítems, cantidades, costos estimados, envío global y selección de criterio de reparto.
- [ ] Detalle de pedido: desglose de subtotales, flete imputado, unidades, costo estimado final, estado de recepción; avisos si se modifica un pedido ya recibido.
- [ ] Recepción: seleccionar pedido, ver recibido/pendiente, confirmar cantidades reales y costo, generar preview antes de confirmar.
- [ ] No presentar «recibido» hasta que el backend confirme transacción. Si se soportan recepciones parciales, dejar saldo/pendiente inequívoco.

**Criterios F4:** costo antes/después comprensible en ARS, stock actualizado una sola vez, UI bloquea operaciones ya confirmadas y muestra el historial real.

## F5 — Venta rápida (prioridad máxima de usabilidad)

- [ ] `Nueva venta`: búsqueda rápida por código/nombre; tarjetas/lista de resultados, botón agregar y carrito visible.
- [ ] Control claro de cantidad, disponibilidad, precio unitario, subtotales y total; feedback de error sin perder carrito.
- [ ] Cliente opcional con «Consumidor final» predeterminado, medio de pago registrado manualmente; no pasarela bancaria.
- [ ] Modal **Confirmar venta** con resumen de líneas, total, medio de pago; deshabilitar mientras guarda, bloquear doble envío.
- [ ] Resultado «Venta registrada», número de operación y acciones ver detalle / nueva venta / generar comprobante no fiscal opcional.
- [ ] Historial con filtros, detalle inmutable, vista de anulación; motivo obligatorio y confirmación explícita.
- [ ] Atajos limitados y documentados (p. ej. `/` para búsqueda cuando no se escribe en input; `Esc` cierra modal); nunca atajo que confirma venta accidentalmente.

**Criterios F5:** flujo probado con 1 y varios productos, sin stock, precio inválido, venta repetida, recarga al confirmar; un clic no descuenta dos veces.

## F6 — Dashboard, reportes y ajustes

- [ ] Inicio: KPI de ventas del período, ganancia bruta, stock crítico, ventas recientes y accesos directos reales.
- [ ] Reportes: filtros por período y categoría, ventas, costo, ganancia bruta, margen real, ranking; diferenciar recargo/margen y excluir ventas anuladas o mostrar ajustes.
- [ ] Chart.js: inicialización y destrucción correcta al navegar con Livewire; cuadros con fallback accesible tabular, sin gráficos de muestra.
- [ ] Exportación CSV/XLSX (y PDF solo si se justifica): selección de rango, previsualización de filtros, confirmación y descarga.
- [ ] Configuración: empresa, recargo deseado, redondeo, stock mínimo, texto de ayuda para entender efectos; control de cambios que afecten precios.
- [ ] Página de ayuda: registrar producto, comprar, recibir, vender, anular, respaldar y restaurar, con instrucciones simples.

**Criterios F6:** gráficos y KPI coinciden con consultas auditadas; errores/ceros/meses sin ventas funcionan; no confundir ganancia bruta con neta.

## F7 — Respaldos, accesibilidad y pulido final

- [ ] Vista de respaldos: última copia, advertencia si no existe, crear, verificar y seleccionar archivo para restaurar.
- [ ] Restaurar: muestra metadatos, advertencias, respaldo preventivo y confirmación fuerte; no se cierra ventana ni bloquea sin mensaje.
- [ ] Estados de progreso y errores útiles (sin datos sensibles), ruta de respaldo y guía para pendrive.
- [ ] Pruebas de teclado, foco, lector de pantalla básico, zoom 125%, contraste y `prefers-reduced-motion`.
- [ ] Finalizar transiciones, feedback al guardar, foco después de errores, validación en español y consistencia visual.
- [ ] Empaquetado Vite: inspeccionar Network en entorno offline; no solicitudes a `fonts.googleapis`, CDNs, trackers ni URLs remotas.

## Matriz de 17 pantallas (referencias SVG originales)

| ID | SVG de referencia en `../svgs/` | Ruta sugerida (ajustar a repo) | Componente y objetivo |
| --- | --- | --- | --- |
| P01 | `01_Inicio.svg` | `/` | Dashboard KPIs + alertas |
| P02 | `02_Catalogo.svg` | `/productos` | búsqueda/listado |
| P03 | `03_Detalle_producto.svg` | `/productos/{product}` | producto, costo, stock, historia |
| P04 | `04_Stock_movimientos.svg` | `/stock` | saldo y movimientos |
| P05 | `05_Pedidos_proveedor.svg` | `/pedidos` | pedidos y estados |
| P06 | `06_Detalle_pedido.svg` | `/pedidos/{order}` | detalle y flete |
| P07 | `07_Recepcion_compras.svg` | `/recepciones` | recepción de mercadería |
| P08 | `08_Historial_ventas.svg` | `/ventas` | historial/anulaciones |
| P09 | `09_Nueva_venta.svg` | `/ventas/nueva` | carrito y confirmación |
| P10 | `10_Clientes.svg` | `/clientes` | contactos opcionales |
| P11 | `11_Proveedores.svg` | `/proveedores` | proveedores |
| P12 | `12_Categorias.svg` | `/categorias` | categorías |
| P13 | `13_Reportes.svg` | `/reportes` | KPI y Chart.js |
| P14 | `14_Configuracion.svg` | `/configuracion` | preferencia comerciales |
| P15 | `15_Copias_seguridad.svg` | `/respaldos` | crear/restaurar copia |
| P16 | `16_Estados_UI.svg` | `/dev/componentes` solo en local | galería de estados/UI |
| P17 | `17_Ayuda.svg` | `/ayuda` | instrucciones no técnicas |

**Orden de rutas:** declarar `/ventas/nueva` antes que `/ventas/{id}` si se introduce esta última; evitar que rutas genéricas capturen rutas fijas. Preferir nombres y route model binding explícitos.

## Matriz de 20 modales / overlays

| ID | SVG original | Acción de UI | Validación/interacción |
| --- | --- | --- | --- |
| M01 | `M01_Nuevo_producto.svg` | crear producto | código único, campos esenciales |
| M02 | `M02_Editar_producto.svg` | editar producto | no editar saldo directo |
| M03 | `M03_Nuevo_pedido.svg` | armar pedido | proveedor/ítems/flete |
| M04 | `M04_Recepcion_mercaderia.svg` | confirmar recepción | qty pendiente, idempotencia |
| M05 | `M05_Ajuste_stock.svg` | ajustar stock | motivo + delta |
| M06 | `M06_Confirmar_venta.svg` | confirmar venta | subtotal/stock/total/método |
| M07 | `M07_Categoria_nueva.svg` | crear categoría | no duplicados |
| M08 | `M08_Cliente_nuevo.svg` | crear cliente | campos opcionales validados |
| M09 | `M09_Proveedor_nuevo.svg` | crear proveedor | nombre obligatorio |
| M10 | `M10_Detalle_venta.svg` | ver operación | solo lectura salvo anulación |
| M11 | `M11_Importar_Excel.svg` | asistente importación | preview, mapping, errores |
| M12 | `M12_Exportar_reporte.svg` | exportar reportes | períodos y opciones |
| M13 | `M13_Crear_respaldo.svg` | crear copia | progreso/verificación |
| M14 | `M14_Restaurar_respaldo.svg` | restaurar | respaldo previo + confirmación |
| M15 | `M15_Eliminar_producto.svg` | **archivar** producto | explicar por qué no se borra |
| M16 | `M16_Filtros_catalogo.svg` | filtros avanzados | reset/aplicar |
| M17 | `M17_Avisos_stock.svg` | alertas stock | filtros y navegación |
| M18 | `M18_Cancelar_venta.svg` | anular venta | razón + advertencia |
| M19 | `M19_Venta_guardada.svg` | éxito de venta | evita nueva confirmación |
| M20 | `M20_Filtro_periodo.svg` | período reportes | rango válido |

## Especificación de motion y accesibilidad

| Evento | Animación | Duración objetivo |
| --- | --- | --- |
| Cambio de página | fade / translateY(4–6px) | 150–200ms |
| Modal al abrir | opacity + scale(.98→1) | 160–200ms |
| Modal al cerrar | opacity + scale(1→.98) | 120–150ms |
| Menús, filtros | fade + translateY(4px) | 120–150ms |
| Panel lateral | translateX | 180–220ms |
| Toast | fade + desplazamiento corto | 160–200ms |
| Cambio de filas | feedback de selección, no cascadas | ≤150ms |

- Preferir CSS transitions y `x-transition`; usar `wire:loading` para indicar guardados.
- No animar valores económicos, costo, stock o cifras contables.
- Respetar `@media (prefers-reduced-motion: reduce)` anulando transformaciones/animaciones no esenciales.
- Tamaño click target ideal ≥40px, `:focus-visible`, etiquetas `for`, mensajes `aria-live`, diálogo con foco contenido, retorno de foco; no usar solo color para estado.

## Control de calidad frontend por entrega

- [ ] Diseño coincide visualmente con los recursos de referencia (no pixel-perfect forzado cuando contradice accesibilidad).
- [ ] La operación se puede completar **sin mouse** al menos en formularios y modales.
- [ ] `npm run build` pasa; no warnings de scripts y Alpine duplicados.
- [ ] Estado loading/empty/error/success probado con datos reales/fixtures.
- [ ] Ningún dato económico se calcula como verdad en el navegador.
- [ ] No hay recursos remotos al abrir y usar en la Mac sin internet.
- [ ] Verificar 1366×768, 1440×900 y zoom 125%.

## Fuentes técnicas oficiales

- Livewire 4: https://livewire.laravel.com/docs/4.x/components
- Livewire 4 pages: https://livewire.laravel.com/docs/4.x/pages
- Livewire navegación: https://livewire.laravel.com/docs/4.x/navigate
- Tailwind + Laravel/Vite: https://tailwindcss.com/docs/installation/framework-guides/laravel/vite
