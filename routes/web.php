<?php

use App\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::view('/productos', 'products.index')->name('products.index');

Route::get('/productos/{product}', [ProductController::class, 'show'])
    ->whereNumber('product')->name('products.show');

Route::view('/stock', 'inventory')->name('inventory.index');

Route::view('/pedidos', 'section', [
    'title' => 'Pedidos a proveedor',
    'section' => 'Operaciones',
    'summary' => 'Acá vas a preparar pedidos y consultar su estado.',
    'pageKind' => 'list',
    'columns' => ['Pedido', 'Proveedor', 'Fecha', 'Estado'],
    'actionLabel' => 'Nuevo pedido',
])->name('purchases.index');

Route::view('/pedidos/{order}', 'section', [
    'title' => 'Detalle del pedido',
    'section' => 'Pedidos a proveedor',
    'summary' => 'El detalle reunirá los artículos pedidos y sus recepciones.',
    'pageKind' => 'detail',
    'backRoute' => 'purchases.index',
    'backLabel' => 'Volver a pedidos',
])->whereNumber('order')->name('purchases.show');

Route::view('/recepciones', 'section', [
    'title' => 'Recepción de compras',
    'section' => 'Operaciones',
    'summary' => 'Acá vas a registrar la mercadería que recibas de tus proveedores.',
    'pageKind' => 'list',
    'columns' => ['Recepción', 'Pedido', 'Proveedor', 'Fecha'],
    'actionLabel' => 'Registrar recepción',
])->name('purchases.receipts');

Route::view('/ventas/nueva', 'section', [
    'title' => 'Nueva venta',
    'section' => 'Ventas',
    'summary' => 'Esta pantalla permitirá preparar y confirmar una venta.',
    'backRoute' => 'sales.index',
    'backLabel' => 'Volver a ventas',
])->name('sales.create');

Route::view('/ventas', 'section', [
    'title' => 'Ventas',
    'section' => 'Operaciones',
    'summary' => 'Acá vas a consultar las ventas confirmadas y su historial.',
    'pageKind' => 'list',
    'columns' => ['Venta', 'Fecha', 'Clienta', 'Total', 'Estado'],
    'actionLabel' => 'Nueva venta',
])->name('sales.index');

Route::view('/clientes', 'contacts', ['kind' => 'customer'])->name('customers.index');
Route::view('/proveedores', 'contacts', ['kind' => 'supplier'])->name('suppliers.index');
Route::view('/categorias', 'categories')->name('categories.index');

Route::view('/reportes', 'section', [
    'title' => 'Reportes',
    'section' => 'Administración',
    'summary' => 'Acá vas a consultar informes construidos a partir de operaciones reales.',
])->name('reports.index');

Route::view('/configuracion', 'section', [
    'title' => 'Configuración',
    'section' => 'Sistema',
    'summary' => 'Acá vas a administrar las preferencias de Adara Gestión.',
])->name('settings.index');

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
