@extends('layouts.app')

@section('title', 'Nuevo usuario')
@section('breadcrumb')
    <li class="breadcrumb-item text-muted"><a href="{{ route('usuarios.index') }}" class="text-muted text-hover-primary">Usuarios</a></li>
@endsection

@section('content')
    <form method="POST" action="{{ route('usuarios.store') }}" novalidate>
        @csrf
        @include('usuarios._form')
    </form>
@endsection
