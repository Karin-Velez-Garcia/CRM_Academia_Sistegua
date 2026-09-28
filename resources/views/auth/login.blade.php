<!DOCTYPE html>
<html lang="es" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Iniciar sesión · {{ config('app.name') }}</title>
    <link rel="icon" type="image/png" href="{{ asset('assets/media/logos/esteca-icon.png') }}">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Inter:300,400,500,600,700">
    <link rel="stylesheet" href="{{ asset('assets/plugins/keenicons/outline/style.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/style.bundle.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}">
    <script>
        try { const t = localStorage.getItem('tema'); if (t) document.documentElement.setAttribute('data-bs-theme', t); } catch (e) {}
    </script>
</head>
<body class="app-blank">
<div class="d-flex flex-column flex-root" id="kt_app_root">
    <div class="d-flex flex-column flex-lg-row flex-column-fluid">

        <div class="d-flex flex-column flex-lg-row-fluid w-lg-50 p-10 order-2 order-lg-1">
            <div class="d-flex flex-center flex-column flex-lg-row-fluid">
                <div class="w-100 w-sm-400px p-10">
                    <form method="POST" action="{{ route('login.store') }}" class="form w-100" novalidate>
                        @csrf
                        <div class="text-center mb-11">
                            <h1 class="text-gray-900 fw-bolder mb-3">Iniciar sesión</h1>
                            <div class="text-gray-500 fw-semibold fs-6">{{ config('colegio.nombre') }}</div>
                        </div>

                        @if ($errors->any())
                            <div class="alert bg-light-danger border border-danger border-dashed d-flex align-items-center p-4 mb-8">
                                <i class="ki-outline ki-information fs-2hx text-danger me-3"></i>
                                <span class="fw-semibold text-gray-800">{{ $errors->first() }}</span>
                            </div>
                        @endif

                        <div class="fv-row mb-8">
                            <label for="email" class="form-label fw-semibold text-gray-900">Correo electrónico</label>
                            <input id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="username" autofocus required
                                   class="form-control bg-transparent @error('email') is-invalid @enderror" placeholder="usuario@correo.com">
                        </div>

                        <div class="fv-row mb-3">
                            <label for="password" class="form-label fw-semibold text-gray-900">Contraseña</label>
                            <div class="position-relative">
                                <input id="password" type="password" name="password" autocomplete="current-password" required
                                       class="form-control bg-transparent pe-12" placeholder="••••••••">
                                <button type="button" class="btn btn-sm btn-icon position-absolute translate-middle-y top-50 end-0 me-2"
                                        data-ver-password="password" aria-label="Mostrar contraseña">
                                    <i class="ki-outline ki-eye fs-2"></i>
                                </button>
                            </div>
                        </div>

                        <div class="d-flex flex-stack flex-wrap gap-3 fs-base fw-semibold mb-8">
                            <label class="form-check form-check-sm form-check-custom form-check-solid">
                                <input class="form-check-input" type="checkbox" name="recordar" value="1">
                                <span class="form-check-label text-gray-700">Mantener la sesión iniciada</span>
                            </label>
                        </div>

                        <div class="d-grid mb-10">
                            <button type="submit" class="btn btn-primary">Ingresar</button>
                        </div>
                    </form>
                </div>
            </div>
            <div class="d-flex flex-center flex-wrap px-5">
                <span class="text-muted fw-semibold fs-7">{{ config('colegio.nombre') }} © {{ now()->year }} · {{ implode(' · ', config('colegio.sedes')) }}</span>
            </div>
        </div>

        <div class="d-flex flex-lg-row-fluid w-lg-50 auth-aside order-1 order-lg-2"
             style="background-image: url('{{ asset('assets/media/auth-bg.png') }}')">
            <div class="d-flex flex-column flex-center py-10 py-lg-15 px-5 px-md-15 w-100">
                <div class="bg-white rounded-4 shadow-sm p-5 p-lg-7 mb-8 mb-lg-10">
                    <img alt="{{ config('colegio.nombre') }}" src="{{ asset('assets/media/logos/esteca-logo.png') }}" class="logo-login">
                </div>
                <h1 class="text-white fs-2qx fw-bolder text-center mb-4">{{ config('colegio.nombre') }}</h1>
                <div class="text-white fs-base text-center opacity-75 mb-6">
                    Reuniones con padres de familia y capacitaciones para catedráticos.
                </div>
                <div class="d-flex flex-wrap flex-center gap-2">
                    @foreach (config('colegio.sedes') as $sede)
                        <span class="badge bg-white bg-opacity-20 text-white fw-semibold fs-7 px-4 py-2">Sede {{ $sede }}</span>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
<script src="{{ asset('assets/js/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('assets/js/app.js') }}"></script>
</body>
</html>
