@extends('layouts.app')

@section('title', 'Detalle del producto')
@section('section', 'Productos')
@section('page', 'Detalle del producto')

@section('content')
    <livewire:products.detail :group-id="$product->id" />
@endsection
