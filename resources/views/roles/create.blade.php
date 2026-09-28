@extends('layouts.app')

@section('title', 'Nuevo rol')
@section('breadcrumb')
    <li class="breadcrumb-item text-muted"><a href="{{ route('roles.index') }}" class="text-muted text-hover-primary">Roles y permisos</a></li>
@endsection

@section('content')
    <form method="POST" action="{{ route('roles.store') }}" novalidate>
        @csrf
        @include('roles._form')
    </form>
@endsection
