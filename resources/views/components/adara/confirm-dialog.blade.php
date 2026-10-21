@props(['id', 'title', 'message', 'confirmLabel' => 'Confirmar', 'tone' => 'danger'])

<x-adara.modal :id="$id" :title="$title" :dismissible="false">
    <p class="text-sm leading-6 text-adara-readable-muted">{{ $message }}</p>

    <x-slot:footer>
        <x-adara.button variant="outline" x-on:click="close()">Cancelar</x-adara.button>
        <x-adara.button :variant="$tone === 'danger' ? 'danger' : 'primary'" x-on:click="$dispatch('adara:confirmed', { id: '{{ $id }}' }); close()">{{ $confirmLabel }}</x-adara.button>
    </x-slot:footer>
</x-adara.modal>
