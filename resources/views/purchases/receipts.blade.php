@extends('layouts.app')

@section('title', 'Recepciones de compras')
@section('section', 'Operaciones')
@section('page', 'Recepciones de compras')

@section('content')
    <livewire:purchase-workspace view="receipts" />
@endsection
