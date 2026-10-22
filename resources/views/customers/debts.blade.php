@extends('layouts.app')

@section('title', 'Clientas con deuda')
@section('section', 'Administración')
@section('page', 'Clientas con deuda')

@section('content')
    <livewire:customers.debts />
@endsection
