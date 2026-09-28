<?php

namespace App\Services;

use App\Models\Contacto;
use App\Models\Grupo;
use App\Models\Sede;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Imports\HeadingRowFormatter;

/**
 * Importa padres de familia o catedráticos desde un archivo Excel/CSV.
 *
 * Cada fila se valida por separado: las filas con error se reportan y el resto se guarda.
 * Un contacto ya existente (mismo correo o DPI) se actualiza o se omite según el modo.
 */
class ImportadorContactos
{
    public const MAX_FILAS = 5000;

    /** Encabezados aceptados (ya convertidos a minúsculas sin tildes) => campo */
    private const ALIAS = [
        'nombres' => 'nombres', 'nombre' => 'nombres',
        'apellidos' => 'apellidos', 'apellido' => 'apellidos',
        'dpi' => 'dpi', 'cui' => 'dpi',
        'correo' => 'correo', 'correo_electronico' => 'correo', 'email' => 'correo', 'e_mail' => 'correo',
        'telefono' => 'telefono', 'celular' => 'telefono', 'telefono_celular' => 'telefono',
        'sede' => 'sede',
        'estudiante' => 'estudiante', 'nombre_del_estudiante' => 'estudiante', 'alumno' => 'estudiante',
        'grado_seccion' => 'grado_seccion', 'grado_y_seccion' => 'grado_seccion', 'grado' => 'grado_seccion',
        'area' => 'area', 'curso' => 'area', 'curso_o_area' => 'area', 'area_o_curso' => 'area',
        'grupos' => 'grupos', 'grupo' => 'grupos',
    ];

    private array $resultado = [
        'creados' => 0, 'actualizados' => 0, 'omitidos' => 0,
        'errores' => [], 'grupos_creados' => [], 'total' => 0,
    ];

    /** @var Collection<int, Sede> */
    private Collection $sedes;

    /** @var array<string, Grupo> */
    private array $cacheGrupos = [];

    /** correo/dpi ya vistos en este archivo => número de fila */
    private array $vistos = [];

    public function __construct(
        private readonly string $tipo,
        private readonly bool $actualizarExistentes = true,
        private readonly ?int $sedeRestringida = null,
        private readonly ?Grupo $grupoDestino = null,
    ) {
        $this->sedes = Sede::activas()->get();
    }

    public function desdeArchivo(string $ruta): array
    {
        HeadingRowFormatter::default('slug');
        $hojas = Excel::toCollection(new class implements \Maatwebsite\Excel\Concerns\WithHeadingRow {}, $ruta);

        return $this->procesar($hojas->first() ?? collect());
    }

    /**
     * @param  Collection<int, Collection|array>  $filas  Filas con encabezados como llaves.
     */
    public function procesar(Collection $filas): array
    {
        $filas = $filas->map(fn ($f) => $this->normalizarFila(collect($f)->all()));

        if ($filas->isNotEmpty() && ! $this->tieneEncabezadosMinimos($filas->first())) {
            $this->resultado['errores'][] = [
                'fila' => 1,
                'mensaje' => 'No se encontraron las columnas obligatorias (nombres, apellidos, sede). Use la plantilla de ejemplo.',
            ];

            return $this->resultado;
        }

        if ($filas->count() > self::MAX_FILAS) {
            $this->resultado['errores'][] = ['fila' => 1, 'mensaje' => 'El archivo tiene más de '.self::MAX_FILAS.' filas. Divídalo en varios archivos.'];

            return $this->resultado;
        }

        foreach ($filas as $i => $fila) {
            $numero = $i + 2; // la fila 1 son los encabezados
            if ($this->filaVacia($fila)) {
                continue;
            }
            $this->resultado['total']++;

            try {
                DB::transaction(fn () => $this->procesarFila($fila, $numero));
            } catch (FilaInvalida $e) {
                $this->resultado['errores'][] = ['fila' => $numero, 'mensaje' => $e->getMessage()];
            }
        }

        return $this->resultado;
    }

