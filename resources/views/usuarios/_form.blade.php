@php($editando = $usuario->exists)
<div class="card">
    <div class="card-body p-lg-10">
        <div class="row g-6">
            <div class="col-md-6">
                <label for="name" class="required form-label fw-semibold">Nombres</label>
                <input id="name" name="name" value="{{ old('name', $usuario->name) }}" required maxlength="100"
                       class="form-control form-control-solid @error('name') is-invalid @enderror">
                @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-6">
                <label for="apellidos" class="required form-label fw-semibold">Apellidos</label>
                <input id="apellidos" name="apellidos" value="{{ old('apellidos', $usuario->apellidos) }}" required maxlength="100"
                       class="form-control form-control-solid @error('apellidos') is-invalid @enderror">
                @error('apellidos') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-6">
                <label for="email" class="required form-label fw-semibold">Correo electrónico</label>
                <input id="email" type="email" name="email" value="{{ old('email', $usuario->email) }}" required
                       class="form-control form-control-solid @error('email') is-invalid @enderror">
                @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-3">
                <label for="dpi" class="form-label fw-semibold">DPI</label>
                <input id="dpi" name="dpi" value="{{ old('dpi', $usuario->dpi) }}" inputmode="numeric" maxlength="13" placeholder="13 dígitos"
                       class="form-control form-control-solid @error('dpi') is-invalid @enderror">
                @error('dpi') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-3">
                <label for="telefono" class="form-label fw-semibold">Teléfono</label>
                <input id="telefono" name="telefono" value="{{ old('telefono', $usuario->telefono) }}" placeholder="7945-1234"
                       class="form-control form-control-solid @error('telefono') is-invalid @enderror">
                @error('telefono') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-6">
                <label for="rol" class="required form-label fw-semibold">Rol</label>
                <select id="rol" name="rol" required class="form-select form-select-solid @error('rol') is-invalid @enderror">
                    <option value="">Seleccione un rol</option>
                    @foreach ($roles as $r)
                        <option value="{{ $r }}" @selected(old('rol', $usuario->getRoleNames()->first()) === $r)>{{ $r }}</option>
                    @endforeach
                </select>
                @error('rol') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-6">
                <label for="sede_id" class="form-label fw-semibold">Sede</label>
                <select id="sede_id" name="sede_id" class="form-select form-select-solid @error('sede_id') is-invalid @enderror">
                    <option value="">Todas las sedes</option>
                    @foreach ($sedes as $s)
                        <option value="{{ $s->id }}" @selected((string) old('sede_id', $usuario->sede_id) === (string) $s->id)>{{ $s->nombre }}</option>
                    @endforeach
                </select>
                @error('sede_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                <div class="form-text">Si elige una sede, el usuario solo verá los contactos y grupos de esa sede (no aplica al Administrador).</div>
            </div>
            <div class="col-12 d-flex align-items-end">
                <input type="hidden" name="activo" value="0">
                <label class="form-check form-switch form-check-custom form-check-solid mb-3">
                    <input class="form-check-input" type="checkbox" name="activo" value="1" @checked(old('activo', $usuario->activo))>
                    <span class="form-check-label fw-semibold text-gray-700">Usuario activo (puede ingresar al sistema)</span>
                </label>
            </div>

            <div class="col-12"><div class="separator separator-dashed my-2"></div></div>

            <div class="col-md-6">
                <label for="password" class="{{ $editando ? '' : 'required' }} form-label fw-semibold">
                    {{ $editando ? 'Nueva contraseña' : 'Contraseña' }}
                </label>
                <input id="password" type="password" name="password" autocomplete="new-password" {{ $editando ? '' : 'required' }}
                       class="form-control form-control-solid @error('password') is-invalid @enderror">
                @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                <div class="form-text">
                    Mínimo 8 caracteres, con letras y números.{{ $editando ? ' Déjela en blanco para no cambiarla.' : '' }}
                </div>
            </div>
            <div class="col-md-6">
                <label for="password_confirmation" class="{{ $editando ? '' : 'required' }} form-label fw-semibold">Confirmar contraseña</label>
                <input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password"
                       class="form-control form-control-solid">
            </div>
        </div>
    </div>
    <div class="card-footer d-flex justify-content-end gap-3 py-6">
        <a href="{{ route('usuarios.index') }}" class="btn btn-light">Cancelar</a>
        <button type="submit" class="btn btn-primary">{{ $editando ? 'Guardar cambios' : 'Crear usuario' }}</button>
    </div>
</div>
