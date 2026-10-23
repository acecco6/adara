<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Subcategory;
use App\Models\Supplier;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class CatalogReferenceService
{
    /** @param array{name: string} $input */
    public function saveCategory(?int $categoryId, array $input): Category
    {
        $data = Validator::make(['name' => trim($input['name'])], [
            'name' => ['required', 'string', 'max:255'],
        ], [
            'name.required' => 'Ingresá el nombre de la categoría.',
            'name.max' => 'El nombre no puede superar 255 caracteres.',
        ])->validate();

        $duplicate = Category::query()
            ->when($categoryId !== null, fn ($query) => $query->whereKeyNot($categoryId))
            ->pluck('name')
            ->contains(fn (string $name): bool => mb_strtolower($name) === mb_strtolower($data['name']));

        if ($duplicate) {
            throw ValidationException::withMessages(['name' => 'Ya existe una categoría con ese nombre.']);
        }

        if ($categoryId === null) {
            return Category::create(['name' => $data['name'], 'is_active' => true]);
        }

        $category = Category::findOrFail($categoryId);
        $category->update(['name' => $data['name']]);

        return $category;
    }

    /** @param array{category_id: int|string, name: string} $input */
    public function saveSubcategory(?int $subcategoryId, array $input): Subcategory
    {
        $data = Validator::make([
            'subcategory_category_id' => $input['category_id'],
            'subcategory_name' => trim($input['name']),
        ], [
            'subcategory_category_id' => ['required', 'integer', 'exists:categories,id'],
            'subcategory_name' => ['required', 'string', 'max:255'],
        ], [
            'subcategory_category_id.required' => 'Elegí una categoría.',
            'subcategory_name.required' => 'Ingresá el nombre de la subcategoría.',
        ])->validate();

        $category = Category::findOrFail((int) $data['subcategory_category_id']);
        $subcategory = $subcategoryId === null ? null : Subcategory::findOrFail($subcategoryId);

        if (! $category->is_active && $category->id !== $subcategory?->category_id) {
            throw ValidationException::withMessages(['subcategory_category_id' => 'Elegí una categoría activa.']);
        }

        if ($subcategory !== null && $subcategory->category_id !== $category->id && $subcategory->groups()->exists()) {
            throw ValidationException::withMessages(['subcategory_category_id' => 'No se puede mover una subcategoría que ya tiene productos.']);
        }

        $duplicate = Subcategory::query()->where('category_id', $category->id)
            ->when($subcategoryId !== null, fn ($query) => $query->whereKeyNot($subcategoryId))
            ->pluck('name')
            ->contains(fn (string $name): bool => mb_strtolower($name) === mb_strtolower($data['subcategory_name']));

        if ($duplicate) {
            throw ValidationException::withMessages(['subcategory_name' => 'Ya existe una subcategoría con ese nombre en esta categoría.']);
        }

        if ($subcategory === null) {
            return Subcategory::create(['category_id' => $category->id, 'name' => $data['subcategory_name'], 'is_active' => true]);
        }

        $subcategory->update(['category_id' => $category->id, 'name' => $data['subcategory_name']]);

        return $subcategory;
    }

    public function setSubcategoryActive(int $subcategoryId, bool $active): void
    {
        Subcategory::findOrFail($subcategoryId)->update(['is_active' => $active]);
    }

    /**
     * @param  array{name: string, phone?: ?string, email?: ?string, notes?: ?string}  $input
     */
    public function saveContact(string $kind, ?int $contactId, array $input): Customer|Supplier
    {
        $model = $this->contactModel($kind);
        $data = Validator::make([
            'name' => trim($input['name']),
            'phone' => trim($input['phone'] ?? ''),
            'email' => trim($input['email'] ?? ''),
            'notes' => trim($input['notes'] ?? ''),
        ], [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:80'],
            'email' => ['nullable', 'email', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [
            'name.required' => 'Ingresá un nombre.',
            'email.email' => 'Ingresá un correo válido.',
        ])->validate();

        $values = [
            'name' => $data['name'],
            'phone' => $data['phone'] ?: null,
            'email' => $data['email'] ?: null,
            'notes' => $data['notes'] ?: null,
        ];

        if ($contactId === null) {
            return $model::create($values + ['is_active' => true]);
        }

        $contact = $model::findOrFail($contactId);
        $contact->update($values);

        return $contact;
    }

    public function setCategoryActive(int $categoryId, bool $active): void
    {
        Category::findOrFail($categoryId)->update(['is_active' => $active]);
    }

    public function setContactActive(string $kind, int $contactId, bool $active): void
    {
        $this->contactModel($kind)::findOrFail($contactId)->update(['is_active' => $active]);
    }

    /** @return class-string<Customer|Supplier> */
    private function contactModel(string $kind): string
    {
        return match ($kind) {
            'customer' => Customer::class,
            'supplier' => Supplier::class,
            default => throw new InvalidArgumentException('Tipo de contacto inválido.'),
        };
    }
}