    private function procesarFila(array $fila, int $numero): void
    {
        $datos = [
            'nombres' => $this->texto($fila['nombres'] ?? null, 100),
            'apellidos' => $this->texto($fila['apellidos'] ?? null, 100),
            'dpi' => $this->dpi($fila['dpi'] ?? null),
            'correo' => $this->correo($fila['correo'] ?? null),
            'telefono' => $this->telefono($fila['telefono'] ?? null),
        ];

        if ($this->tipo === Contacto::PADRE) {
            $datos['estudiante'] = $this->texto($fila['estudiante'] ?? null, 150);
            $datos['grado_seccion'] = $this->texto($fila['grado_seccion'] ?? null, 60);
        } else {
            $datos['area'] = $this->texto($fila['area'] ?? null, 100);
        }

        if (! $datos['nombres'] || ! $datos['apellidos']) {
            throw new FilaInvalida('Faltan los nombres o los apellidos.');
        }

        $datos['sede_id'] = $this->sede($fila['sede'] ?? null)->id;

        // Repetidos dentro del mismo archivo
        foreach (['correo', 'dpi'] as $campo) {
            if ($datos[$campo] && isset($this->vistos[$campo.':'.$datos[$campo]])) {
                throw new FilaInvalida("El {$this->etiqueta($campo)} {$datos[$campo]} está repetido (ya aparece en la fila {$this->vistos[$campo.':'.$datos[$campo]]}).");
            }
        }
        foreach (['correo', 'dpi'] as $campo) {
            if ($datos[$campo]) {
                $this->vistos[$campo.':'.$datos[$campo]] = $numero;
            }
        }

        $existente = $this->buscarExistente($datos);

        if ($existente && $this->sedeRestringida && $existente->sede_id !== $this->sedeRestringida) {
            throw new FilaInvalida('Ya existe un contacto con ese correo o DPI en otra sede.');
        }

        if ($existente && ! $this->actualizarExistentes) {
            $this->resultado['omitidos']++;
            $contacto = $existente;
        } elseif ($existente) {
            // Solo se reemplazan los datos que vienen llenos en el archivo
            $existente->fill(array_filter($datos, fn ($v) => $v !== null && $v !== ''));
            $this->validarUnicos($existente);
            $existente->save();
            $this->resultado['actualizados']++;
            $contacto = $existente;
        } else {
            $contacto = new Contacto($datos + ['tipo' => $this->tipo]);
            $this->validarUnicos($contacto);
            $contacto->save();
            $this->resultado['creados']++;
        }

        $grupos = $this->grupos($fila['grupos'] ?? null, $contacto);
        if ($this->grupoDestino && $this->grupoDestino->admite($contacto)) {
            $grupos[] = $this->grupoDestino->id;
        }
        if ($grupos) {
            $contacto->grupos()->syncWithoutDetaching($grupos);
        }
    }

    private function buscarExistente(array $datos): ?Contacto
    {
        if ($datos['correo'] && $c = Contacto::tipo($this->tipo)->where('correo', $datos['correo'])->first()) {
            return $c;
        }
        if ($datos['dpi'] && $c = Contacto::tipo($this->tipo)->where('dpi', $datos['dpi'])->first()) {
            return $c;
        }

        return null;
    }

    /** Evita chocar con otro contacto al actualizar (p. ej. el DPI nuevo ya es de otra persona). */
    private function validarUnicos(Contacto $c): void
    {
        foreach (['correo', 'dpi'] as $campo) {
            if ($c->{$campo} && Contacto::tipo($this->tipo)->where($campo, $c->{$campo})->whereKeyNot($c->id)->exists()) {
                throw new FilaInvalida("El {$this->etiqueta($campo)} {$c->{$campo}} ya pertenece a otro contacto.");
            }
        }
    }

