@extends('layouts.app')

@section('title', 'Pedido '.$order->number)
@section('section', 'Pedidos a proveedor')
@section('page', $order->number)

@section('content')
    <livewire:purchase-workspace view="detail" :order-id="$order->id" />
@endsection
