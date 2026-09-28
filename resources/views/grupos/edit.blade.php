@extends('layouts.app')

@section('title', 'Editar grupo')
@section('breadcrumb')
    <li class="breadcrumb-item text-muted"><a href="{{ route('grupos.index') }}" class="text-muted text-hover-primary">Grupos</a></li>
@endsection

@section('content')
    <form method="POST" action="{{ route('grupos.update', $grupo) }}" novalidate>
        @csrf @method('PUT')
        @include('grupos._form')
    </form>
@endsection
