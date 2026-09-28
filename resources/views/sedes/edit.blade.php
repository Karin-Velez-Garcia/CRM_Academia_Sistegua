@extends('layouts.app')

@section('title', 'Sede '.$sede->nombre)
@section('breadcrumb')
    <li class="breadcrumb-item text-muted"><a href="{{ route('sedes.index') }}" class="text-muted text-hover-primary">Sedes</a></li>
@endsection

@section('content')
    <form method="POST" action="{{ route('sedes.update', $sede) }}" novalidate>
        @csrf @method('PUT')
        @include('sedes._form')
    </form>
@endsection
