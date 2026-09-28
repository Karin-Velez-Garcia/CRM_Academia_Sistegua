@extends('layouts.app')

@section('title', 'Nueva plantilla')
@section('breadcrumb')
    <li class="breadcrumb-item text-muted"><a href="{{ route('plantillas.index') }}" class="text-muted text-hover-primary">Plantillas</a></li>
@endsection

@section('content')
    <form method="POST" action="{{ route('plantillas.store') }}" novalidate>
        @csrf
        @include('plantillas._form')
    </form>
@endsection
