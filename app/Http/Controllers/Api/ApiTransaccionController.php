<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Transaccion\TransaccionCollection;
use App\Models\Transaccion;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class ApiTransaccionController extends Controller
{
    public function __construct()
    {
        $this->middleware('jwt.auth');
        $this->middleware('can:listar_transaccion')->only('index');
        $this->middleware('can:registrar_transaccion')->only('store');
        $this->middleware('can:editar_transaccion')->only('update');
        /* $this->middleware('can:eliminar_transaccion')->only('destroy'); */
    }

    #[OA\Get(
        path: '/api/transacciones',
        summary: 'Listar transacciones',
        description: 'Obtiene el historial paginado de transacciones financieras. Permite buscar por motivo, filtrar por rango de fechas y filtrar por tipo de transacción. Los registros se muestran desde el más reciente al más antiguo.',
        tags: ['Transacciones'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'search',
                in: 'query',
                required: false,
                description: 'Término de búsqueda dentro del motivo de la transacción.',
                schema: new OA\Schema(
                    type: 'string',
                    example: 'Compra'
                )
            ),
            new OA\Parameter(
                name: 'fecha_inicio',
                in: 'query',
                required: false,
                description: 'Fecha inicial del rango de búsqueda. Se considera desde las 00:00:00.',
                schema: new OA\Schema(
                    type: 'string',
                    format: 'date',
                    example: '2026-08-01'
                )
            ),
            new OA\Parameter(
                name: 'fecha_fin',
                in: 'query',
                required: false,
                description: 'Fecha final del rango de búsqueda. Se considera hasta las 23:59:59.',
                schema: new OA\Schema(
                    type: 'string',
                    format: 'date',
                    example: '2026-08-31'
                )
            ),
            new OA\Parameter(
                name: 'tipo_transaccion_id',
                in: 'query',
                required: false,
                description: 'ID del tipo de transacción para filtrar los registros.',
                schema: new OA\Schema(
                    type: 'integer',
                    example: 1
                )
            ),
            new OA\Parameter(
                name: 'per_page',
                in: 'query',
                required: false,
                description: 'Cantidad de transacciones que se mostrarán por página.',
                schema: new OA\Schema(
                    type: 'integer',
                    default: 10,
                    minimum: 1,
                    example: 10
                )
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Transacciones obtenidas correctamente'
            ),
            new OA\Response(
                response: 401,
                description: 'No autorizado - Token inválido o ausente'
            ),
            new OA\Response(
                response: 422,
                description: 'Parámetros de consulta inválidos'
            ),
            new OA\Response(
                response: 500,
                description: 'Error interno del servidor'
            )
        ]
    )]

    public function index(Request $request)
    {
        $search = $request->input('search');
        $fechaInicio = $request->input('fecha_inicio');
        $fechaFin = $request->input('fecha_fin');
        $tipoId = $request->input('tipo_transaccion_id');
        $perPage = $request->input('per_page', 10);

        $transacciones = Transaccion::with([
            'tipoTransaccion',
            'usuario',
        ])
            ->when($search, function ($query) use ($search) {
                $query->where('motivo', 'ilike', "%{$search}%");
            })
            ->when($fechaInicio, function ($query) use ($fechaInicio) {
                $query->where(
                    'created_at',
                    '>=',
                    $fechaInicio . ' 00:00:00'
                );
            })
            ->when($fechaFin, function ($query) use ($fechaFin) {
                $query->where(
                    'created_at',
                    '<=',
                    $fechaFin . ' 23:59:59'
                );
            })
            ->when($tipoId, function ($query) use ($tipoId) {
                $query->where('tipo_transaccion_id', $tipoId);
            })
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return response()->json([
            'transacciones' => TransaccionCollection::make($transacciones),
            'total' => $transacciones->total(),
            'pagination' => [
                'total' => $transacciones->total(),
                'current_page' => $transacciones->currentPage(),
                'last_page' => $transacciones->lastPage(),
                'per_page' => $transacciones->perPage(),
                'total_visible' => min($transacciones->lastPage(), 5),
                'itemsPerPage' => $transacciones->perPage(),
            ],
        ], 200);
    }
}
