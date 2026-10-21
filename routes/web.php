<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

if (app()->environment(['local', 'testing'])) {
    Route::view('/dev/componentes', 'dev.components')->name('dev.components');
}
