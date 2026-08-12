<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Carrito\CarritoCollection;
use App\Http\Resources\Carrito\CarritoResource;
use App\Http\Resources\Categoria\CategoriaCollection;
use App\Http\Resources\Categoria\CategoriaResource;
use App\Models\Carrito;
use App\Models\Categoria;
use App\Models\DetalleCarrito;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;


use OpenApi\Attributes as OA;

class ApiCarritoController extends Controller
{
    public function __construct()
    {
        $this->middleware('jwt.auth');
        $this->middleware('can:listar_carrito')->only('index');
        $this->middleware('can:registrar_carrito')->only('store');
        $this->middleware('can:editar_carrito')->only('update');
        /* $this->middleware('can:eliminar_carrito')->only('destroy'); */
    }

    #[OA\Get(
        path: '/api/carritos',
        summary: 'Listar carritos',
        description: 'Obtiene una lista paginada de todos los carritos registrados, incluyendo la información del usuario asociado, sus detalles y los productos correspondientes.',
        tags: ['Carritos'],
        security: [],
        parameters: [
            new OA\Parameter(
                name: 'per_page',
                in: 'query',
                required: false,
                description: 'Cantidad de carritos a mostrar por página.',
                schema: new OA\Schema(
                    type: 'integer',
                    default: 10,
                    example: 10
                )
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Lista de carritos obtenida correctamente'
            ),
            new OA\Response(
                response: 500,
                description: 'Error interno del servidor'
            )
        ]
    )]

    public function index(Request $request)
    {
        $per_page = $request->input('per_page', 10);

        $carritos = Carrito::with([
            'usuario',
            'detalles.producto',
        ])
            ->orderByDesc('id')
            ->paginate($per_page);

        return response()->json([
            'carritos' => CarritoCollection::make($carritos),
            'total' => $carritos->total(),
            'pagination' => [
                'total' => $carritos->total(),
                'current_page' => $carritos->currentPage(),
                'last_page' => $carritos->lastPage(),
                'per_page' => $carritos->perPage(),
                'total_visible' => min($carritos->lastPage(), 5),
                'itemsPerPage' => $carritos->perPage(),
            ],
        ]);
    }

    #[OA\Post(
        path: '/api/carritos',
        summary: 'Agregar producto al carrito',
        description: 'Agrega un producto al carrito del usuario autenticado. Si el usuario no tiene un carrito, se crea automáticamente. Si el producto ya existe dentro del carrito, se suma la nueva cantidad al detalle existente; de lo contrario, se crea un nuevo detalle.',
        tags: ['Carritos'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: [
                    'producto_id',
                    'cantidad'
                ],
                properties: [
                    new OA\Property(
                        property: 'producto_id',
                        type: 'integer',
                        example: 1,
                        description: 'ID del producto que se desea agregar al carrito'
                    ),
                    new OA\Property(
                        property: 'cantidad',
                        type: 'integer',
                        minimum: 1,
                        example: 2,
                        description: 'Cantidad del producto que se desea agregar'
                    )
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Producto agregado al carrito correctamente'
            ),
            new OA\Response(
                response: 401,
                description: 'No autorizado - Token inválido o ausente'
            ),
            new OA\Response(
                response: 422,
                description: 'Errores de validación'
            ),
            new OA\Response(
                response: 500,
                description: 'Error interno al agregar el producto al carrito'
            )
        ]
    )]

    public function store(Request $request)
    {
        try {

            $request->validate([
                'producto_id' => ['required', 'integer', 'exists:productos,id'],
                'cantidad' => ['required', 'integer', 'min:1'],
            ], [
                'producto_id.required' => 'El producto es obligatorio.',
                'producto_id.exists' => 'El producto seleccionado no existe.',
                'cantidad.required' => 'La cantidad es obligatoria.',
                'cantidad.integer' => 'La cantidad debe ser un número entero.',
                'cantidad.min' => 'La cantidad debe ser mayor a 0.',
            ]);

        } catch (ValidationException $e) {

            return response()->json([
                'mensaje' => 'Errores de validación',
                'errors' => $e->errors(),
            ], 422);
        }

        DB::beginTransaction();

        try {

            /*
            |--------------------------------------------------------------------------
            | Buscar o crear carrito del usuario
            |--------------------------------------------------------------------------
            */

            $carrito = Carrito::where('usuario_id', Auth::id())->first();

            if (!$carrito) {

                $carrito = new Carrito();

                $carrito->usuario_id = Auth::id();
                $carrito->estado = 1;

                $carrito->save();
            }

            /*
            |--------------------------------------------------------------------------
            | Buscar detalle existente
            |--------------------------------------------------------------------------
            */

            $detalle = DetalleCarrito::where('carrito_id', $carrito->id)
                ->where('producto_id', $request->producto_id)
                ->first();

            if ($detalle) {

                $detalle->cantidad += $request->cantidad;
                $detalle->save();

            } else {

                $detalle = new DetalleCarrito();

                $detalle->carrito_id = $carrito->id;
                $detalle->producto_id = $request->producto_id;
                $detalle->cantidad = $request->cantidad;

                $detalle->save();
            }

            DB::commit();

            return response()->json([
                'codigo' => 200,
                'mensaje' => 'Producto agregado al carrito correctamente',
                'carrito' => CarritoResource::make(
                    $carrito->load([
                        'usuario.persona',
                        'detalles.producto.categoria',
                        'detalles.producto.tipoMarca',
                        'detalles.producto.imagenes',
                    ])
                ),
            ], 200);

        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'mensaje' => 'Error al agregar producto al carrito.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    #[OA\Put(
        path: '/api/carritos/{detalleCarrito}',
        summary: 'Actualizar cantidad del carrito',
        description: 'Actualiza la cantidad de un producto dentro del carrito del usuario autenticado. El parámetro de ruta corresponde al ID del detalle del carrito.',
        tags: ['Carritos'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'detalleCarrito',
                in: 'path',
                required: true,
                description: 'ID del detalle del carrito que se desea actualizar',
                schema: new OA\Schema(
                    type: 'integer',
                    example: 1
                )
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['cantidad'],
                properties: [
                    new OA\Property(
                        property: 'cantidad',
                        type: 'integer',
                        minimum: 1,
                        example: 5,
                        description: 'Nueva cantidad del producto dentro del carrito'
                    )
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Cantidad del producto actualizada correctamente'
            ),
            new OA\Response(
                response: 401,
                description: 'No autorizado - Token inválido o ausente'
            ),
            new OA\Response(
                response: 404,
                description: 'El detalle del carrito no existe o no pertenece al usuario'
            ),
            new OA\Response(
                response: 422,
                description: 'Errores de validación'
            ),
            new OA\Response(
                response: 500,
                description: 'Error interno al actualizar la cantidad'
            )
        ]
    )]

    public function update(Request $request, DetalleCarrito $detalleCarrito)
    {
        try {

            $request->validate([
                'cantidad' => ['required', 'integer', 'min:1'],
            ], [
                'cantidad.required' => 'La cantidad es obligatoria.',
                'cantidad.integer' => 'La cantidad debe ser un número entero.',
                'cantidad.min' => 'La cantidad debe ser mayor a 0.',
            ]);

        } catch (ValidationException $e) {

            return response()->json([
                'mensaje' => 'Errores de validación',
                'errors' => $e->errors(),
            ], 422);
        }

        try {

            /*
            |--------------------------------------------------------------------------
            | Verificar que el detalle pertenezca al carrito del usuario
            |--------------------------------------------------------------------------
            */

            $detalleCarrito = DetalleCarrito::where('id', $detalleCarrito->id)
                ->whereHas('carrito', function ($query) {
                    $query->where('usuario_id', Auth::id());
                })
                ->first();

            if (!$detalleCarrito) {

                return response()->json([
                    'mensaje' => 'El detalle del carrito no existe o no pertenece al usuario.',
                ], 404);
            }

            /*
            |--------------------------------------------------------------------------
            | Actualizar cantidad
            |--------------------------------------------------------------------------
            */

            $detalleCarrito->cantidad = $request->cantidad;

            $detalleCarrito->save();

            /*
            |--------------------------------------------------------------------------
            | Obtener carrito actualizado
            |--------------------------------------------------------------------------
            */

            $carrito = Carrito::with([
                'usuario.persona',
                'detalles.producto.categoria',
                'detalles.producto.tipoMarca',
                'detalles.producto.imagenes',
            ])
                ->where('id', $detalleCarrito->carrito_id)
                ->where('usuario_id', Auth::id())
                ->first();

            return response()->json([
                'codigo' => 200,
                'mensaje' => 'Cantidad del producto actualizada correctamente',
                'carrito' => CarritoResource::make($carrito),
            ], 200);

        } catch (\Exception $e) {

            return response()->json([
                'mensaje' => 'Error al actualizar la cantidad del producto.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}