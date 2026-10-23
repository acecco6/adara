<div class="flex flex-col gap-6">
    <header class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="text-[11px] font-extrabold uppercase tracking-[0.16em] text-adara-primary">Administración</p>
            <h1 class="mt-2 font-display text-[30px] font-semibold text-adara-ink">Categorías</h1>
            <p class="mt-2 text-sm text-adara-readable-muted">Organizá los productos. Archivar una categoría conserva sus vínculos existentes.</p>
        </div>
        <div class="flex flex-wrap gap-2"><x-adara.button variant="outline" wire:click="startCreatingSubcategory" aria-haspopup="dialog" aria-controls="subcategory-form">Nueva subcategoría</x-adara.button><x-adara.button wire:click="startCreating" aria-haspopup="dialog" aria-controls="category-form">Nueva categoría</x-adara.button></div>
    </header>

    <x-adara.modal id="category-form" :title="$editingId === null ? 'Nueva categoría' : 'Editar categoría'" wire:ignore.self>
        <form id="category-form-fields" wire:submit="save">
            <x-adara.input id="category-name" name="name" label="Nombre de la categoría" wire:model="name" required />
        </form>
        <x-slot:footer>
            <span wire:loading wire:target="save" role="status" class="mr-auto self-center text-sm text-adara-readable-muted">Guardando…</span>
            <x-adara.button variant="outline" x-on:click="close()">Cancelar</x-adara.button>
            <x-adara.button type="submit" form="category-form-fields" wire:loading.attr="disabled" wire:target="save">Guardar categoría</x-adara.button>
        </x-slot:footer>
    </x-adara.modal>

    <x-adara.modal id="subcategory-form" :title="$editingSubcategoryId === null ? 'Nueva subcategoría' : 'Editar subcategoría'" wire:ignore.self>
        <form id="subcategory-form-fields" wire:submit="saveSubcategory" class="grid gap-4">
            <x-adara.select id="subcategory-category" name="subcategory_category_id" label="Categoría" wire:model="subcategoryCategoryId" required>
                <option value="">Elegir categoría</option>
                @foreach ($availableCategories as $availableCategory)
                    <option value="{{ $availableCategory->id }}">{{ $availableCategory->name }}</option>
                @endforeach
            </x-adara.select>
            <x-adara.input id="subcategory-name" name="subcategory_name" label="Nombre de la subcategoría" wire:model="subcategoryName" required />
        </form>
        <x-slot:footer>
            <span wire:loading wire:target="saveSubcategory" role="status" class="mr-auto self-center text-sm text-adara-readable-muted">Guardando…</span>
            <x-adara.button variant="outline" x-on:click="close()">Cancelar</x-adara.button>
            <x-adara.button type="submit" form="subcategory-form-fields" wire:loading.attr="disabled" wire:target="saveSubcategory">Guardar subcategoría</x-adara.button>
        </x-slot:footer>
    </x-adara.modal>

    <section class="overflow-hidden rounded-adara-card border border-adara-border bg-adara-surface shadow-adara-panel" aria-label="Listado de categorías">
        <div class="border-b border-adara-border p-5 sm:p-6">
            <div class="max-w-sm"><x-adara.input id="category-search" name="search" type="search" label="Buscar categoría o subcategoría" wire:model.live.debounce.300ms="search" /></div>
        </div>
        <div wire:loading.delay wire:target="search,toggleActive" role="status" class="px-5 py-2 text-sm text-adara-readable-muted">Actualizando categorías…</div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[520px] text-left text-sm" aria-label="Categorías">
                <thead class="bg-adara-background text-[11px] font-semibold uppercase tracking-wide text-adara-readable-muted"><tr><th scope="col" class="px-5 py-3">Categoría</th><th scope="col" class="px-5 py-3">Productos</th><th scope="col" class="px-5 py-3">Estado</th><th scope="col" class="px-5 py-3 text-right">Acciones</th></tr></thead>
                <tbody class="divide-y divide-adara-border">
                    @forelse ($categories as $category)
                        <tr wire:key="category-{{ $category->id }}">
                            <td class="px-5 py-4 font-semibold">{{ $category->name }}</td>
                            <td class="px-5 py-4">{{ $category->groups_count }}</td>
                            <td class="px-5 py-4"><x-adara.badge :tone="$category->is_active ? 'success' : 'neutral'">{{ $category->is_active ? 'Activa' : 'Archivada' }}</x-adara.badge></td>
                            <td class="px-5 py-4"><div class="flex justify-end gap-2"><x-adara.button variant="outline" wire:click="startEditing({{ $category->id }})">Editar</x-adara.button><x-adara.button variant="ghost" wire:click="toggleActive({{ $category->id }})">{{ $category->is_active ? 'Archivar' : 'Reactivar' }}</x-adara.button></div></td>
                        </tr>
                        @foreach ($category->subcategories as $subcategory)
                            <tr wire:key="subcategory-{{ $subcategory->id }}" class="bg-adara-background/50">
                                <td class="px-5 py-3 pl-10 text-adara-ink"><span class="mr-2 text-adara-readable-muted" aria-hidden="true">↳</span>{{ $subcategory->name }}</td>
                                <td class="px-5 py-3 text-adara-readable-muted">Subcategoría</td>
                                <td class="px-5 py-3"><x-adara.badge :tone="$subcategory->is_active ? 'success' : 'neutral'">{{ $subcategory->is_active ? 'Activa' : 'Archivada' }}</x-adara.badge></td>
                                <td class="px-5 py-3"><div class="flex justify-end gap-2"><x-adara.button variant="outline" wire:click="startEditingSubcategory({{ $subcategory->id }})">Editar</x-adara.button><x-adara.button variant="ghost" wire:click="toggleSubcategoryActive({{ $subcategory->id }})">{{ $subcategory->is_active ? 'Archivar' : 'Reactivar' }}</x-adara.button></div></td>
                            </tr>
                        @endforeach
                    @empty
                        <tr><td colspan="4" class="px-5 py-10 text-center"><p class="font-display text-lg font-semibold">No hay categorías para mostrar</p><p class="mt-2 text-sm text-adara-readable-muted">Agregá la primera o cambiá la búsqueda.</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($categories->hasPages()) <div class="border-t border-adara-border px-5 py-4">{{ $categories->links('components.adara.pagination', data: ['scrollTo' => false]) }}</div> @endif
    </section>
</div>
