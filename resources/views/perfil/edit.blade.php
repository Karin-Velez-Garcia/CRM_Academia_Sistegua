@extends('layouts.app')

@section('title', 'Mi perfil')

@section('content')
    <div class="row g-5 g-xl-8">
        <div class="col-xl-7">
            <form method="POST" action="{{ route('perfil.update') }}" class="card h-100" novalidate>
                @csrf @method('PUT')
                <div class="card-header border-0 pt-6">
                    <h3 class="card-title fw-bold">Datos personales</h3>
                    <div class="card-toolbar">
                        <span class="badge badge-light-primary fw-bold">{{ $usuario->getRoleNames()->first() ?? 'Sin rol' }}</span>
                    </div>
                </div>
                <div class="card-body pt-2">
                    <div class="row g-6">
                        <div class="col-md-6">
                            <label for="name" class="required form-label fw-semibold">Nombres</label>
                            <input id="name" name="name" value="{{ old('name', $usuario->name) }}" required
                                   class="form-control form-control-solid @error('name') is-invalid @enderror">
                            @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label for="apellidos" class="required form-label fw-semibold">Apellidos</label>
                            <input id="apellidos" name="apellidos" value="{{ old('apellidos', $usuario->apellidos) }}" required
                                   class="form-control form-control-solid @error('apellidos') is-invalid @enderror">
                            @error('apellidos') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12">
                            <label for="email" class="required form-label fw-semibold">Correo electrónico</label>
                            <input id="email" type="email" name="email" value="{{ old('email', $usuario->email) }}" required
                                   class="form-control form-control-solid @error('email') is-invalid @enderror">
                            @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label for="dpi" class="form-label fw-semibold">DPI</label>
                            <input id="dpi" name="dpi" value="{{ old('dpi', $usuario->dpi) }}" inputmode="numeric" maxlength="13"
                                   class="form-control form-control-solid @error('dpi') is-invalid @enderror">
                            @error('dpi') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label for="telefono" class="form-label fw-semibold">Teléfono</label>
                            <input id="telefono" name="telefono" value="{{ old('telefono', $usuario->telefono) }}" placeholder="7945-1234"
                                   class="form-control form-control-solid @error('telefono') is-invalid @enderror">
                            @error('telefono') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex justify-content-end py-6">
                    <button type="submit" class="btn btn-primary">Guardar datos</button>
                </div>
            </form>
        </div>

        <div class="col-xl-5">
            <form method="POST" action="{{ route('perfil.password') }}" class="card h-100" novalidate>
                @csrf @method('PUT')
                <div class="card-header border-0 pt-6">
                    <h3 class="card-title fw-bold">Cambiar contraseña</h3>
                </div>
                <div class="card-body pt-2">
                    <div class="mb-6">
                        <label for="password_actual" class="required form-label fw-semibold">Contraseña actual</label>
                        <input id="password_actual" type="password" name="password_actual" autocomplete="current-password"
                               class="form-control form-control-solid @error('password_actual') is-invalid @enderror">
                        @error('password_actual') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="mb-6">
                        <label for="password" class="required form-label fw-semibold">Nueva contraseña</label>
                        <input id="password" type="password" name="password" autocomplete="new-password"
                               class="form-control form-control-solid @error('password') is-invalid @enderror">
                        @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        <div class="form-text">Mínimo 8 caracteres, con letras y números.</div>
                    </div>
                    <div>
                        <label for="password_confirmation" class="required form-label fw-semibold">Confirmar nueva contraseña</label>
                        <input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password"
                               class="form-control form-control-solid">
                    </div>
                </div>
                <div class="card-footer d-flex justify-content-end py-6">
                    <button type="submit" class="btn btn-light-primary">Cambiar contraseña</button>
                </div>
            </form>
        </div>
    </div>
@endsection
