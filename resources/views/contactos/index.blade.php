@extends('layouts.app')

@php($esPadre = $config['tipo'] === \App\Models\Contacto::PADRE)

@section('title', $config['plural'])
@section('breadcrumb')
    <li class="breadcrumb-item text-muted">Colegio</li>
@endsection

@section('acciones')
    @can('contactos.importar')
        <a href="{{ route('contactos.importar', $segmento) }}" class="btn btn-sm btn-light-primary">
            <i class="ki-outline ki-file-up fs-2"></i> Cargar desde Excel
        </a>
    @endcan
    <a href="{{ route('contactos.exportar', [$segmento] + request()->only(['buscar', 'sede', 'grupo', 'correo'])) }}" class="btn btn-sm btn-light">
        <i class="ki-outline ki-file-down fs-2"></i> Exportar
    </a>
    @can('contactos.crear')
        <a href="{{ route('contactos.create', $segmento) }}" class="btn btn-sm btn-primary">
            <i class="ki-outline ki-plus fs-2"></i> {{ $config['nuevo'] }}
        </a>
    @endcan
@endsection

@section('content')
    @if ($totalSinCorreo > 0)
        <div class="alert bg-light-warning border border-warning border-dashed d-flex align-items-center p-4 mb-6">
            <i class="ki-outline ki-information-5 fs-2x text-warning me-3"></i>
            <span class="fw-semibold text-gray-800">
                {{ $totalSinCorreo }} {{ $totalSinCorreo === 1 ? 'contacto no tiene' : 'contactos no tienen' }} correo registrado y no podrán recibir invitaciones.
                <a href="{{ route('contactos.index', [$segmento, 'correo' => 'sin']) }}" class="fw-bold">Ver cuáles</a>
            </span>
        </div>
    @endif

    <div class="card">
        <div class="card-header border-0 pt-6">
            <form method="GET" class="d-flex flex-wrap align-items-center gap-3 w-100">
                <div class="d-flex align-items-center position-relative flex-grow-1 mw-350px">
                    <i class="ki-outline ki-magnifier fs-3 position-absolute ms-4"></i>
                    <input type="text" name="buscar" value="{{ request('buscar') }}" class="form-control form-control-solid ps-12"
                           placeholder="{{ $esPadre ? 'Nombre, correo, DPI, estudiante o grado' : 'Nombre, correo, DPI o curso' }}">
                </div>
                @if ($sedes->count() > 1)
                    <select name="sede" class="form-select form-select-solid w-auto">
                        <option value="">Todas las sedes</option>
                        @foreach ($sedes as $s)
                            <option value="{{ $s->id }}" @selected(request('sede') == $s->id)>{{ $s->nombre }}</option>
                        @endforeach
                    </select>
                @endif
                <select name="grupo" class="form-select form-select-solid w-auto mw-250px">
                    <option value="">Todos los grupos</option>
                    @foreach ($grupos as $g)
                        <option value="{{ $g->id }}" @selected(request('grupo') == $g->id)>{{ $g->nombre }}{{ $g->sede ? ' ('.$g->sede->nombre.')' : '' }}</option>
                    @endforeach
                </select>
                <select name="correo" class="form-select form-select-solid w-auto">
                    <option value="">Con y sin correo</option>
                    <option value="con" @selected(request('correo') === 'con')>Con correo</option>
                    <option value="sin" @selected(request('correo') === 'sin')>Sin correo</option>
                </select>
                <button type="submit" class="btn btn-light-primary">Filtrar</button>
                @if (request()->hasAny(['buscar', 'sede', 'grupo', 'correo']))
                    <a href="{{ route('contactos.index', $segmento) }}" class="btn btn-light">Limpiar</a>
                @endif
            </form>
        </div>

        <div class="card-body py-4">
            @can('contactos.editar')
                <form method="POST" action="{{ route('contactos.masivo', $segmento) }}" id="formMasivo"
                      class="d-none align-items-center flex-wrap gap-3 bg-light-primary rounded px-5 py-3 mb-4" data-barra-masiva>
                    @csrf
                    <span class="fw-bold text-gray-800"><span data-contador>0</span> seleccionados</span>
                    <select name="accion" class="form-select form-select-sm w-auto" data-accion-masiva>
                        <option value="agregar_grupo">Agregar a un grupo</option>
                        <option value="quitar_grupo">Quitar de un grupo</option>
                        @can('contactos.eliminar')<option value="eliminar">Eliminar</option>@endcan
                    </select>
                    <select name="grupo_id" class="form-select form-select-sm w-auto mw-250px" data-grupo-masivo>
                        <option value="">Seleccione el grupo</option>
                        @foreach ($grupos as $g)
                            <option value="{{ $g->id }}">{{ $g->nombre }}{{ $g->sede ? ' ('.$g->sede->nombre.')' : '' }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="btn btn-sm btn-primary">Aplicar</button>
                    <a href="{{ route('grupos.create', ['tipo' => $config['tipo']]) }}" class="btn btn-sm btn-link">Crear un grupo nuevo</a>
                </form>
            @endcan

            <div class="table-responsive">
                <table class="table align-middle table-row-dashed fs-6 gy-4">
                    <thead>
                    <tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">
                        @can('contactos.editar')
                            <th class="w-10px pe-2">
                                <div class="form-check form-check-sm form-check-custom form-check-solid">
                                    <input class="form-check-input" type="checkbox" data-seleccionar-todos aria-label="Seleccionar todos">
                                </div>
                            </th>
                        @endcan
                        <th class="min-w-200px">{{ $esPadre ? 'Padre o madre' : 'Catedrático' }}</th>
                        <th>{{ $esPadre ? 'Estudiante' : 'Curso o área' }}</th>
                        <th>Teléfono</th>
                        <th>Sede</th>
                        <th>Grupos</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                    </thead>
                    <tbody class="text-gray-600 fw-semibold">
                    @forelse ($contactos as $c)
                        <tr>
                            @can('contactos.editar')
                                <td>
                                    <div class="form-check form-check-sm form-check-custom form-check-solid">
                                        <input class="form-check-input" type="checkbox" name="ids[]" value="{{ $c->id }}" form="formMasivo"
                                               data-seleccion aria-label="Seleccionar a {{ $c->nombre_completo }}">
                                    </div>
                                </td>
                            @endcan
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="symbol symbol-circle symbol-40px me-3">
                                        <span class="symbol-label bg-light-{{ $esPadre ? 'primary' : 'info' }} text-{{ $esPadre ? 'primary' : 'info' }} fw-bold">{{ $c->iniciales }}</span>
                                    </div>
                                    <div class="d-flex flex-column">
                                        <span class="text-gray-800 fw-bold">{{ $c->nombre_completo }}</span>
                                        @if ($c->correo)
                                            <span class="fs-7">{{ $c->correo }}
                                                @unless ($c->acepta_correos)<span class="badge badge-light-danger ms-1" title="No desea recibir correos">No recibe correos</span>@endunless
                                            </span>
                                        @else
                                            <span class="fs-7 text-warning">Sin correo</span>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td>
                                @if ($esPadre)
                                    <span class="text-gray-800">{{ $c->estudiante ?: '—' }}</span>
                                    @if ($c->grado_seccion)<span class="d-block fs-7">{{ $c->grado_seccion }}</span>@endif
                                @else
                                    {{ $c->area ?: '—' }}
                                @endif
                            </td>
                            <td class="text-nowrap">{{ $c->telefono ?: '—' }}</td>
                            <td>{{ $c->sede->nombre }}</td>
                            <td>
                                @forelse ($c->grupos->take(2) as $g)
                                    <span class="badge badge-light fw-semibold mb-1">{{ $g->nombre }}</span>
                                @empty
                                    <span class="text-muted">—</span>
                                @endforelse
                                @if ($c->grupos->count() > 2)
                                    <span class="badge badge-light-primary" title="{{ $c->grupos->pluck('nombre')->join(', ') }}">+{{ $c->grupos->count() - 2 }}</span>
                                @endif
                            </td>
                            <td class="text-end text-nowrap">
                                @can('contactos.editar')
                                    <a href="{{ route('contactos.edit', [$segmento, $c]) }}" class="btn btn-icon btn-bg-light btn-active-color-primary btn-sm me-1"
                                       data-bs-toggle="tooltip" title="Editar">
                                        <i class="ki-outline ki-pencil fs-3"></i>
                                    </a>
                                @endcan
                                @can('contactos.eliminar')
                                    <form method="POST" action="{{ route('contactos.destroy', [$segmento, $c]) }}" class="d-inline"
                                          data-confirmar="¿Eliminar a {{ $c->nombre_completo }}?">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-icon btn-bg-light btn-active-color-danger btn-sm" data-bs-toggle="tooltip" title="Eliminar">
                                            <i class="ki-outline ki-trash fs-3"></i>
                                        </button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-15">
                                <div class="text-muted mb-4">No se encontraron {{ mb_strtolower($config['plural']) }}.</div>
                                @can('contactos.importar')
                                    <a href="{{ route('contactos.importar', $segmento) }}" class="btn btn-sm btn-light-primary">Cargar desde Excel</a>
                                @endcan
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="d-flex flex-stack flex-wrap gap-3 pt-2">
                <span class="text-muted fs-7">{{ number_format($contactos->total()) }} {{ $contactos->total() === 1 ? 'registro' : 'registros' }}</span>
                {{ $contactos->links() }}
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            var barra = document.querySelector('[data-barra-masiva]');
            if (!barra) return;
            var casillas = document.querySelectorAll('[data-seleccion]');
            var todos = document.querySelector('[data-seleccionar-todos]');
            var contador = barra.querySelector('[data-contador]');
            var accion = barra.querySelector('[data-accion-masiva]');
            var grupo = barra.querySelector('[data-grupo-masivo]');

            function actualizar() {
                var n = Array.prototype.filter.call(casillas, function (c) { return c.checked; }).length;
                contador.textContent = n;
                barra.classList.toggle('d-none', n === 0);
                barra.classList.toggle('d-flex', n > 0);
                if (todos) todos.checked = n > 0 && n === casillas.length;
            }
            casillas.forEach(function (c) { c.addEventListener('change', actualizar); });
            if (todos) todos.addEventListener('change', function () {
                casillas.forEach(function (c) { c.checked = todos.checked; });
                actualizar();
            });
            accion.addEventListener('change', function () { grupo.classList.toggle('d-none', accion.value === 'eliminar'); });
            barra.addEventListener('submit', function (ev) {
                if (accion.value === 'eliminar') {
                    barra.dataset.confirmar = '¿Eliminar los ' + contador.textContent + ' contactos seleccionados? Esta acción no se puede deshacer.';
                } else {
                    delete barra.dataset.confirmar;
                }
            }, true);
        })();
    </script>
@endpush
