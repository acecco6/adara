@extends('layouts.app')

@section('title', $kind === 'supplier' ? 'Proveedores' : 'Clientas')
@section('section', 'Administración')
@section('page', $kind === 'supplier' ? 'Proveedores' : 'Clientas')

@section('content')
    <livewire:contacts.directory :kind="$kind" />
@endsection
