@php($departamentoActual = old('departamento_id', $sede->municipio?->departamento_id))
<div class="card">
    <div class="card-body p-lg-10">
        <div class="row g-6">
            <div class="col-md-6">
                <label for="nombre" class="required form-label fw-semibold">Nombre de la sede</label>
                <input id="nombre" name="nombre" value="{{ old('nombre', $sede->nombre) }}" required maxlength="80" placeholder="Por ejemplo: Sanarate"
                       class="form-control form-control-solid @error('nombre') is-invalid @enderror">
                @error('nombre') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-6 d-flex align-items-end">
                <input type="hidden" name="activa" value="0">
                <label class="form-check form-switch form-check-custom form-check-solid mb-3">
                    <input class="form-check-input" type="checkbox" name="activa" value="1" @checked(old('activa', $sede->activa ?? true))>
                    <span class="form-check-label fw-semibold text-gray-700">Sede activa</span>
                </label>
            </div>
            <div class="col-md-6">
                <label for="departamento_id" class="required form-label fw-semibold">Departamento</label>
                <select id="departamento_id" name="departamento_id" class="form-select form-select-solid" data-municipios-url="{{ url('/api/departamentos') }}">
                    <option value="">Seleccione</option>
                    @foreach ($departamentos as $d)
                        <option value="{{ $d->id }}" @selected((string) $departamentoActual === (string) $d->id)>{{ $d->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6">
                <label for="municipio_id" class="required form-label fw-semibold">Municipio</label>
                <select id="municipio_id" name="municipio_id" class="form-select form-select-solid @error('municipio_id') is-invalid @enderror"
                        data-seleccionado="{{ old('municipio_id', $sede->municipio_id) }}">
                    <option value="">Seleccione primero el departamento</option>
                </select>
                @error('municipio_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-12">
                <label for="direccion" class="required form-label fw-semibold">Dirección</label>
                <input id="direccion" name="direccion" value="{{ old('direccion', $sede->direccion) }}" maxlength="200" required
                       class="form-control form-control-solid @error('direccion') is-invalid @enderror" placeholder="Por ejemplo: 3a. Calle 2-45, Zona 1, Barrio El Centro">
                @error('direccion') <div class="invalid-feedback">{{ $message }}</div> @enderror
                <div class="form-text">Se usa como lugar de las reuniones y capacitaciones presenciales de esta sede y aparece en las invitaciones.</div>
            </div>
            <div class="col-md-6">
                <label for="telefono" class="form-label fw-semibold">Teléfono</label>
                <input id="telefono" name="telefono" value="{{ old('telefono', $sede->telefono) }}" placeholder="7945-1234"
                       class="form-control form-control-solid @error('telefono') is-invalid @enderror">
                @error('telefono') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-6">
                <label for="correo" class="form-label fw-semibold">Correo de la sede</label>
                <input id="correo" type="email" name="correo" value="{{ old('correo', $sede->correo) }}"
                       class="form-control form-control-solid @error('correo') is-invalid @enderror">
                @error('correo') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        </div>
    </div>
    <div class="card-footer d-flex justify-content-end gap-3 py-6">
        <a href="{{ route('sedes.index') }}" class="btn btn-light">Cancelar</a>
        <button type="submit" class="btn btn-primary">{{ $sede->exists ? 'Guardar cambios' : 'Crear sede' }}</button>
    </div>
</div>

@push('scripts')
    <script>
        (function () {
            var dep = document.getElementById('departamento_id');
            var mun = document.getElementById('municipio_id');
            function cargar(seleccionado) {
                mun.innerHTML = '<option value="">' + (dep.value ? 'Cargando…' : 'Seleccione primero el departamento') + '</option>';
                if (!dep.value) return;
                fetch(dep.dataset.municipiosUrl + '/' + dep.value + '/municipios', { headers: { 'Accept': 'application/json' } })
                    .then(function (r) { return r.json(); })
                    .then(function (lista) {
                        mun.innerHTML = '<option value="">Seleccione</option>';
                        lista.forEach(function (m) {
                            var o = new Option(m.nombre, m.id, false, String(m.id) === String(seleccionado));
                            mun.add(o);
                        });
                    })
                    .catch(function () { mun.innerHTML = '<option value="">No se pudieron cargar los municipios</option>'; });
            }
            dep.addEventListener('change', function () { cargar(null); });
            cargar(mun.dataset.seleccionado);
        })();
    </script>
@endpush
