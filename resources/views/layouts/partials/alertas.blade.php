@foreach (['success' => ['success', 'ki-check-circle'], 'error' => ['danger', 'ki-information'], 'info' => ['primary', 'ki-information-5']] as $clave => [$color, $icono])
    @if (session($clave))
        <div class="alert alert-dismissible bg-light-{{ $color }} border border-{{ $color }} border-dashed d-flex align-items-center p-5 mb-8" role="alert">
            <i class="ki-outline {{ $icono }} fs-2hx text-{{ $color }} me-4"></i>
            <div class="d-flex flex-column pe-10">
                <span class="fw-semibold text-gray-800">{{ session($clave) }}</span>
            </div>
            <button type="button" class="position-absolute top-0 end-0 m-2 btn btn-icon" data-bs-dismiss="alert" aria-label="Cerrar">
                <i class="ki-outline ki-cross fs-1 text-{{ $color }}"></i>
            </button>
        </div>
    @endif
@endforeach
