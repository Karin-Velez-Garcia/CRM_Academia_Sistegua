@extends('layouts.app')

@section('title', 'Rol: '.$rol->name)
@section('breadcrumb')
    <li class="breadcrumb-item text-muted"><a href="{{ route('roles.index') }}" class="text-muted text-hover-primary">Roles y permisos</a></li>
@endsection

@section('content')
    <form method="POST" action="{{ route('roles.update', $rol) }}" novalidate>
        @csrf @method('PUT')
        @include('roles._form')
    </form>
@endsection
