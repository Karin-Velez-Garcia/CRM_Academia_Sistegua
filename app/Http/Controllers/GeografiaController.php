<?php

namespace App\Http\Controllers;

use App\Models\Departamento;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GeografiaController extends Controller
{
    public function index(Request $request): View
    {
        $buscar = $request->input('buscar');

        $departamentos = Departamento::withCount('municipios')
            ->when($buscar, fn ($q) => $q->where(fn ($w) => $w->where('nombre', 'like', "%{$buscar}%")
                ->orWhereHas('municipios', fn ($m) => $m->where('nombre', 'like', "%{$buscar}%"))))
            ->when($request->filled('region'), fn ($q) => $q->where('region', $request->input('region')))
            ->orderBy('codigo')
            ->get();

        return view('geografia.index', [
            'departamentos' => $departamentos,
            'regiones' => Departamento::orderBy('region')->distinct()->pluck('region'),
        ]);
    }

    public function show(Departamento $departamento): View
    {
        return view('geografia.show', [
            'departamento' => $departamento->load('municipios'),
        ]);
    }

    public function municipios(Departamento $departamento): JsonResponse
    {
        return response()->json(
            $departamento->municipios()->get(['id', 'codigo', 'nombre'])
        );
    }
}
