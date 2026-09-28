@extends('layouts.app')

@section('title', 'Editar '.$config['singular'])
@section('breadcrumb')
    <li class="breadcrumb-item text-muted"><a href="{{ route('eventos.index', $segmento) }}" class="text-muted text-hover-primary">{{ $config['plural'] }}</a></li>
@endsection

@section('content')
    <form method="POST" action="{{ route('eventos.update', [$segmento, $evento]) }}" novalidate>
        @csrf @method('PUT')
        @include('eventos._form')
    </form>
@endsection
