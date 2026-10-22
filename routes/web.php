<?php

use App\Http\Controllers\ProductController;
use App\Http\Controllers\PurchaseController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::view('/productos', 'products.index')->name('products.index');

Route::get('/productos/{product}', [ProductController::class, 'show'])
    ->whereNumber('product')->name('products.show');

Route::view('/stock', 'inventory')->name('inventory.index');

Route::view('/pedidos', 'purchases.index')->name('purchases.index');

Route::get('/pedidos/{order}', [PurchaseController::class, 'show'])
    ->whereNumber('order')->name('purchases.show');

Route::view('/recepciones', 'purchases.receipts')->name('purchases.receipts');

Route::view('/ventas/nueva', 'sales.create')->name('sales.create');

Route::view('/ventas', 'sales.index')->name('sales.index');

Route::view('/cobros', 'payments.index')->name('payments.index');

Route::view('/clientes', 'contacts', ['kind' => 'customer'])->name('customers.index');
Route::view('/clientes/deudas', 'customers.debts')->name('customers.debts');
Route::view('/proveedores', 'contacts', ['kind' => 'supplier'])->name('suppliers.index');
Route::view('/categorias', 'categories')->name('categories.index');

Route::view('/reportes', 'reports')->name('reports.index');

Route::view('/configuracion', 'settings.index')->name('settings.index');

Route::view('/respaldos', 'section', [
    'title' => 'Copias de seguridad',
    'section' => 'Sistema',
    'summary' => 'Acá vas a crear y consultar las copias de seguridad de tus datos.',
])->name('backups.index');

Route::view('/ayuda', 'section', [
    'title' => 'Ayuda',
    'section' => 'Sistema',
    'summary' => 'Acá vas a encontrar guías para usar las funciones disponibles.',
])->name('help.index');

if (app()->environment(['local', 'testing'])) {
    Route::view('/dev/componentes', 'dev.components')->name('dev.components');
}
