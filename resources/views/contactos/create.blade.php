@extends('layouts.app')

@section('title', $config['nuevo'])
@section('breadcrumb')
    <li class="breadcrumb-item text-muted"><a href="{{ route('contactos.index', $segmento) }}" class="text-muted text-hover-primary">{{ $config['plural'] }}</a></li>
@endsection

@section('content')
    <form method="POST" action="{{ route('contactos.store', $segmento) }}" novalidate>
        @csrf
        @include('contactos._form')
    </form>
@endsection
