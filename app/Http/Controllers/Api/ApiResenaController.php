<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Resena\ResenaCollection;
use App\Http\Resources\Resena\ResenaResource;
use App\Models\Resena;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class ApiResenaController extends Controller
{
    public function __construct()
    {
        $this->middleware('jwt.auth');
        $this->middleware('can:listar_resena')->only('index');
        $this->middleware('can:registrar_resena')->only('store');
        $this->middleware('can:editar_resena')->only('update');
        /* $this->middleware('can:eliminar_resena')->only('destroy'); */
    }

    #[OA\Get(
        path: '/api/resenas',
        summary: 'Listar reseñas',
        description: 'Obtiene una lista paginada de reseñas. Permite buscar por comentario, nombre o slug del producto, correo del usuario.',
        tags: ['Reseñas'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'search',
                in: 'query',
                required: false,
                description: 'Término de búsqueda por comentario, nombre del producto, slug, correo del usuario.',
                schema: new OA\Schema(
                    type: 'string',
                    example: 'laptop'
                )
            ),
            new OA\Parameter(
                name: 'per_page',
                in: 'query',
                required: false,
                description: 'Cantidad de registros a mostrar por página (por defecto: 10).',
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
                description: 'Lista de reseñas obtenida correctamente'
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

        $resenas = Resena::with([
            'usuario',
            'producto',
        ])
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {

                    // Buscar en reseñas
                    $q->where('comentario', 'ilike', "%{$search}%")

                        // Buscar por producto
                        ->orWhereHas('producto', function ($producto) use ($search) {
                            $producto->where('nombre', 'ilike', "%{$search}%")
                                ->orWhere('slug', 'ilike', "%{$search}%");
                        })

                        // Buscar por usuario
                        ->orWhereHas('usuario', function ($usuario) use ($search) {
                            $usuario->where('correo', 'ilike', "%{$search}%");
                        });
                });
            })
            ->orderByDesc('id')
            ->paginate($per_page);

        return response()->json([
            'resenas' => ResenaCollection::make($resenas),
            'total' => $resenas->total(),
            'pagination' => [
                'total' => $resenas->total(),
                'current_page' => $resenas->currentPage(),
                'last_page' => $resenas->lastPage(),
                'per_page' => $resenas->perPage(),
                'total_visible' => min($resenas->lastPage(), 5),
                'itemsPerPage' => $resenas->perPage(),
            ],
        ]);
    }

    #[OA\Post(
        path: '/api/resenas',
        summary: 'Registrar reseña',
        description: 'Registra una nueva reseña asociada al usuario autenticado y a un producto. La calificación debe estar entre 1 y 5.',
        tags: ['Reseñas'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'application/json',
                schema: new OA\Schema(
                    type: 'object',
                    required: [
                        'producto_id',
                        'calificacion',
                        'estado'
                    ],
                    properties: [
                        new OA\Property(
                            property: 'producto_id',
                            type: 'integer',
                            description: 'ID del producto que se desea reseñar.',
                            example: 1
                        ),
                        new OA\Property(
                            property: 'calificacion',
                            type: 'integer',
                            minimum: 1,
                            maximum: 5,
                            description: 'Calificación del producto. Valores permitidos del 1 al 5.',
                            example: 5
                        ),
                        new OA\Property(
                            property: 'comentario',
                            type: 'string',
                            nullable: true,
                            description: 'Comentario o descripción de la reseña.',
                            example: 'Excelente producto, cumplió con mis expectativas.'
                        ),
                        new OA\Property(
                            property: 'estado',
                            type: 'integer',
                            enum: [0, 1],
                            description: 'Estado de la reseña: 1 activo, 0 inactivo.',
                            example: 1
                        )
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Reseña creada correctamente'
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
                description: 'Error interno al crear la reseña'
            )
        ]
    )]

    public function store(Request $request)
    {
        try {

            $request->validate([
                'producto_id' => ['required', 'integer', 'exists:productos,id'],
                'calificacion' => ['required', 'integer', 'min:1', 'max:5'],
                'comentario' => ['nullable', 'string'],
                'estado' => ['required', 'boolean'],
            ], [

                'producto_id.required' => 'El producto es obligatorio.',
                'producto_id.exists' => 'El producto seleccionado no existe.',

                'calificacion.required' => 'La calificación es obligatoria.',
                'calificacion.integer' => 'La calificación debe ser un número entero.',
                'calificacion.min' => 'La calificación mínima es 1.',
                'calificacion.max' => 'La calificación máxima es 5.',

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
            | Crear reseña
            |--------------------------------------------------------------------------
            */

            $resena = new Resena();

            $resena->usuario_id = Auth::id();
            $resena->producto_id = $request->producto_id;
            $resena->calificacion = $request->calificacion;
            $resena->comentario = $request->comentario;
            $resena->estado = $request->estado;

            $resena->save();

            DB::commit();

            return response()->json([
                'codigo' => 200,
                'mensaje' => 'Reseña creada correctamente',
                'resena' => ResenaResource::make(
                    $resena->load([
                        'usuario',
                        'producto',
                    ])
                ),
            ], 200);

        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'mensaje' => 'Error al crear reseña.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    #[OA\Put(
        path: '/api/resenas/{resena}',
        summary: 'Actualizar reseña',
        description: 'Actualiza una reseña existente. Permite modificar el producto, la calificación, el comentario y el estado de la reseña. El usuario asociado a la reseña no puede modificarse.',
        tags: ['Reseñas'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'resena',
                in: 'path',
                required: true,
                description: 'ID de la reseña a actualizar',
                schema: new OA\Schema(
                    type: 'integer',
                    example: 1
                )
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'application/json',
                schema: new OA\Schema(
                    type: 'object',
                    required: [
                        'producto_id',
                        'calificacion',
                        'estado'
                    ],
                    properties: [
                        new OA\Property(
                            property: 'producto_id',
                            type: 'integer',
                            description: 'ID del producto asociado a la reseña.',
                            example: 1
                        ),
                        new OA\Property(
                            property: 'calificacion',
                            type: 'integer',
                            minimum: 1,
                            maximum: 5,
                            description: 'Calificación del producto. Valores permitidos del 1 al 5.',
                            example: 4
                        ),
                        new OA\Property(
                            property: 'comentario',
                            type: 'string',
                            nullable: true,
                            description: 'Comentario o descripción de la reseña.',
                            example: 'El producto funciona correctamente.'
                        ),
                        new OA\Property(
                            property: 'estado',
                            type: 'integer',
                            enum: [0, 1],
                            description: 'Estado de la reseña: 1 activo, 0 inactivo.',
                            example: 1
                        )
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Reseña actualizada correctamente'
            ),
            new OA\Response(
                response: 401,
                description: 'No autorizado - Token inválido o ausente'
            ),
            new OA\Response(
                response: 404,
                description: 'Reseña no encontrada'
            ),
            new OA\Response(
                response: 422,
                description: 'Errores de validación'
            ),
            new OA\Response(
                response: 500,
                description: 'Error interno al actualizar la reseña'
            )
        ]
    )]   

    public function update(Request $request, Resena $resena)
    {
        try {

            $request->validate([
                'producto_id' => ['required', 'integer', 'exists:productos,id'],
                'calificacion' => ['required', 'integer', 'min:1', 'max:5'],
                'comentario' => ['nullable', 'string'],
                'estado' => ['required', 'boolean'],
            ], [

                'producto_id.required' => 'El producto es obligatorio.',
                'producto_id.exists' => 'El producto seleccionado no existe.',

                'calificacion.required' => 'La calificación es obligatoria.',
                'calificacion.integer' => 'La calificación debe ser un número entero.',
                'calificacion.min' => 'La calificación mínima es 1.',
                'calificacion.max' => 'La calificación máxima es 5.',

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
            | Actualizar datos de la reseña
            |--------------------------------------------------------------------------
            */

            $resena->producto_id = $request->producto_id;
            $resena->calificacion = $request->calificacion;
            $resena->comentario = $request->comentario;
            $resena->estado = $request->estado;

            $resena->save();

            DB::commit();

            return response()->json([
                'codigo' => 200,
                'mensaje' => 'Reseña actualizada correctamente',
                'resena' => ResenaResource::make(
                    $resena->load([
                        'usuario',
                        'producto',
                    ])
                ),
            ], 200);

        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'mensaje' => 'Error al actualizar reseña.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}    