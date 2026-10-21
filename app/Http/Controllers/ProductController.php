<?php

namespace App\Http\Controllers;

use App\Models\ProductGroup;
use Illuminate\Contracts\View\View;

class ProductController extends Controller
{
    public function show(ProductGroup $product): View
    {
        return view('products.show', ['product' => $product]);
    }
}
