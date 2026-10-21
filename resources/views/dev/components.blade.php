@extends('layouts.app')

@section('title', 'Componentes')
@section('section', 'Desarrollo')
@section('page', 'Componentes')

@section('content')
    <div class="flex flex-col gap-7">
        <header>
            <p class="text-[11px] font-extrabold uppercase tracking-[0.16em] text-adara-primary">Biblioteca visual</p>
            <h1 class="mt-2 font-display text-[30px] font-semibold leading-tight text-adara-ink">Componentes y estados</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-adara-readable-muted">Ejemplos de interfaz para revisar diseño y accesibilidad. Esta página no guarda información.</p>
        </header>

        <section class="rounded-adara-card border border-adara-border bg-adara-surface p-5 shadow-adara-panel sm:p-7" aria-labelledby="botones-titulo">
            <h2 id="botones-titulo" class="font-display text-xl font-semibold">Botones</h2>
            <p class="mt-1 text-sm text-adara-readable-muted">Variantes, estado inactivo y estado de espera.</p>
            <div class="mt-5 flex flex-wrap gap-3">
                <x-adara.button>Principal</x-adara.button>
                <x-adara.button variant="secondary">Secundario</x-adara.button>
                <x-adara.button variant="outline">Contorno</x-adara.button>
                <x-adara.button variant="ghost">Discreto</x-adara.button>
                <x-adara.button variant="danger">Peligro</x-adara.button>
                <x-adara.button disabled>Inactivo</x-adara.button>
                <x-adara.button :loading="true">Guardando</x-adara.button>
            </div>
        </section>

        <section class="rounded-adara-card border border-adara-border bg-adara-surface p-5 shadow-adara-panel sm:p-7" aria-labelledby="campos-titulo">
            <h2 id="campos-titulo" class="font-display text-xl font-semibold">Campos y validación</h2>
            <p class="mt-1 text-sm text-adara-readable-muted">Cada campo tiene etiqueta visible y el error se anuncia junto al control.</p>
            <div class="mt-5 grid gap-5 md:grid-cols-2">
                <x-adara.input id="ejemplo-nombre" label="Nombre del producto" hint="Escribí un nombre fácil de identificar." placeholder="Ej.: crema facial" required />
                <x-adara.input id="ejemplo-codigo" label="Código" error="El código ya está en uso." value="EJ-001" required />
                <x-adara.currency-input id="ejemplo-importe" label="Importe en ARS" value="1.234,56" />
                <x-adara.select id="ejemplo-categoria" label="Categoría" hint="Seleccioná una opción para continuar.">
                    <option value="">Elegir categoría</option>
                    <option value="ejemplo">Categoría de ejemplo</option>
                </x-adara.select>
                <x-adara.textarea id="ejemplo-nota" label="Nota interna" hint="Agregá información útil si hace falta." placeholder="Escribí una nota breve..." />
                <x-adara.input id="ejemplo-inactivo" label="Campo inactivo" value="No disponible" disabled />
            </div>
        </section>

        <section class="rounded-adara-card border border-adara-border bg-adara-surface p-5 shadow-adara-panel sm:p-7" aria-labelledby="dialogos-titulo" x-data="{ confirmed: false }" x-on:adara:confirmed.window="if ($event.detail.id === 'ejemplo-confirmacion') { confirmed = true; $dispatch('adara:toast', { message: 'Confirmación de ejemplo completada.', tone: 'success' }) }">
            <h2 id="dialogos-titulo" class="font-display text-xl font-semibold">Diálogos y avisos</h2>
            <p class="mt-1 text-sm text-adara-readable-muted">Ejemplos visuales: no guardan ni eliminan información.</p>
            <div class="mt-5 flex flex-wrap gap-3">
                <x-adara.button x-on:click="$dispatch('adara:open', { id: 'ejemplo-modal' })">Abrir modal</x-adara.button>
                <x-adara.button variant="danger" x-on:click="$dispatch('adara:open', { id: 'ejemplo-confirmacion' })">Pedir confirmación</x-adara.button>
                <x-adara.button variant="outline" x-on:click="$dispatch('adara:toast', { message: 'Los cambios de ejemplo están listos.', tone: 'success' })">Mostrar aviso</x-adara.button>
                <x-adara.button variant="outline" x-on:click="$dispatch('adara:toast', { message: 'No se pudo completar el ejemplo. Revisá los datos.', tone: 'error' })">Mostrar error</x-adara.button>
            </div>
            <p x-cloak x-show="confirmed" class="mt-4 text-sm font-medium text-adara-success" role="status">Acción de ejemplo confirmada.</p>

            <x-adara.modal id="ejemplo-modal" title="Modal de ejemplo" description="Este formulario muestra el comportamiento del componente compartido.">
                <x-adara.input id="ejemplo-modal-nombre" label="Nombre de ejemplo" placeholder="Escribí un nombre..." />
                <x-slot:footer>
                    <x-adara.button variant="outline" x-on:click="close()">Cancelar</x-adara.button>
                    <x-adara.button x-on:click="close(); $dispatch('adara:toast', { message: 'Ejemplo cerrado sin guardar datos.', tone: 'info' })">Continuar</x-adara.button>
                </x-slot:footer>
            </x-adara.modal>

            <x-adara.confirm-dialog id="ejemplo-confirmacion" title="Confirmar acción de ejemplo" message="Esta demostración no cambia datos. Podés cancelar con Escape o el botón Cancelar." confirm-label="Confirmar ejemplo" />
        </section>

        <section class="rounded-adara-card border border-adara-border bg-adara-surface p-5 shadow-adara-panel sm:p-7" aria-labelledby="avisos-titulo">
            <h2 id="avisos-titulo" class="font-display text-xl font-semibold">Etiquetas y avisos</h2>
            <div class="mt-5 flex flex-wrap gap-2">
                <x-adara.badge>Sin estado</x-adara.badge>
                <x-adara.badge tone="success">Completado</x-adara.badge>
                <x-adara.badge tone="warning">Revisar</x-adara.badge>
                <x-adara.badge tone="danger">Error</x-adara.badge>
            </div>
            <div class="mt-5 grid gap-3 lg:grid-cols-2">
                <x-adara.alert tone="info" title="Información">Este es un mensaje informativo.</x-adara.alert>
                <x-adara.alert tone="success" title="Listo">La acción de ejemplo terminó correctamente.</x-adara.alert>
                <x-adara.alert tone="warning" title="Atención">Revisá los datos antes de continuar.</x-adara.alert>
                <x-adara.alert tone="danger" title="No se pudo continuar">Corregí el campo marcado y volvé a intentarlo.</x-adara.alert>
            </div>
        </section>

        <section class="grid gap-5 lg:grid-cols-2" aria-label="Estados de contenido">
            <x-adara.empty-state title="Todavía no hay registros" description="Cuando agregues información, aparecerá en este espacio." />
            <div class="rounded-adara-card border border-adara-border bg-adara-surface p-5 shadow-adara-panel sm:p-7">
                <h2 class="font-display text-xl font-semibold">Cargando información</h2>
                <p class="mt-1 mb-5 text-sm text-adara-readable-muted">Vista de espera para contenido que aún no llegó.</p>
                <x-adara.loading-state label="Cargando la lista" />
            </div>
        </section>
    </div>
@endsection
