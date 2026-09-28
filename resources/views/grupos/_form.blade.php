@php($restringido = auth()->user()->sedeRestringida())
<div class="card">
    <div class="card-body p-lg-10">
        <div class="row g-6">
            <div class="col-md-6">
                <label for="nombre" class="required form-label fw-semibold">Nombre del grupo</label>
                <input id="nombre" name="nombre" value="{{ old('nombre', $grupo->nombre) }}" required maxlength="100" placeholder="Por ejemplo: Padres de 3.º Básico"
                       class="form-control form-control-solid @error('nombre') is-invalid @enderror">
                @error('nombre') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-3">
                <label for="tipo" class="required form-label fw-semibold">Tipo de contactos</label>
                <select id="tipo" name="tipo" class="form-select form-select-solid @error('tipo') is-invalid @enderror">
                    @foreach (\App\Models\Grupo::TIPOS as $valor => $etq)
                        <option value="{{ $valor }}" @selected(old('tipo', $grupo->tipo) === $valor)>{{ $etq }}</option>
                    @endforeach
                </select>
                @error('tipo') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-3">
                <label for="sede_id" class="form-label fw-semibold">Sede</label>
                <select id="sede_id" name="sede_id" class="form-select form-select-solid @error('sede_id') is-invalid @enderror" @disabled($restringido)>
                    @unless ($restringido)<option value="">Todas las sedes</option>@endunless
                    @foreach ($sedes as $s)
                        <option value="{{ $s->id }}" @selected((string) old('sede_id', $grupo->sede_id) === (string) $s->id)>{{ $s->nombre }}</option>
                    @endforeach
                </select>
                @error('sede_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-12">
                <label for="descripcion" class="form-label fw-semibold">Descripción</label>
                <input id="descripcion" name="descripcion" value="{{ old('descripcion', $grupo->descripcion) }}" maxlength="255"
                       class="form-control form-control-solid @error('descripcion') is-invalid @enderror" placeholder="Opcional">
                @error('descripcion') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        </div>
        <div class="text-muted fs-7 mt-6">
            Un grupo "Mixto" puede incluir padres de familia y catedráticos. Un grupo con sede solo admite contactos de esa sede.
        </div>
    </div>
    <div class="card-footer d-flex justify-content-end gap-3 py-6">
        <a href="{{ $grupo->exists ? route('grupos.show', $grupo) : route('grupos.index') }}" class="btn btn-light">Cancelar</a>
        <button type="submit" class="btn btn-primary">{{ $grupo->exists ? 'Guardar cambios' : 'Crear grupo' }}</button>
    </div>
</div>
