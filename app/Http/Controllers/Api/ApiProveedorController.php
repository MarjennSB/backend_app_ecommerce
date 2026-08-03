<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Proveedor\ProveedorCollection;
use App\Http\Resources\Proveedor\ProveedorResource;
use App\Models\Proveedor;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class ApiProveedorController extends Controller
{

public function __construct()
    {
        $this->middleware('jwt.auth');
        $this->middleware('can:listar_proveedor')->only('index');
        $this->middleware('can:registrar_proveedor')->only('store');
        $this->middleware('can:editar_proveedor')->only('update');
        $this->middleware('can:eliminar_proveedor')->only('destroy');
    }


    #[OA\Get(
    path: "/api/proveedores",
    summary: "Listar proveedores",
    description: "Obtiene una lista paginada de proveedores.",
    tags: ["Proveedores"],
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
            description: "Lista de proveedores"
        )
    ]
)]


    public function index(Request $request)
    {
        $search = $request->input('search');
        $per_page = $request->input('per_page', 10);

        $proveedores = Proveedor::with(['persona'])
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
            'proveedores' => ProveedorCollection::make($proveedores),
            'total' => $proveedores->total(),
            'pagination' => [
                'total' => $proveedores->total(),
                'current_page' => $proveedores->currentPage(),
                'last_page' => $proveedores->lastPage(),
                'per_page' => $proveedores->perPage(),
                'total_visible' => $proveedores->lastPage() < 5 ? $proveedores->lastPage() : 5,
                'itemsPerPage' => $proveedores->perPage(),
            ],
        ]);
    }

    #[OA\Post(
    path: "/api/proveedores",
    summary: "Crear proveedor",
    description: "Registra una persona como proveedor.",
    tags: ["Proveedores"],
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
            description: "Proveedor creado correctamente"
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
                    'unique:proveedores,persona_id'
                ],
                'estado' => [
                    'required',
                    'boolean'
                ],
            ], [
                'persona_id.required' => 'La persona es obligatoria.',
                'persona_id.exists' => 'La persona seleccionada no es válida.',
                'persona_id.unique' => 'La persona ya está registrada como proveedor.',
                'estado.required' => 'El estado es obligatorio.',
                'estado.boolean' => 'El estado debe ser verdadero o falso.',
            ]);

        } catch (ValidationException $e) {

            return response()->json([
                'mensaje' => 'Errores de validación',
                'errors' => $e->errors(),
            ], 422);
        }


        $proveedor = new Proveedor();

        $proveedor->persona_id = $request->persona_id;
        $proveedor->estado = $request->estado;

        $proveedor->save();


        return response()->json([
            'codigo' => 200,
            'mensaje' => 'Proveedor creado correctamente',
            'proveedor' => ProveedorResource::make($proveedor),
        ], 200);
    }

    #[OA\Put(
    path: "/api/proveedores/{id}",
    summary: "Actualizar proveedor",
    description: "Actualiza la información de un proveedor.",
    tags: ["Proveedores"],
    security: [["bearerAuth" => []]],
    parameters: [
        new OA\Parameter(
            name: "id",
            description: "ID del proveedor",
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
            description: "Proveedor actualizado correctamente"
        ),
        new OA\Response(
            response: 422,
            description: "Error de validación"
        )
    ]
)]


    public function update(Request $request, Proveedor $proveedor)
    {
        try {
            $request->validate([
                'persona_id' => [
                    'required',
                    'integer',
                    'exists:personas,id',
                    'unique:proveedores,persona_id,' . $proveedor->id
                ],
                'estado' => [
                    'required',
                    'boolean'
                ],
            ], [
                'persona_id.required' => 'La persona es obligatoria.',
                'persona_id.exists' => 'La persona seleccionada no es válida.',
                'persona_id.unique' => 'La persona ya está registrada como proveedor.',
                'estado.required' => 'El estado es obligatorio.',
                'estado.boolean' => 'El estado debe ser verdadero o falso.',
            ]);

        } catch (ValidationException $e) {

            return response()->json([
                'mensaje' => 'Errores de validación',
                'errors' => $e->errors(),
            ], 422);
        }


        $proveedor->persona_id = $request->persona_id;
        $proveedor->estado = $request->estado;

        $proveedor->save();


        return response()->json([
            'mensaje' => 'Proveedor actualizado correctamente',
            'proveedor' => ProveedorResource::make($proveedor),
        ], 200);
    }
}
