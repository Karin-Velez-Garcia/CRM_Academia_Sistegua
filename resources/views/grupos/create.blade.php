@extends('layouts.app')

@section('title', 'Nuevo grupo')
@section('breadcrumb')
    <li class="breadcrumb-item text-muted"><a href="{{ route('grupos.index') }}" class="text-muted text-hover-primary">Grupos</a></li>
@endsection

@section('content')
    <form method="POST" action="{{ route('grupos.store') }}" novalidate>
        @csrf
        @include('grupos._form')
    </form>
@endsection
