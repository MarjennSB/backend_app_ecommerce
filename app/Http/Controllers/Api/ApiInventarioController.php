<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Inventario\InventarioCollection;
use App\Models\Inventario;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class ApiInventarioController extends Controller
{

    public function __construct()
    {
        $this->middleware('jwt.auth');
        $this->middleware('can:listar_inventario')->only('index');
        $this->middleware('can:registrar_inventario')->only('store');
        $this->middleware('can:editar_inventario')->only('update');
        /* $this->middleware('can:eliminar_inventario')->only('destroy'); */
    }

    #[OA\Get(
        path: '/api/inventarios',
        summary: 'Listar movimientos de inventario',
        description: 'Obtiene el historial paginado de movimientos de inventario (Kardex). Permite filtrar por rango de fechas y por producto. Los movimientos se muestran desde el más reciente al más antiguo.',
        tags: ['Inventarios'],
        security: [['bearerAuth' => []]],
        parameters: [
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
                name: 'producto_id',
                in: 'query',
                required: false,
                description: 'ID del producto para consultar únicamente sus movimientos de inventario.',
                schema: new OA\Schema(
                    type: 'integer',
                    example: 1
                )
            ),
            new OA\Parameter(
                name: 'per_page',
                in: 'query',
                required: false,
                description: 'Cantidad de movimientos por página.',
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
                description: 'Historial de movimientos obtenido correctamente'
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
        $fechaInicio = $request->input('fecha_inicio');
        $fechaFin = $request->input('fecha_fin');
        $productoId = $request->input('producto_id');
        $perPage = $request->input('per_page', 10);

        $movimientos = Inventario::with([
            'producto',
            'tipoMovimientoInventario',
            'usuario',
        ])
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
            ->when($productoId, function ($query) use ($productoId) {
                $query->where('producto_id', $productoId);
            })
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return response()->json([
            'inventario' => InventarioCollection::make($movimientos),
            'total' => $movimientos->total(),
            'pagination' => [
                'total' => $movimientos->total(),
                'current_page' => $movimientos->currentPage(),
                'last_page' => $movimientos->lastPage(),
                'per_page' => $movimientos->perPage(),
                'total_visible' => min($movimientos->lastPage(), 5),
                'itemsPerPage' => $movimientos->perPage(),
            ],
        ], 200);
    }
}
