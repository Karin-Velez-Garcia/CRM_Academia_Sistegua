@extends('layouts.app')

@section('title', 'Nueva sede')
@section('breadcrumb')
    <li class="breadcrumb-item text-muted"><a href="{{ route('sedes.index') }}" class="text-muted text-hover-primary">Sedes</a></li>
@endsection

@section('content')
    <form method="POST" action="{{ route('sedes.store') }}" novalidate>
        @csrf
        @include('sedes._form')
    </form>
@endsection
