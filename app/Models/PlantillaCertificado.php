<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Diseño de un certificado. Los elementos se guardan con coordenadas en porcentaje
 * de la página, de modo que el editor del navegador y el PDF muestran lo mismo.
 */
class PlantillaCertificado extends Model
{
    protected $table = 'plantillas_certificado';

    public const HORIZONTAL = 'horizontal';
    public const VERTICAL = 'vertical';

    /** Medidas de la hoja carta en puntos, según la orientación. */
    public const MEDIDAS = [
        self::HORIZONTAL => ['ancho' => 792, 'alto' => 612],
        self::VERTICAL => ['ancho' => 612, 'alto' => 792],
    ];

    /** Variables que se pueden usar dentro de los textos del diseño. */
    public const VARIABLES = [
        '{nombre}' => 'Nombre de quien recibe el certificado',
        '{empresa}' => 'Empresa o negocio de la persona',
        '{oficio}' => 'Oficio de la persona',
        '{curso}' => 'Nombre de la capacitación',
        '{fecha}' => 'Fecha en que se realizó',
        '{duracion}' => 'Duración, por ejemplo "4 horas"',
        '{facilitador}' => 'Quien impartió la capacitación',
        '{sede}' => 'Sede donde se realizó',
        '{lugar}' => 'Lugar o plataforma virtual',
        '{academia}' => 'Nombre de la academia',
        '{codigo}' => 'Código de verificación del certificado',
    ];

    protected $fillable = ['nombre', 'predeterminada', 'orientacion', 'fondo', 'elementos'];

    protected function casts(): array
    {
        return ['predeterminada' => 'boolean', 'elementos' => 'array'];
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(Evento::class);
    }

    public function medidas(): array
    {
        return self::MEDIDAS[$this->orientacion] ?? self::MEDIDAS[self::HORIZONTAL];
    }

    /** El diseño que se usa cuando la capacitación no tiene uno propio. */
    public static function predeterminada(): ?self
    {
        return static::orderByDesc('predeterminada')->orderBy('id')->first();
    }

    /** Deja esta como la única predeterminada. */
    public function marcarPredeterminada(): void
    {
        static::where('id', '!=', $this->id)->update(['predeterminada' => false]);
        $this->forceFill(['predeterminada' => true])->save();
    }

    /** Reemplaza las variables del texto con los datos reales del evento y la persona. */
    public static function rellenar(?string $texto, Evento $evento, ?Invitacion $invitacion = null): string
    {
        $contacto = $invitacion?->contacto;
        $lugar = $evento->es_virtual ? 'modalidad virtual' : (string) $evento->lugar;

        return strtr((string) $texto, [
            '{nombre}' => $contacto?->nombre_completo ?? 'Nombre del participante',
            '{empresa}' => $contacto?->empresa ?: '',
            '{oficio}' => $contacto?->oficio ?: '',
            '{curso}' => $evento->titulo,
            '{fecha}' => $evento->inicio->translatedFormat('j \d\e F \d\e Y'),
            '{duracion}' => $evento->duracion_texto,
            '{facilitador}' => (string) $evento->facilitador,
            '{sede}' => $evento->sede?->nombre ?? '',
            '{lugar}' => $lugar,
            '{academia}' => config('academia.nombre'),
            '{codigo}' => $invitacion?->codigo_constancia ?? 'XXXX-XXXX',
        ]);
    }

