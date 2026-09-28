@php
    $esPadre = $config['tipo'] === \App\Models\Contacto::PADRE;
    $gruposMarcados = collect(old('grupos', $contacto->exists ? $contacto->grupos->pluck('id')->all() : []))->map(fn ($id) => (int) $id)->all();
@endphp
<div class="card">
    <div class="card-body p-lg-10">
        <h4 class="fw-bold text-gray-800 mb-5">Datos personales</h4>
        <div class="row g-6 mb-10">
            <div class="col-md-6">
                <label for="nombres" class="required form-label fw-semibold">Nombres</label>
                <input id="nombres" name="nombres" value="{{ old('nombres', $contacto->nombres) }}" required maxlength="100"
                       class="form-control form-control-solid @error('nombres') is-invalid @enderror">
                @error('nombres') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-6">
                <label for="apellidos" class="required form-label fw-semibold">Apellidos</label>
                <input id="apellidos" name="apellidos" value="{{ old('apellidos', $contacto->apellidos) }}" required maxlength="100"
                       class="form-control form-control-solid @error('apellidos') is-invalid @enderror">
                @error('apellidos') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-6">
                <label for="correo" class="form-label fw-semibold">Correo electrónico</label>
                <input id="correo" type="email" name="correo" value="{{ old('correo', $contacto->correo) }}" maxlength="150"
                       class="form-control form-control-solid @error('correo') is-invalid @enderror" placeholder="nombre@correo.com">
                @error('correo') <div class="invalid-feedback">{{ $message }}</div> @enderror
                <div class="form-text">Necesario para enviarle invitaciones.</div>
            </div>
            <div class="col-md-3">
                <label for="telefono" class="form-label fw-semibold">Teléfono</label>
                <input id="telefono" name="telefono" value="{{ old('telefono', $contacto->telefono) }}" placeholder="5874-3210"
                       class="form-control form-control-solid @error('telefono') is-invalid @enderror">
                @error('telefono') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-3">
                <label for="dpi" class="form-label fw-semibold">DPI</label>
                <input id="dpi" name="dpi" value="{{ old('dpi', $contacto->dpi) }}" inputmode="numeric" maxlength="13" placeholder="13 dígitos"
                       class="form-control form-control-solid @error('dpi') is-invalid @enderror">
                @error('dpi') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        </div>

        <h4 class="fw-bold text-gray-800 mb-5">{{ $esPadre ? 'Estudiante' : 'Información académica' }}</h4>
        <div class="row g-6 mb-10">
            <div class="col-md-4">
                <label for="sede_id" class="required form-label fw-semibold">Sede</label>
                <select id="sede_id" name="sede_id" required class="form-select form-select-solid @error('sede_id') is-invalid @enderror">
                    @if ($sedes->count() > 1)<option value="">Seleccione</option>@endif
                    @foreach ($sedes as $s)
                        <option value="{{ $s->id }}" @selected((string) old('sede_id', $contacto->sede_id) === (string) $s->id)>{{ $s->nombre }}</option>
                    @endforeach
                </select>
                @error('sede_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            @if ($esPadre)
                <div class="col-md-5">
                    <label for="estudiante" class="form-label fw-semibold">Nombre del estudiante</label>
                    <input id="estudiante" name="estudiante" value="{{ old('estudiante', $contacto->estudiante) }}" maxlength="150"
                           class="form-control form-control-solid @error('estudiante') is-invalid @enderror">
                    @error('estudiante') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    <div class="form-text">Si tiene varios hijos en el colegio, sepárelos con coma.</div>
                </div>
                <div class="col-md-3">
                    <label for="grado_seccion" class="form-label fw-semibold">Grado y sección</label>
                    <input id="grado_seccion" name="grado_seccion" value="{{ old('grado_seccion', $contacto->grado_seccion) }}" maxlength="60"
                           class="form-control form-control-solid @error('grado_seccion') is-invalid @enderror" placeholder="3.º Básico A">
                    @error('grado_seccion') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            @else
                <div class="col-md-8">
                    <label for="area" class="form-label fw-semibold">Curso o área que imparte</label>
                    <input id="area" name="area" value="{{ old('area', $contacto->area) }}" maxlength="100"
                           class="form-control form-control-solid @error('area') is-invalid @enderror" placeholder="Matemática, Ciencias Naturales…">
                    @error('area') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            @endif
        </div>

        <h4 class="fw-bold text-gray-800 mb-5">Grupos y comunicación</h4>
        <div class="row g-6">
            <div class="col-md-8">
                <label class="form-label fw-semibold">Grupos</label>
                @if ($grupos->isEmpty())
                    <div class="text-muted fs-7">Todavía no hay grupos. <a href="{{ route('grupos.create', ['tipo' => $config['tipo']]) }}">Crear un grupo</a></div>
                @else
                    <div class="d-flex flex-wrap gap-3">
                        @foreach ($grupos as $g)
                            <label class="form-check form-check-sm form-check-custom form-check-solid border border-dashed border-gray-300 rounded px-3 py-2"
                                   @if ($g->sede) data-grupo-sede="{{ $g->sede_id }}" @endif>
                                <input class="form-check-input" type="checkbox" name="grupos[]" value="{{ $g->id }}" @checked(in_array($g->id, $gruposMarcados))>
                                <span class="form-check-label text-gray-700">{{ $g->nombre }}@if ($g->sede) <span class="text-muted fs-8">({{ $g->sede->nombre }})</span>@endif</span>
                            </label>
                        @endforeach
                    </div>
                    <div class="form-text">Solo se guardan los grupos de la sede elegida o de todas las sedes.</div>
                @endif
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Correos</label>
                <input type="hidden" name="acepta_correos" value="0">
                <label class="form-check form-switch form-check-custom form-check-solid">
                    <input class="form-check-input" type="checkbox" name="acepta_correos" value="1" @checked(old('acepta_correos', $contacto->acepta_correos ?? true))>
                    <span class="form-check-label fw-semibold text-gray-700">Acepta recibir invitaciones por correo</span>
                </label>
            </div>
        </div>
    </div>
    <div class="card-footer d-flex flex-wrap justify-content-end gap-3 py-6">
        <a href="{{ route('contactos.index', $segmento) }}" class="btn btn-light">Cancelar</a>
        @unless ($contacto->exists)
            <button type="submit" name="crear_otro" value="1" class="btn btn-light-primary">Guardar y registrar otro</button>
        @endunless
        <button type="submit" class="btn btn-primary">{{ $contacto->exists ? 'Guardar cambios' : 'Guardar' }}</button>
    </div>
</div>

@push('scripts')
    <script>
        // Oculta los grupos que pertenecen a otra sede distinta de la elegida
        (function () {
            var sede = document.getElementById('sede_id');
            function filtrar() {
                document.querySelectorAll('[data-grupo-sede]').forEach(function (el) {
                    var visible = !sede.value || el.dataset.grupoSede === sede.value;
                    el.classList.toggle('d-none', !visible);
                    if (!visible) el.querySelector('input').checked = false;
                });
            }
            if (sede) { sede.addEventListener('change', filtrar); filtrar(); }
        })();
    </script>
@endpush
