{{-- Ventana de confirmación reutilizable: cualquier <form data-confirmar="mensaje"> la usa --}}
<div class="modal fade" id="modalConfirmar" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-450px">
        <div class="modal-content">
            <div class="modal-body text-center py-10 px-8">
                <i class="ki-outline ki-information-5 fs-5tx text-warning mb-5"></i>
                <div class="fs-5 fw-semibold text-gray-800 mb-8" data-confirmar-texto>¿Está seguro?</div>
                <div class="d-flex flex-center gap-3">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-danger" data-confirmar-aceptar>Sí, continuar</button>
                </div>
            </div>
        </div>
    </div>
</div>
