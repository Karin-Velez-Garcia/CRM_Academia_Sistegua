<div class="mb-5">
    <label class="required form-label fw-semibold">Asunto</label>
    <input name="asunto" value="{{ $asunto }}" required maxlength="200" class="form-control form-control-solid">
</div>
<div>
    <label class="required form-label fw-semibold">Mensaje</label>
    <textarea name="mensaje" rows="8" required maxlength="5000" class="form-control form-control-solid">{{ $mensaje }}</textarea>
    <div class="d-flex flex-wrap align-items-center gap-2 mt-3">
        <span class="text-muted fs-8">Insertar:</span>
        @foreach (\App\Models\Plantilla::VARIABLES as $var => $desc)
            <button type="button" class="badge badge-light-primary border-0 fs-8" data-variable="{{ $var }}" title="{{ $desc }}">{{ $var }}</button>
        @endforeach
    </div>
    <div class="form-text">Las variables se reemplazan con los datos de cada persona. La fecha, el lugar y los botones para responder se agregan automáticamente debajo del mensaje.</div>
</div>
