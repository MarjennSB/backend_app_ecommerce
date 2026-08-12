<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

use App\Models\Departamento;
use App\Models\Distrito;
use App\Models\Genero;
use App\Models\Provincia;
use App\Models\TipoDocumentoIdentidad;


use App\Models\TipoDocumentoComprobante;
use App\Models\TipoMetodoPago;
use App\Models\TipoMovimientoInventario;
use App\Models\TipoTransaccion;


class ApiMasterController extends Controller
{
    public function __construct()
    {
        $this->middleware('jwt.auth');
        $this->middleware('can:listar_master')->only('index');
        $this->middleware('can:registrar_master')->only('store');
        $this->middleware('can:editar_master')->only('update');
        /* $this->middleware('can:eliminar_master')->only('destroy'); */
    }
    
    public function selectTipoDocumento()
    {
        $tipodocumentoidentidad = TipoDocumentoIdentidad::where('estado', true)
            ->orderBy('nombre', 'asc')
            ->get(['id', 'nombre', 'siglas']);

        return response()->json(['tipodocumentoidentidad' => $tipodocumentoidentidad]);
    }

    public function selectGenero()
    {
        $generos = Genero::where('estado', true)
            ->orderBy('nombre', 'asc')
            ->get(['id', 'nombre']); // Usando 'nombre' según tu migración

        return response()->json(['generos' => $generos]);
    }

    public function selectDepartamento()
    {
        $departamentos = Departamento::where('estado', true)
            ->orderBy('descripcion', 'asc')
            ->get(['id', 'descripcion']);

        return response()->json(['departamentos' => $departamentos]);
    }

    // Carga TODAS las provincias de la base de datos
    public function selectProvincia()
    {
        $provincias = Provincia::where('estado', true)
            ->orderBy('descripcion', 'asc')
            ->get(['id', 'descripcion', 'departamento_id']); // Agregué departamento_id por si el frontend hace el filtro internamente

        return response()->json(['provincias' => $provincias]);
    }

    // Carga TODOS los distritos de la base de datos
    public function selectDistrito()
    {
        $distritos = Distrito::where('estado', true)
            ->orderBy('descripcion', 'asc')
            ->get(['id', 'descripcion', 'provincia_id']);

        return response()->json(['distritos' => $distritos]);
    }

    public function selectTipoDocumentoComprobante()
    {
        $tipodocumentocomprobante = TipoDocumentoComprobante::where('estado', true)
            ->orderBy('nombre', 'asc')
            ->get(['id', 'nombre', 'siglas']);

        return response()->json(['tipodocumentocomprobante' => $tipodocumentocomprobante]);
    }

    // Carga TODOS los tipos de movimiento de inventario
    public function selectTipoMovimientoInventario()
    {
        $tipomovimientoinventario = TipoMovimientoInventario::where('estado', true)
            ->orderBy('nombre', 'asc')
            ->get(['id', 'nombre', 'siglas']);

        return response()->json(['tipomovimientoinventario' => $tipomovimientoinventario]);
    }

    // Carga TODOS los tipos de transacción
    public function selectTipoTransaccion()
    {
        $tipotransaccion = TipoTransaccion::where('estado', true)
            ->orderBy('nombre', 'asc')
            ->get(['id', 'nombre', 'siglas']);

        return response()->json(['tipotransaccion' => $tipotransaccion]);
    }

    public function selectTipoMetodoPago()
    {
        $tipometodopago = TipoMetodoPago::where('estado', true)
            ->orderBy('nombre', 'asc')
            ->get(['id', 'nombre', 'siglas']);

        return response()->json(['tipometodopago' => $tipometodopago]);
    }
}