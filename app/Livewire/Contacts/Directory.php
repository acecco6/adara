<?php

namespace App\Livewire\Contacts;

use App\Models\Customer;
use App\Models\Supplier;
use App\Services\CatalogReferenceService;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

class Directory extends Component
{
    use WithPagination;

    #[Locked]
    public string $kind;

    public string $search = '';

    public string $name = '';

    public string $phone = '';

    public string $email = '';

    public string $notes = '';

    #[Locked]
    public ?int $editingId = null;

    public function mount(string $kind): void
    {
        if (! in_array($kind, ['customer', 'supplier'], true)) {
            throw new InvalidArgumentException('Tipo de contacto inválido.');
        }

        $this->kind = $kind;
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function startCreating(): void
    {
        $this->editingId = null;
        $this->reset('name', 'phone', 'email', 'notes');
        $this->resetErrorBag();
        $this->dispatch('adara:open', id: 'contact-form');
    }

    public function startEditing(int $contactId): void
    {
        $contact = $this->model()::findOrFail($contactId);
        $this->editingId = $contact->id;
        $this->name = $contact->name;
        $this->phone = $contact->phone ?? '';
        $this->email = $contact->email ?? '';
        $this->notes = $contact->notes ?? '';
        $this->resetErrorBag();
        $this->dispatch('adara:open', id: 'contact-form');
    }

    public function save(CatalogReferenceService $references): void
    {
        $references->saveContact($this->kind, $this->editingId, [
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'notes' => $this->notes,
        ]);
        $this->resetErrorBag();
        $this->dispatch('adara:close', id: 'contact-form');
        $this->dispatch('adara:toast', tone: 'success', message: $this->kind === 'supplier' ? 'Proveedor guardado.' : 'Clienta guardada.');
    }

    public function toggleActive(int $contactId, CatalogReferenceService $references): void
    {
        $contact = $this->model()::findOrFail($contactId);
        $references->setContactActive($this->kind, $contactId, ! $contact->is_active);
        $this->dispatch('adara:toast', tone: 'success', message: $contact->is_active ? 'Registro archivado.' : 'Registro reactivado.');
    }

    public function render(): View
    {
        $query = $this->model()::query()
            ->when(trim($this->search) !== '', fn ($query) => $query->where('name', 'like', '%'.trim($this->search).'%')->orWhere('phone', 'like', '%'.trim($this->search).'%'))
            ->orderBy('name')
            ->orderBy('id');

        if ($this->kind === 'supplier') {
            $query->withCount('groups');
        }

        return view('livewire.contacts.directory', ['contacts' => $query->paginate(15)]);
    }

    /** @return class-string<Customer|Supplier> */
    private function model(): string
    {
        return $this->kind === 'supplier' ? Supplier::class : Customer::class;
    }
}
