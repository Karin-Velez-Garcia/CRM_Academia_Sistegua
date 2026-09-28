@php
    $esAdmin = $rol->name === \App\Models\User::ROL_ADMINISTRADOR;
    $marcados = old('permisos', $asignados);
    $nombresAcciones = ['ver' => 'Ver', 'crear' => 'Crear', 'editar' => 'Editar', 'eliminar' => 'Eliminar', 'importar' => 'Importar', 'enviar' => 'Enviar', 'exportar' => 'Exportar', 'asistencia' => 'Asistencia'];
@endphp
<div class="card">
    <div class="card-body p-lg-10">
        @if ($esAdmin)
            <div class="alert bg-light-primary border border-primary border-dashed d-flex align-items-center p-5 mb-8">
                <i class="ki-outline ki-shield-tick fs-2hx text-primary me-4"></i>
                <span class="fw-semibold text-gray-800">El rol Administrador siempre tiene todos los permisos, incluidos los de módulos nuevos. No se puede modificar.</span>
            </div>
        @endif

        <div class="mb-8 mw-500px">
            <label for="name" class="required form-label fw-semibold">Nombre del rol</label>
            <input id="name" name="name" value="{{ old('name', $rol->name) }}" required maxlength="60" @disabled($esAdmin)
                   class="form-control form-control-solid @error('name') is-invalid @enderror" placeholder="Por ejemplo: Director de sede">
            @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <div class="d-flex flex-stack mb-3">
            <h4 class="fw-bold mb-0">Permisos</h4>
            @unless ($esAdmin)
                <label class="form-check form-check-sm form-check-custom form-check-solid">
                    <input class="form-check-input" type="checkbox" id="marcarTodo">
                    <span class="form-check-label fw-semibold">Marcar todo</span>
                </label>
            @endunless
        </div>
        @error('permisos') <div class="text-danger fs-7 mb-3">{{ $message }}</div> @enderror

        <div class="table-responsive">
            <table class="table table-row-dashed align-middle gy-4 tabla-permisos">
                <thead>
                <tr class="text-muted fw-bold fs-7 text-uppercase">
                    <th class="min-w-200px">Módulo</th>
                    @foreach ($acciones as $accion)
                        <th class="text-center min-w-70px">{{ $nombresAcciones[$accion] ?? ucfirst($accion) }}</th>
                    @endforeach
                </tr>
                </thead>
                <tbody class="fw-semibold text-gray-700">
                @foreach ($modulos as $modulo => $accionesModulo)
                    <tr>
                        <td class="text-gray-800">{{ $etiquetas[$modulo] ?? $modulo }}</td>
                        @foreach ($acciones as $accion)
                            <td class="text-center">
                                @if (in_array($accion, $accionesModulo))
                                    @php($permiso = "{$modulo}.{$accion}")
                                    <div class="form-check form-check-custom form-check-solid form-check-sm">
                                        <input class="form-check-input casilla-permiso" type="checkbox" name="permisos[]" value="{{ $permiso }}"
                                               aria-label="{{ $etiquetas[$modulo] ?? $modulo }}: {{ $accion }}"
                                               @checked($esAdmin || in_array($permiso, $marcados)) @disabled($esAdmin)>
                                    </div>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="form-text">Los módulos de sedes, contactos, reuniones y capacitaciones, invitaciones y reportes se habilitarán en las próximas fases; los permisos ya se pueden asignar.</div>
    </div>
    <div class="card-footer d-flex justify-content-end gap-3 py-6">
        <a href="{{ route('roles.index') }}" class="btn btn-light">{{ $esAdmin ? 'Volver' : 'Cancelar' }}</a>
        @unless ($esAdmin)
            <button type="submit" class="btn btn-primary">{{ $rol->exists ? 'Guardar cambios' : 'Crear rol' }}</button>
        @endunless
    </div>
</div>

@push('scripts')
    <script>
        (function () {
            var todo = document.getElementById('marcarTodo');
            if (!todo) return;
            var casillas = document.querySelectorAll('.casilla-permiso');
            var actualizar = function () { todo.checked = Array.prototype.every.call(casillas, function (c) { return c.checked; }); };
            todo.addEventListener('change', function () { casillas.forEach(function (c) { c.checked = todo.checked; }); });
            casillas.forEach(function (c) { c.addEventListener('change', actualizar); });
            actualizar();
        })();
    </script>
@endpush
