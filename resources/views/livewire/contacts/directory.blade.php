<div class="flex flex-col gap-6">
    <header class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="text-[11px] font-extrabold uppercase tracking-[0.16em] text-adara-primary">Administración</p>
            <h1 class="mt-2 font-display text-[30px] font-semibold text-adara-ink">{{ $kind === 'supplier' ? 'Proveedores' : 'Clientas' }}</h1>
            <p class="mt-2 text-sm text-adara-readable-muted">{{ $kind === 'supplier' ? 'Guardá los datos de quienes te venden productos.' : 'Organizá los contactos de tus clientas.' }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @if ($kind === 'customer')
                <a href="{{ route('customers.debts') }}" wire:navigate class="inline-flex min-h-11 items-center rounded-adara-control border border-adara-border bg-adara-surface px-4 text-sm font-semibold text-adara-ink hover:text-adara-primary">Ver clientas con deuda</a>
                <a href="{{ route('payments.index') }}" wire:navigate class="inline-flex min-h-11 items-center rounded-adara-control border border-adara-border bg-adara-surface px-4 text-sm font-semibold text-adara-ink hover:text-adara-primary">Ver cobros</a>
            @endif
            <x-adara.button wire:click="startCreating" aria-haspopup="dialog" aria-controls="contact-form">{{ $kind === 'supplier' ? 'Nuevo proveedor' : 'Nueva clienta' }}</x-adara.button>
        </div>
    </header>

    <x-adara.modal id="contact-form" :title="($editingId === null ? 'Nuevo ' : 'Editar ').($kind === 'supplier' ? 'proveedor' : 'clienta')" wire:ignore.self>
        <form id="contact-form-fields" wire:submit="save" class="flex flex-col gap-4">
            <x-adara.input id="contact-name" name="name" label="Nombre" wire:model="name" required />
            <x-adara.input id="contact-phone" name="phone" label="Teléfono" type="tel" wire:model="phone" />
            <x-adara.input id="contact-email" name="email" label="Correo electrónico" type="email" wire:model="email" />
            <x-adara.textarea id="contact-notes" name="notes" label="Notas" wire:model="notes" />
        </form>
        <x-slot:footer>
            <span wire:loading wire:target="save" role="status" class="mr-auto self-center text-sm text-adara-readable-muted">Guardando…</span>
            <x-adara.button variant="outline" x-on:click="close()">Cancelar</x-adara.button>
            <x-adara.button type="submit" form="contact-form-fields" wire:loading.attr="disabled" wire:target="save">Guardar</x-adara.button>
        </x-slot:footer>
    </x-adara.modal>

    <section class="overflow-hidden rounded-adara-card border border-adara-border bg-adara-surface shadow-adara-panel" aria-label="{{ $kind === 'supplier' ? 'Listado de proveedores' : 'Listado de clientas' }}">
        <div class="border-b border-adara-border p-5 sm:p-6"><div class="max-w-sm"><x-adara.input id="contact-search" name="search" type="search" label="Buscar por nombre o teléfono" wire:model.live.debounce.300ms="search" /></div></div>
        <div wire:loading.delay wire:target="search,toggleActive" role="status" class="px-5 py-2 text-sm text-adara-readable-muted">Actualizando listado…</div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[620px] text-left text-sm" aria-label="{{ $kind === 'supplier' ? 'Proveedores' : 'Clientas' }}">
                <thead class="bg-adara-background text-[11px] font-semibold uppercase tracking-wide text-adara-readable-muted"><tr><th scope="col" class="px-5 py-3">Nombre</th><th scope="col" class="px-5 py-3">Contacto</th>@if ($kind === 'supplier') <th scope="col" class="px-5 py-3">Productos</th> @endif<th scope="col" class="px-5 py-3">Estado</th><th scope="col" class="px-5 py-3 text-right">Acciones</th></tr></thead>
                <tbody class="divide-y divide-adara-border">
                    @forelse ($contacts as $contact)
                        <tr wire:key="{{ $kind }}-{{ $contact->id }}">
                            <td class="px-5 py-4"><span class="font-semibold">{{ $contact->name }}</span>@if ($contact->notes)<p class="mt-1 max-w-xs truncate text-xs text-adara-readable-muted" title="{{ $contact->notes }}">{{ $contact->notes }}</p>@endif</td>
                            <td class="px-5 py-4 text-adara-readable-muted">{{ $contact->phone ?: 'Sin teléfono' }}@if ($contact->email)<br>{{ $contact->email }}@endif</td>
                            @if ($kind === 'supplier') <td class="px-5 py-4">{{ $contact->groups_count }}</td> @endif
                            <td class="px-5 py-4"><x-adara.badge :tone="$contact->is_active ? 'success' : 'neutral'">{{ $contact->is_active ? 'Activo' : 'Archivado' }}</x-adara.badge></td>
                            <td class="px-5 py-4"><div class="flex justify-end gap-2"><x-adara.button variant="outline" wire:click="startEditing({{ $contact->id }})">Editar</x-adara.button><x-adara.button variant="ghost" wire:click="toggleActive({{ $contact->id }})">{{ $contact->is_active ? 'Archivar' : 'Reactivar' }}</x-adara.button></div></td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ $kind === 'supplier' ? 5 : 4 }}" class="px-5 py-10 text-center"><p class="font-display text-lg font-semibold">No hay {{ $kind === 'supplier' ? 'proveedores' : 'clientas' }} para mostrar</p><p class="mt-2 text-sm text-adara-readable-muted">Agregá el primer registro o cambiá la búsqueda.</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($contacts->hasPages()) <div class="border-t border-adara-border px-5 py-4">{{ $contacts->links('components.adara.pagination', data: ['scrollTo' => false]) }}</div> @endif
    </section>
</div>
