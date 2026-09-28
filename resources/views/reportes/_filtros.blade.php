{{-- Filtros comunes de los reportes. Requiere $desde, $hasta, $sedes y $rutaExcel. --}}
<form method="GET" class="d-flex flex-wrap align-items-end gap-3">
    <div>
        <label class="form-label fs-7 fw-semibold mb-1">Desde</label>
        <input type="date" name="desde" value="{{ $desde->format('Y-m-d') }}" class="form-control form-control-sm form-control-solid">
    </div>
    <div>
        <label class="form-label fs-7 fw-semibold mb-1">Hasta</label>
        <input type="date" name="hasta" value="{{ $hasta->format('Y-m-d') }}" class="form-control form-control-sm form-control-solid">
    </div>
    @if ($sedes->count() > 1)
        <div>
            <label class="form-label fs-7 fw-semibold mb-1">Sede</label>
            <select name="sede" class="form-select form-select-sm form-select-solid">
                <option value="">Todas</option>
                @foreach ($sedes as $s)
                    <option value="{{ $s->id }}" @selected(request('sede') == $s->id)>{{ $s->nombre }}</option>
                @endforeach
            </select>
        </div>
    @endif
    <div>
        <label class="form-label fs-7 fw-semibold mb-1">Tipo</label>
        <select name="tipo" class="form-select form-select-sm form-select-solid">
            <option value="">Todos</option>
            @foreach ($tipos as $valor => $etq)
                <option value="{{ $valor }}" @selected(request('tipo') === $valor)>{{ $etq }}</option>
            @endforeach
        </select>
    </div>
    {{ $extra ?? '' }}
    <button type="submit" class="btn btn-sm btn-light-primary">Aplicar</button>
    @can('reportes.exportar')
        <a href="{{ $rutaExcel }}" class="btn btn-sm btn-light ms-auto"><i class="ki-outline ki-file-down fs-3"></i> Exportar a Excel</a>
    @endcan
</form>
