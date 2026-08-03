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
        $this->middleware('jwt.auth');
        $this->middleware('can:listar_categoria')->only('index');
        $this->middleware('can:registrar_categoria')->only('store');
        $this->middleware('can:editar_categoria')->only('update');
        $this->middleware('can:eliminar_categoria')->only('destroy');
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

    #[OA\Post(
        path: '/api/categorias',
        summary: 'Registrar categoría',
        tags: ['Categorías'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['nombre','estado'],
                properties: [
                    new OA\Property(
                        property: 'nombre',
                        type: 'string',
                        example: 'BEBIDAS'
                    ),
                    new OA\Property(
                        property: 'descripcion',
                        type: 'string',
                        example: 'Categoría de bebidas'
                    ),
                    new OA\Property(
                        property: 'estado',
                        type: 'boolean',
                        example: true
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
                'descripcion'        => ['nullable', 'string', 'max:300'],
                'estado'           => ['required', 'boolean'],
            ], [
                    'nombre.required'              => 'El nombre es obligatorio.',
                    'nombre.max'                   => 'El nombre no debe superar los 100 caracteres.',               
                    'nombre.unique'                => 'Ya existe una categoría con ese nombre.',
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
        path: '/api/categorias/{id}',
        summary: 'Actualizar categoría',
        tags: ['Categorías'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID de la categoría',
                schema: new OA\Schema(type: 'integer')
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['nombre','estado'],
                properties: [
                    new OA\Property(
                        property: 'nombre',
                        type: 'string',
                        example: 'ELECTRONICOS'
                    ),
                    new OA\Property(
                        property: 'descripcion',
                        type: 'string',
                        example: 'Productos electrónicos'
                    ),
                    new OA\Property(
                        property: 'estado',
                        type: 'boolean',
                        example: true
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
        $request->merge([
            'nombre' => strtoupper(trim($request->nombre))
        ]);
        try {
            $request->validate([
                'nombre'           => ['required', 'string', 'max:100', 'unique:categorias,nombre'],
                'descripcion'        => ['nullable', 'string', 'max:300'],
                'estado'           => ['required', 'boolean'],
            ], [
                'nombre.required'              => 'El nombre es obligatorio.',
                'nombre.max'                   => 'El nombre no debe superar los 100 caracteres.',               
                'nombre.unique'                => 'Ya existe una categoría con ese nombre.',
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

        $categoria->nombre           = strtoupper($request->nombre);
        $categoria->descripcion        = $request->descripcion;
        $categoria->estado           = $request->estado;
        $categoria->save();

        return response()->json([
            'codigo'  => 200,
            'mensaje' => 'Categoría actualizada correctamente',
            'categoria'    => CategoriaResource::make($categoria),
        ], 200);
    }
}