<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

use App\Http\Resources\Cliente\ClienteCollection;
use App\Http\Resources\Cliente\ClienteResource;
use App\Models\Cliente;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class ApiClienteController extends Controller
{

    public function __construct()
        {
            $this->middleware('jwt.auth');
            $this->middleware('can:listar_cliente')->only('index');
            $this->middleware('can:registrar_cliente')->only('store');
            $this->middleware('can:editar_cliente')->only('update');
            /* $this->middleware('can:eliminar_cliente')->only('destroy'); */
        }


    #[OA\Get(
    path: "/api/clientes",
    summary: "Listar clientes",
    description: "Obtiene una lista paginada de clientes.",
    tags: ["Clientes"],
    security: [["bearerAuth" => []]],
    parameters: [
        new OA\Parameter(
            name: "search",
            description: "Buscar por nombres, apellidos o documento",
            in: "query",
            required: false,
            schema: new OA\Schema(type: "string")
        ),
        new OA\Parameter(
            name: "per_page",
            description: "Cantidad de registros por página",
            in: "query",
            required: false,
            schema: new OA\Schema(type: "integer", example: 10)
        )
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: "Lista de clientes"
        )
    ]
)]


    public function index(Request $request)
    {
        $search = $request->input('search');
        $per_page = $request->input('per_page', 10);

        $clientes = Cliente::with(['persona'])
            ->when($search, function ($query) use ($search) {
                $query->whereHas('persona', function ($q) use ($search) {
                    $q->where('nombres', 'ilike', "%{$search}%")
                      ->orWhere('apellido_paterno', 'ilike', "%{$search}%")
                      ->orWhere('apellido_materno', 'ilike', "%{$search}%")
                      ->orWhere('numero_documento', 'ilike', "%{$search}%");
                });
            })
            ->orderBy('id', 'desc')
            ->paginate($per_page);

        return response()->json([
            'clientes' => ClienteCollection::make($clientes),
            'total' => $clientes->total(),
            'pagination' => [
                'total' => $clientes->total(),
                'current_page' => $clientes->currentPage(),
                'last_page' => $clientes->lastPage(),
                'per_page' => $clientes->perPage(),
                'total_visible' => $clientes->lastPage() < 5 ? $clientes->lastPage() : 5,
                'itemsPerPage' => $clientes->perPage(),
            ],
        ]);
    }

    #[OA\Post(
    path: "/api/clientes",
    summary: "Crear cliente",
    description: "Registra una persona como cliente.",
    tags: ["Clientes"],
    security: [["bearerAuth" => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: [
                "persona_id",
                "estado"
            ],
            properties: [
                new OA\Property(
                    property: "persona_id",
                    type: "integer",
                    example: 1
                ),
                new OA\Property(
                    property: "estado",
                    type: "boolean",
                    example: true
                )
            ]
        )
    ),
    responses: [
        new OA\Response(
            response: 200,
            description: "Cliente creado correctamente"
        ),
        new OA\Response(
            response: 422,
            description: "Error de validación"
        )
    ]
)]


    public function store(Request $request)
    {
        try {
            $request->validate([
                'persona_id' => [
                    'required',
                    'integer',
                    'exists:personas,id',
                    'unique:clientes,persona_id'
                ],
                'estado' => [
                    'required',
                    'boolean'
                ],
            ], [
                'persona_id.required' => 'La persona es obligatoria.',
                'persona_id.exists' => 'La persona seleccionada no es válida.',
                'persona_id.unique' => 'La persona ya está registrada como cliente.',
                'estado.required' => 'El estado es obligatorio.',
                'estado.boolean' => 'El estado debe ser verdadero o falso.',
            ]);

        } catch (ValidationException $e) {

            return response()->json([
                'mensaje' => 'Errores de validación',
                'errors' => $e->errors(),
            ], 422);
        }


        $cliente = new Cliente();

        $cliente->persona_id = $request->persona_id;
        $cliente->estado = $request->estado;

        $cliente->save();


        return response()->json([
            'codigo' => 200,
            'mensaje' => 'Cliente creado correctamente',
            'cliente' => ClienteResource::make($cliente),
        ], 200);
    }

    #[OA\Put(
    path: "/api/clientes/{id}",
    summary: "Actualizar cliente",
    description: "Actualiza la información de un cliente.",
    tags: ["Clientes"],
    security: [["bearerAuth" => []]],
    parameters: [
        new OA\Parameter(
            name: "id",
            description: "ID del cliente",
            in: "path",
            required: true,
            schema: new OA\Schema(
                type: "integer",
                example: 1
            )
        )
    ],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: [
                "persona_id",
                "estado"
            ],
            properties: [
                new OA\Property(
                    property: "persona_id",
                    type: "integer",
                    example: 1
                ),
                new OA\Property(
                    property: "estado",
                    type: "boolean",
                    example: true
                )
            ]
        )
    ),
    responses: [
        new OA\Response(
            response: 200,
            description: "Cliente actualizado correctamente"
        ),
        new OA\Response(
            response: 422,
            description: "Error de validación"
        )
    ]
)]


    public function update(Request $request, Cliente $cliente)
    {
        try {
            $request->validate([
                'persona_id' => [
                    'required',
                    'integer',
                    'exists:personas,id',
                    'unique:clientes,persona_id,' . $cliente->id
                ],
                'estado' => [
                    'required',
                    'boolean'
                ],
            ], [
                'persona_id.required' => 'La persona es obligatoria.',
                'persona_id.exists' => 'La persona seleccionada no es válida.',
                'persona_id.unique' => 'La persona ya está registrada como cliente.',
                'estado.required' => 'El estado es obligatorio.',
                'estado.boolean' => 'El estado debe ser verdadero o falso.',
            ]);

        } catch (ValidationException $e) {

            return response()->json([
                'mensaje' => 'Errores de validación',
                'errors' => $e->errors(),
            ], 422);
        }


        $cliente->persona_id = $request->persona_id;
        $cliente->estado = $request->estado;

        $cliente->save();


        return response()->json([
            'mensaje' => 'Cliente actualizado correctamente',
            'cliente' => ClienteResource::make($cliente),
        ], 200);
    }
}
