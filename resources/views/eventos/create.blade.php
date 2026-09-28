@extends('layouts.app')

@section('title', $config['nuevo'])
@section('breadcrumb')
    <li class="breadcrumb-item text-muted"><a href="{{ route('eventos.index', $segmento) }}" class="text-muted text-hover-primary">{{ $config['plural'] }}</a></li>
@endsection

@section('content')
    @isset($duplicado)
        <div class="alert bg-light-primary border border-primary border-dashed d-flex align-items-center p-4 mb-6">
            <i class="ki-outline ki-copy fs-2x text-primary me-3"></i>
            <span class="fw-semibold text-gray-800">Copia de "{{ $duplicado->titulo }}". Elija la nueva fecha y revise los datos antes de guardar.</span>
        </div>
    @endisset
    <form method="POST" action="{{ route('eventos.store', $segmento) }}" novalidate>
        @csrf
        @include('eventos._form')
    </form>
@endsection
