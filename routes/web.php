<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductImageController;
use App\Http\Controllers\PurchaseController;
use App\Http\Middleware\EnsureActiveUser;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])->middleware('throttle:5,1')->name('register.store');
});

Route::middleware(['auth', EnsureActiveUser::class])->group(function (): void {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::view('/', 'welcome')->name('home');

    Route::view('/productos', 'products.index')->name('products.index');
    Route::get('/productos/imagenes/{image}', [ProductImageController::class, 'show'])
        ->whereNumber('image')->name('products.images.show');

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
});
