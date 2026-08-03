<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Inventario\InventarioCollection;
use App\Http\Resources\Inventario\InventarioResource;
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
        $this->middleware('can:eliminar_inventario')->only('destroy');
    }
    #[OA\Get(
        path: '/api/inventarios',
        summary: 'Historial de movimientos de inventario',
        description: 'Obtiene el registro histórico (Kardex) de todos los ingresos y salidas de productos en el e-commerce. Permite filtrar por rango de fechas y producto específico. Este módulo es de solo lectura.',
        tags: ['Inventarios'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'fecha_inicio',
                in: 'query',
                required: false,
                description: 'Fecha de inicio (YYYY-MM-DD)',
                schema: new OA\Schema(type: 'string', format: 'date', example: '2026-08-01')
            ),
            new OA\Parameter(
                name: 'fecha_fin',
                in: 'query',
                required: false,
                description: 'Fecha de fin (YYYY-MM-DD)',
                schema: new OA\Schema(type: 'string', format: 'date', example: '2026-08-31')
            ),
            new OA\Parameter(
                name: 'producto_id',
                in: 'query',
                required: false,
                description: 'ID de un producto específico para ver solo su historial',
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
            new OA\Parameter(
                name: 'per_page',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', default: 10)
            )
        ],
        responses: [
            new OA\Response(response: 200, description: 'Historial obtenido correctamente')
        ]
    )]

    public function index(Request $request)
    {
        $fechaInicio = $request->input('fecha_inicio');
        $fechaFin    = $request->input('fecha_fin');
        $productoId  = $request->input('producto_id');
        $perPage     = $request->input('per_page', 10);

        // Cargamos las relaciones para no sobrecargar la base de datos
        $movimientos = Inventario::with(['producto', 'tipoMovimientoInventario', 'usuario'])
            ->when($fechaInicio, function ($query) use ($fechaInicio) {
                // Aseguramos que tome desde las 00:00:00 de ese día
                $query->where('created_at', '>=', $fechaInicio . ' 00:00:00');
            })
            ->when($fechaFin, function ($query) use ($fechaFin) {
                // Aseguramos que tome hasta las 23:59:59 de ese día
                $query->where('created_at', '<=', $fechaFin . ' 23:59:59');
            })
            ->when($productoId, function ($query) use ($productoId) {
                $query->where('producto_id', $productoId);
            })
            // Ordenamos del más reciente al más antiguo
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return response()->json([
            'inventario' => new InventarioCollection($movimientos->getCollection()),
            'pagination' => [
                'total'         => $movimientos->total(),
                'current_page'  => $movimientos->currentPage(),
                'last_page'     => $movimientos->lastPage(),
                'per_page'      => $movimientos->perPage(),
            ],
        ], 200);
    }
}
