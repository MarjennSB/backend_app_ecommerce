<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Transaccion\TransaccionCollection;
use App\Models\Transaccion;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class ApiTransaccionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    #[OA\Get(
        path: '/api/transacciones',
        summary: 'Listar historial de flujo de caja',
        description: 'Obtiene una lista paginada de todas las transacciones de dinero (ingresos por ventas y salidas por compras). Permite filtrar por fechas, buscar por motivo/comprobante y filtrar por tipo de transacción.',
        tags: ['Transacciones'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'search',
                in: 'query',
                required: false,
                description: 'Término de búsqueda (coincidencia parcial en el motivo o número de comprobante)',
                schema: new OA\Schema(type: 'string', example: 'B001')
            ),
            new OA\Parameter(
                name: 'fecha_inicio',
                in: 'query',
                required: false,
                description: 'Fecha de inicio (YYYY-MM-DD)',
                schema: new OA\Schema(type: 'string', format: 'date')
            ),
            new OA\Parameter(
                name: 'fecha_fin',
                in: 'query',
                required: false,
                description: 'Fecha de fin (YYYY-MM-DD)',
                schema: new OA\Schema(type: 'string', format: 'date')
            ),
            new OA\Parameter(
                name: 'tipo_transaccion_id',
                in: 'query',
                required: false,
                description: 'ID del tipo de transacción (ej. filtrar solo ingresos o solo egresos)',
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'per_page',
                in: 'query',
                required: false,
                description: 'Cantidad de registros a mostrar por página (por defecto: 10)',
                schema: new OA\Schema(type: 'integer', default: 10)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Lista de transacciones obtenida correctamente'
            ),
            new OA\Response(
                response: 401,
                description: 'No autorizado'
            )
        ]
    )]
    public function index(Request $request)
    {
        // 1. Capturamos los parámetros de la petición
        $search      = $request->input('search');
        $fechaInicio = $request->input('fecha_inicio');
        $fechaFin    = $request->input('fecha_fin');
        $tipoId      = $request->input('tipo_transaccion_id');
        $per_page    = $request->input('per_page', 10);

        // 2. Construimos la consulta con sus relaciones y filtros
        $transacciones = Transaccion::with(['tipoTransaccion', 'usuario'])
            ->when($search, function ($query) use ($search) {
                $query->where('motivo', 'like', "%{$search}%");
            })
            ->when($fechaInicio, function ($query) use ($fechaInicio) {
                $query->where('created_at', '>=', $fechaInicio . ' 00:00:00');
            })
            ->when($fechaFin, function ($query) use ($fechaFin) {
                $query->where('created_at', '<=', $fechaFin . ' 23:59:59');
            })
            ->when($tipoId, function ($query) use ($tipoId) {
                $query->where('tipo_transaccion_id', $tipoId);
            })
            ->orderByDesc('created_at') // Ordenamos de lo más reciente a lo más antiguo
            ->paginate($per_page);

        // 3. Retornamos la estructura exacta que tu frontend (Vue) espera
        return response()->json([
            'transacciones' => new TransaccionCollection($transacciones->getCollection()),
            'total'         => $transacciones->total(),
            'pagination'    => [
                'total'         => $transacciones->total(),
                'current_page'  => $transacciones->currentPage(),
                'last_page'     => $transacciones->lastPage(),
                'per_page'      => $transacciones->perPage(),
                'total_visible' => min($transacciones->lastPage(), 5),
                'itemsPerPage'  => $transacciones->perPage(),
            ],
        ], 200);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
