<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Categoria\CategoriaCollection;
use App\Http\Resources\Categoria\CategoriaResource;
use App\Models\Categoria;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;


use OpenApi\Attributes as OA;

class ApiCategoriaController extends Controller
{
    public function __construct()
    {
        $this->middleware('jwt.auth')->except(['index', 'show']);
        $this->middleware('can:listar_categoria')->only('index');
        $this->middleware('can:registrar_categoria')->only('store');
        $this->middleware('can:editar_categoria')->only('update');
        /* $this->middleware('can:eliminar_categoria')->only('destroy'); */
    }

    #[OA\Get(
        path: '/api/categorias',
        summary: 'Listar categorías',
        tags: ['Categorías'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'search',
                in: 'query',
                required: false,
                description: 'Buscar categoría por nombre',
                schema: new OA\Schema(type: 'string')
            ),
            new OA\Parameter(
                name: 'per_page',
                in: 'query',
                required: false,
                description: 'Cantidad de registros por página',
                schema: new OA\Schema(type: 'string', example: 10)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Lista de categorías obtenida correctamente'
            ),
            new OA\Response(
                response: 401,
                description: 'No autorizado'
            )
        ]
    )]

    public function index(Request $request)
    {
        $search   = $request->string('search');
        $per_page = $request->integer('per_page', 10);

        $categorias = Categoria::where('nombre', 'ilike', '%' . $search . '%')
            ->orderBy('id', 'desc')
            ->paginate($per_page);

        return response()->json([
            'categorias' => CategoriaCollection::make($categorias),
            'total'      => $categorias->total(),
            'pagination' => [
                'total'         => $categorias->total(),
                'current_page'  => $categorias->currentPage(),
                'last_page'     => $categorias->lastPage(),
                'per_page'      => $categorias->perPage(),
                'total_visible' => $categorias->lastPage() < 5 ? $categorias->lastPage() : 5,
                'itemsPerPage'  => $categorias->perPage(),
            ],
        ]);
    }

    #[OA\Get(
        path: '/api/categorias/{slug}',
        summary: 'Obtener detalle de una categoría por slug',
        description: 'Obtiene la información de la categoría por su slug (pública).',
        tags: ['Categorías'],
        parameters: [
            new OA\Parameter(
                name: 'slug',
                in: 'path',
                required: true,
                description: 'Slug único de la categoría',
                schema: new OA\Schema(type: 'string', example: 'bebidas')
            )
        ],
        responses: [
            new OA\Response(response: 200, description: 'Categoría encontrada'),
            new OA\Response(response: 404, description: 'Categoría no encontrada')
        ]
    )]
    public function show(Categoria $categoria)
    {
        return CategoriaResource::make($categoria);
    }

    #[OA\Post(
        path: '/api/categorias',
        summary: 'Registrar categoría',
        description: 'Crea una nueva categoría.',
        tags: ['Categorías'],
        security: [['bearerAuth' => []]],

        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: [
                    'nombre',
                    'slug',
                    'estado'
                ],
                properties: [

                    new OA\Property(
                        property: 'nombre',
                        type: 'string',
                        example: 'BEBIDAS',
                        description: 'Nombre de la categoría'
                    ),

                    new OA\Property(
                        property: 'slug',
                        type: 'string',
                        example: 'bebidas',
                        description: 'Slug único de la categoría'
                    ),

                    new OA\Property(
                        property: 'descripcion',
                        type: 'string',
                        nullable: true,
                        example: 'Categoría de bebidas',
                        description: 'Descripción de la categoría'
                    ),

                    new OA\Property(
                        property: 'estado',
                        type: 'integer',
                        enum: [0, 1],
                        example: 1,
                        description: 'Estado de la categoría: 1 activo, 0 inactivo'
                    )
                ]
            )
        ),

        responses: [

            new OA\Response(
                response: 200,
                description: 'Categoría creada correctamente'
            ),

            new OA\Response(
                response: 422,
                description: 'Error de validación'
            ),

            new OA\Response(
                response: 401,
                description: 'No autorizado'
            )
        ]
    )]


    public function store(Request $request)
    {
        // Convertir el nombre a mayúsculas y eliminar 
        // los espacios en blanco al inicio y al final
        $request->merge([
            'nombre' => strtoupper(trim($request->nombre))
        ]);

        try {
            $request->validate([
                'nombre'           => ['required', 'string', 'max:100', 'unique:categorias,nombre'],
                'slug'              => ['required', 'string', 'max:200', 'unique:categorias,slug'],
                'descripcion'        => ['nullable', 'string', 'max:300'],
                'estado'           => ['required', 'boolean'],
            ], [
                    'nombre.required'              => 'El nombre es obligatorio.',
                    'nombre.max'                   => 'El nombre no debe superar los 100 caracteres.',               
                    'nombre.unique'                => 'Ya existe una categoría con ese nombre.',
                    'slug.required'              => 'El slug de la categoría es obligatorio.', 
                    'slug.max'                   => 'El slug no puede superar los 200 caracteres.', 
                    'slug.unique'                   => 'Ese slug ya está en uso.',
                    'descripcion.max'              => 'La descripción no debe superar los 300 caracteres.',
                    'estado.required'              => 'El estado es obligatorio.',
                    'estado.boolean'               => 'El estado debe ser verdadero o falso.',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'mensaje' => 'Errores de validación',
                'errors'  => $e->errors(),
            ], 422);
        }

        $categoria = new Categoria();
        $categoria->nombre           = $request->nombre;
        $categoria->slug             = $request->slug;
        $categoria->descripcion        = $request->descripcion;
        $categoria->estado           = $request->estado;
        $categoria->save();

        return response()->json([
            'codigo'  => 200,
            'mensaje' => 'Categoría creada correctamente',
            'categoria'    => CategoriaResource::make($categoria),
        ], 200);
    }

    #[OA\Put(
        path: '/api/categorias/{categoria}',
        summary: 'Actualizar categoría',
        description: 'Actualiza una categoría existente.',
        tags: ['Categorías'],
        security: [['bearerAuth' => []]],

        parameters: [
            new OA\Parameter(
                name: 'categoria',
                in: 'path',
                required: true,
                description: 'ID de la categoría',
                schema: new OA\Schema(
                    type: 'integer',
                    example: 1
                )
            )
        ],

        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: [
                    'nombre',
                    'slug',
                    'estado'
                ],
                properties: [

                    new OA\Property(
                        property: 'nombre',
                        type: 'string',
                        example: 'ELECTRONICOS',
                        description: 'Nombre de la categoría'
                    ),

                    new OA\Property(
                        property: 'slug',
                        type: 'string',
                        example: 'electronicos',
                        description: 'Slug único de la categoría'
                    ),

                    new OA\Property(
                        property: 'descripcion',
                        type: 'string',
                        nullable: true,
                        example: 'Productos electrónicos',
                        description: 'Descripción de la categoría'
                    ),

                    new OA\Property(
                        property: 'estado',
                        type: 'integer',
                        enum: [0, 1],
                        example: 1,
                        description: 'Estado de la categoría: 1 activo, 0 inactivo'
                    )
                ]
            )
        ),

        responses: [

            new OA\Response(
                response: 200,
                description: 'Categoría actualizada correctamente'
            ),

            new OA\Response(
                response: 404,
                description: 'Categoría no encontrada'
            ),

            new OA\Response(
                response: 422,
                description: 'Error de validación'
            ),

            new OA\Response(
                response: 401,
                description: 'No autorizado'
            )
        ]
    )]

    public function update(Request $request, Categoria $categoria)
    {
        try {
            $request->validate([
                'nombre'      => ['required', 'string', 'max:150'],
                'slug'        => ['required', 'string', 'max:200', 'unique:categorias,slug,' . $categoria->id],
                'descripcion' => ['nullable', 'string', 'max:255'],
                'estado'      => ['required', 'boolean'],
            ], [
                'nombre.required' => 'El nombre de la categoría es obligatorio.',
                'nombre.max'      => 'El nombre de la categoría no puede superar los 150 caracteres.',
                'slug.required'   => 'El slug de la categoría es obligatorio.',
                'slug.max'        => 'El slug no puede superar los 200 caracteres.',
                'slug.unique'     => 'Ese slug ya está en uso.',
                'descripcion.max' => 'La descripción no puede superar los 255 caracteres.',
                'estado.required' => 'El estado es obligatorio.',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'mensaje' => 'Errores de validación',
                'errors'  => $e->errors()
            ], 422);
        }

        $categoria->nombre      = $request->nombre;
        $categoria->slug        = $request->slug;
        $categoria->descripcion = $request->descripcion;
        $categoria->estado      = $request->estado;

        $categoria->save();

        return response()->json([
            'mensaje'   => 'Categoría actualizada correctamente',
            'categoria' => CategoriaResource::make($categoria),
        ], 200);
    }
}