<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DireccionEnvio\DireccionEnvioCollection;
use App\Http\Resources\DireccionEnvio\DireccionEnvioResource;
use App\Http\Resources\Favorito\FavoritoCollection;
use App\Http\Resources\Favorito\FavoritoResource;
use App\Models\DireccionEnvio;
use App\Models\Favorito;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;


class ApiFavoritoController extends Controller
{
    public function __construct()
    {
        $this->middleware('jwt.auth');
        $this->middleware('can:listar_favorito')->only('index');
        $this->middleware('can:registrar_favorito')->only('store');
        $this->middleware('can:editar_favorito')->only('update');
        /* $this->middleware('can:eliminar_favorito')->only('destroy'); */
    }
    
    #[OA\Get(
        path: '/api/favoritos',
        summary: 'Listar favoritos',
        description: 'Obtiene una lista paginada de los productos favoritos del usuario autenticado. Permite buscar por nombre, slug, descripción corta, descripción larga o código de barras del producto.',
        tags: ['Favoritos'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'search',
                in: 'query',
                required: false,
                description: 'Término de búsqueda por nombre, slug, descripción corta, descripción larga o código de barras del producto.',
                schema: new OA\Schema(
                    type: 'string',
                    example: 'laptop'
                )
            ),
            new OA\Parameter(
                name: 'per_page',
                in: 'query',
                required: false,
                description: 'Cantidad de favoritos a mostrar por página. Por defecto: 10.',
                schema: new OA\Schema(
                    type: 'integer',
                    default: 10,
                    example: 15
                )
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Lista de favoritos obtenida correctamente'
            ),
            new OA\Response(
                response: 401,
                description: 'No autorizado - Token inválido o ausente'
            )
        ]
    )]

    public function index(Request $request)
    {
        $search = $request->input('search');
        $per_page = $request->input('per_page', 10);

        $favoritos = Favorito::with([
            'usuario.persona',
            'producto.usuario.persona',
            'producto.categoria',
            'producto.tipoMarca',
            'producto.imagenes',
        ])
            ->where('usuario_id', Auth::id())
            ->when($search, function ($query) use ($search) {
                $query->whereHas('producto', function ($q) use ($search) {
                    $q->where(function ($q) use ($search) {
                        $q->where('nombre', 'ilike', "%{$search}%")
                            ->orWhere('slug', 'ilike', "%{$search}%")
                            ->orWhere('descripcion_corta', 'ilike', "%{$search}%")
                            ->orWhere('descripcion_larga', 'ilike', "%{$search}%")
                            ->orWhere('codigo_barras', 'ilike', "%{$search}%");
                    });
                });
            })
            ->orderByDesc('id')
            ->paginate($per_page);

        return response()->json([
            'favoritos' => FavoritoCollection::make($favoritos),
            'total' => $favoritos->total(),
            'pagination' => [
                'total' => $favoritos->total(),
                'current_page' => $favoritos->currentPage(),
                'last_page' => $favoritos->lastPage(),
                'per_page' => $favoritos->perPage(),
                'total_visible' => min($favoritos->lastPage(), 5),
                'itemsPerPage' => $favoritos->perPage(),
            ],
        ]);
    }

    #[OA\Post(
        path: '/api/favoritos',
        summary: 'Agregar producto a favoritos',
        description: 'Agrega un producto a la lista de favoritos del usuario autenticado. El usuario se obtiene automáticamente desde el token de autenticación. No se permite registrar el mismo producto dos veces para el mismo usuario.',
        tags: ['Favoritos'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'application/json',
                schema: new OA\Schema(
                    type: 'object',
                    required: [
                        'producto_id',
                        'estado'
                    ],
                    properties: [
                        new OA\Property(
                            property: 'producto_id',
                            type: 'integer',
                            description: 'ID del producto que se desea agregar a favoritos.',
                            example: 1
                        ),
                        new OA\Property(
                            property: 'estado',
                            type: 'integer',
                            enum: [0, 1],
                            description: 'Estado del favorito: 1 activo, 0 inactivo.',
                            example: 1
                        )
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Producto agregado a favoritos correctamente'
            ),
            new OA\Response(
                response: 401,
                description: 'No autorizado - Token inválido o ausente'
            ),
            new OA\Response(
                response: 422,
                description: 'Errores de validación o el producto ya se encuentra en favoritos'
            ),
            new OA\Response(
                response: 500,
                description: 'Error interno al agregar el producto a favoritos'
            )
        ]
    )]

    public function store(Request $request)
    {
        try {

            $request->validate([
                'producto_id' => ['required', 'integer', 'exists:productos,id'],
                'estado' => ['required', 'boolean'],
            ], [

                'producto_id.required' => 'El producto es obligatorio.',
                'producto_id.exists' => 'El producto seleccionado no existe.',

                'estado.required' => 'El estado es obligatorio.',
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
            | Verificar si ya existe el favorito
            |--------------------------------------------------------------------------
            */

            $favoritoExiste = Favorito::where('usuario_id', Auth::id())
                ->where('producto_id', $request->producto_id)
                ->exists();

            if ($favoritoExiste) {

                DB::rollBack();

                return response()->json([
                    'mensaje' => 'El producto ya se encuentra en favoritos.',
                ], 422);
            }

            /*
            |--------------------------------------------------------------------------
            | Crear favorito
            |--------------------------------------------------------------------------
            */

            $favorito = new Favorito();

            $favorito->usuario_id = Auth::id();
            $favorito->producto_id = $request->producto_id;
            $favorito->estado = $request->estado;

            $favorito->save();

            DB::commit();

            return response()->json([
                'codigo' => 200,
                'mensaje' => 'Producto agregado a favoritos correctamente',
                'favorito' => FavoritoResource::make(
                    $favorito->load([
                        'usuario.persona',
                        'producto',
                    ])
                ),
            ], 200);

        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'mensaje' => 'Error al agregar producto a favoritos.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    #[OA\Put(
        path: '/api/favoritos/{favorito}',
        summary: 'Actualizar favorito',
        description: 'Actualiza el estado de un favorito del usuario autenticado. Permite activar o desactivar un producto de la lista de favoritos.',
        tags: ['Favoritos'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'favorito',
                in: 'path',
                required: true,
                description: 'ID del favorito a actualizar',
                schema: new OA\Schema(
                    type: 'integer',
                    example: 1
                )
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['estado'],
                properties: [
                    new OA\Property(
                        property: 'estado',
                        type: 'integer',
                        enum: [0, 1],
                        example: 1,
                        description: 'Estado del favorito: 1 activo, 0 inactivo'
                    )
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Favorito actualizado correctamente'
            ),
            new OA\Response(
                response: 401,
                description: 'No autorizado - Token inválido o ausente'
            ),
            new OA\Response(
                response: 404,
                description: 'Favorito no encontrado'
            ),
            new OA\Response(
                response: 422,
                description: 'Errores de validación'
            ),
            new OA\Response(
                response: 500,
                description: 'Error interno al actualizar el favorito'
            )
        ]
    )]

    public function update(Request $request, Favorito $favorito)
    {
        try {

            $request->validate([
                'estado' => ['required', 'boolean'],
            ], [
                'estado.required' => 'El estado es obligatorio.',
                'estado.boolean' => 'El estado debe ser 0 o 1.',
            ]);

        } catch (ValidationException $e) {

            return response()->json([
                'mensaje' => 'Errores de validación',
                'errors' => $e->errors(),
            ], 422);
        }

        try {

            // Verificar que el favorito pertenezca al usuario autenticado
            if ($favorito->usuario_id !== Auth::id()) {

                return response()->json([
                    'mensaje' => 'No tienes permiso para modificar este favorito.',
                ], 403);

            }

            $favorito->estado = $request->estado;

            $favorito->save();

            return response()->json([
                'codigo' => 200,
                'mensaje' => 'Favorito actualizado correctamente',
                'favorito' => FavoritoResource::make(
                    $favorito->load([
                        'usuario.persona',
                        'producto.usuario.persona',
                        'producto.categoria',
                        'producto.tipoMarca',
                        'producto.imagenes',
                    ])
                ),
            ], 200);

        } catch (\Exception $e) {

            return response()->json([
                'mensaje' => 'Error al actualizar favorito.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

}   