    private function sede(?string $valor): Sede
    {
        if ($this->sedeRestringida) {
            $propia = $this->sedes->firstWhere('id', $this->sedeRestringida);
            if (blank($valor) || Sede::normalizar($valor) === Sede::normalizar($propia->nombre)) {
                return $propia;
            }
            throw new FilaInvalida("Solo puede importar contactos de la sede {$propia->nombre}.");
        }

        if (blank($valor)) {
            throw new FilaInvalida('Falta la sede.');
        }

        return Sede::porNombre($valor, $this->sedes)
            ?? throw new FilaInvalida("La sede \"{$valor}\" no existe. Use: ".$this->sedes->pluck('nombre')->join(', ').'.');
    }

    /** @return list<int> */
    private function grupos(?string $valor, Contacto $contacto): array
    {
        $ids = [];
        foreach (array_filter(array_map('trim', preg_split('/[,;]/', (string) $valor))) as $nombre) {
            $clave = Sede::normalizar($nombre).'|'.$contacto->sede_id;
            $grupo = $this->cacheGrupos[$clave] ??= Grupo::compatibles($this->tipo, $contacto->sede_id)->get()
                ->first(fn (Grupo $g) => Sede::normalizar($g->nombre) === Sede::normalizar($nombre));

            if (! $grupo) {
                $grupo = Grupo::create(['nombre' => Str::limit($nombre, 100, ''), 'tipo' => $this->tipo, 'sede_id' => $contacto->sede_id]);
                $this->cacheGrupos[$clave] = $grupo;
                $this->resultado['grupos_creados'][] = $grupo->nombre.' ('.$contacto->sede->nombre.')';
            }
            $ids[] = $grupo->id;
        }

        return $ids;
    }

    private function normalizarFila(array $fila): array
    {
        $normal = [];
        foreach ($fila as $llave => $valor) {
            $llave = Str::of((string) $llave)->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->value();
            if (isset(self::ALIAS[$llave])) {
                $normal[self::ALIAS[$llave]] = is_string($valor) ? trim($valor) : $valor;
            }
        }

        return $normal;
    }

    private function tieneEncabezadosMinimos(array $fila): bool
    {
        return array_key_exists('nombres', $fila) && array_key_exists('apellidos', $fila)
            && ($this->sedeRestringida || array_key_exists('sede', $fila));
    }

    private function filaVacia(array $fila): bool
    {
        return collect($fila)->every(fn ($v) => blank($v));
    }

    private function texto(mixed $v, int $max): ?string
    {
        $v = Str::squish((string) $v);

        return $v === '' ? null : Str::limit($v, $max, '');
    }

    private function correo(mixed $v): ?string
    {
        $v = Str::lower(trim((string) $v));
        if ($v === '') {
            return null;
        }
        if (! filter_var($v, FILTER_VALIDATE_EMAIL)) {
            throw new FilaInvalida("El correo \"{$v}\" no es válido.");
        }

        return $v;
    }

    private function dpi(mixed $v): ?string
    {
        // Excel puede convertir el DPI a número (p. ej. 2.58474E+12); se reconstruyen los dígitos
        if (is_float($v) || is_int($v)) {
            $v = number_format((float) $v, 0, '', '');
        }
        $digitos = preg_replace('/\D/', '', (string) $v);
        if ($digitos === '') {
            return null;
        }
        if (strlen($digitos) !== 13) {
            throw new FilaInvalida("El DPI \"{$v}\" debe tener 13 dígitos.");
        }

        return $digitos;
    }

    private function telefono(mixed $v): ?string
    {
        $digitos = preg_replace('/\D/', '', (string) $v);
        if ($digitos === '') {
            return null;
        }
        if (strlen($digitos) === 11 && str_starts_with($digitos, '502')) {
            $digitos = substr($digitos, 3);
        }
        if (strlen($digitos) !== 8) {
            throw new FilaInvalida("El teléfono \"{$v}\" debe tener 8 dígitos.");
        }

        return substr($digitos, 0, 4).'-'.substr($digitos, 4);
    }

    private function etiqueta(string $campo): string
    {
        return $campo === 'dpi' ? 'DPI' : 'correo';
    }
}