    /**
     * Diseño con el que nace una plantilla nueva: equivale al certificado clásico
     * (marco, logo, título, nombre, texto, dos firmas y código de verificación).
     */
    public static function disenoInicial(): array
    {
        return [
            ['id' => 'marco', 'tipo' => 'marco', 'x' => 3, 'y' => 4, 'ancho' => 94, 'alto' => 92, 'color' => '#D62228', 'grosor' => 3],
            ['id' => 'franja', 'tipo' => 'linea', 'x' => 5, 'y' => 7, 'ancho' => 90, 'color' => '#6D6D6D', 'grosor' => 6],
            ['id' => 'logo', 'tipo' => 'imagen', 'src' => 'logo', 'x' => 35, 'y' => 10, 'ancho' => 30],
            ['id' => 'academia', 'tipo' => 'texto', 'texto' => '{academia} · Sede {sede}', 'x' => 15, 'y' => 30, 'ancho' => 70,
                'fuente' => 12, 'color' => '#78829D', 'alineacion' => 'center', 'mayusculas' => true, 'negrita' => false],
            ['id' => 'titulo', 'tipo' => 'texto', 'texto' => 'CONSTANCIA DE PARTICIPACIÓN', 'x' => 10, 'y' => 35, 'ancho' => 80,
                'fuente' => 30, 'color' => '#2D2D2D', 'alineacion' => 'center', 'negrita' => true],
            ['id' => 'otorga', 'tipo' => 'texto', 'texto' => 'Se otorga la presente a', 'x' => 20, 'y' => 45, 'ancho' => 60,
                'fuente' => 13, 'color' => '#78829D', 'alineacion' => 'center'],
            ['id' => 'nombre', 'tipo' => 'texto', 'texto' => '{nombre}', 'x' => 15, 'y' => 50, 'ancho' => 70,
                'fuente' => 26, 'color' => '#D62228', 'alineacion' => 'center', 'negrita' => true],
            ['id' => 'subrayado', 'tipo' => 'linea', 'x' => 25, 'y' => 58, 'ancho' => 50, 'color' => '#DBDFE9', 'grosor' => 1],
            ['id' => 'texto', 'tipo' => 'texto',
                'texto' => 'por su participación en la capacitación "{curso}", realizada el {fecha} en {lugar}, con una duración de {duracion}.',
                'x' => 15, 'y' => 61, 'ancho' => 70, 'fuente' => 13, 'color' => '#4B5675', 'alineacion' => 'center'],
            ['id' => 'firma1linea', 'tipo' => 'linea', 'x' => 15, 'y' => 82, 'ancho' => 26, 'color' => '#252F4A', 'grosor' => 1],
            ['id' => 'firma1', 'tipo' => 'texto', 'texto' => '{facilitador}', 'x' => 15, 'y' => 83, 'ancho' => 26,
                'fuente' => 11, 'color' => '#4B5675', 'alineacion' => 'center'],
            ['id' => 'firma1rol', 'tipo' => 'texto', 'texto' => 'Facilitador(a)', 'x' => 15, 'y' => 87, 'ancho' => 26,
                'fuente' => 10, 'color' => '#99A1B7', 'alineacion' => 'center'],
            ['id' => 'firma2linea', 'tipo' => 'linea', 'x' => 59, 'y' => 82, 'ancho' => 26, 'color' => '#252F4A', 'grosor' => 1],
            ['id' => 'firma2', 'tipo' => 'texto', 'texto' => 'Dirección', 'x' => 59, 'y' => 83, 'ancho' => 26,
                'fuente' => 11, 'color' => '#4B5675', 'alineacion' => 'center'],
            ['id' => 'firma2rol', 'tipo' => 'texto', 'texto' => '{academia}', 'x' => 59, 'y' => 87, 'ancho' => 26,
                'fuente' => 10, 'color' => '#99A1B7', 'alineacion' => 'center'],
            ['id' => 'codigo', 'tipo' => 'texto', 'texto' => 'Código de verificación: {codigo}', 'x' => 20, 'y' => 93, 'ancho' => 60,
                'fuente' => 9, 'color' => '#99A1B7', 'alineacion' => 'center'],
        ];
    }

    /**
     * Normaliza y limita lo que llega del editor: solo campos conocidos y valores dentro de rango.
     * Evita que un JSON manipulado meta HTML o estilos arbitrarios en el PDF.
     */
    public static function sanear(array $elementos): array
    {
        $limpios = [];

        foreach (array_slice($elementos, 0, 60) as $i => $el) {
            $tipo = in_array($el['tipo'] ?? '', ['texto', 'imagen', 'linea', 'marco'], true) ? $el['tipo'] : null;
            if (! $tipo) {
                continue;
            }

            $limpio = [
                'id' => preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($el['id'] ?? 'el'.$i)) ?: 'el'.$i,
                'tipo' => $tipo,
                'x' => self::numero($el['x'] ?? 0, -10, 110),
                'y' => self::numero($el['y'] ?? 0, -10, 110),
                'ancho' => self::numero($el['ancho'] ?? 30, 1, 120),
            ];

            if ($tipo === 'texto') {
                $limpio += [
                    'texto' => mb_substr(strip_tags((string) ($el['texto'] ?? '')), 0, 600),
                    'fuente' => (int) self::numero($el['fuente'] ?? 13, 6, 72),
                    'color' => self::color($el['color'] ?? '#252F4A'),
                    'alineacion' => in_array($el['alineacion'] ?? '', ['left', 'center', 'right'], true) ? $el['alineacion'] : 'center',
                    'negrita' => (bool) ($el['negrita'] ?? false),
                    'cursiva' => (bool) ($el['cursiva'] ?? false),
                    'mayusculas' => (bool) ($el['mayusculas'] ?? false),
                ];
            } elseif ($tipo === 'imagen') {
                $src = (string) ($el['src'] ?? 'logo');
                // Solo el logo de la academia o un archivo subido desde el editor
                $limpio['src'] = $src === 'logo' ? 'logo' : basename($src);
            } elseif ($tipo === 'linea') {
                $limpio += ['color' => self::color($el['color'] ?? '#252F4A'), 'grosor' => (int) self::numero($el['grosor'] ?? 1, 1, 20)];
            } else { // marco
                $limpio += [
                    'alto' => self::numero($el['alto'] ?? 90, 1, 120),
                    'color' => self::color($el['color'] ?? '#D62228'),
                    'grosor' => (int) self::numero($el['grosor'] ?? 2, 1, 20),
                ];
            }

            if ($tipo === 'imagen') {
                $limpio['alto'] = self::numero($el['alto'] ?? 0, 0, 120);
            }

            $limpios[] = $limpio;
        }

        return $limpios;
    }

    private static function numero(mixed $valor, float $min, float $max): float
    {
        return round(max($min, min($max, (float) $valor)), 2);
    }

    private static function color(mixed $valor): string
    {
        return preg_match('/^#[0-9a-fA-F]{6}$/', (string) $valor) ? strtoupper((string) $valor) : '#252F4A';
    }
}
