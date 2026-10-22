<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use Illuminate\Contracts\View\View;

class PurchaseController extends Controller
{
    public function show(PurchaseOrder $order): View
    {
        return view('purchases.show', ['order' => $order]);
    }
}
