<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Persona\PersonaCollection;
use App\Http\Resources\Persona\PersonaResource;
use App\Models\Persona;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class ApiPersonaController extends Controller
{
    public function __construct()
    {
        // Todas las rutas de Persona (proveedores/entidades) requieren autenticación
        $this->middleware('jwt.auth');
        $this->middleware('can:listar_persona')->only('index');
        $this->middleware('can:registrar_persona')->only('store');
        $this->middleware('can:editar_persona')->only('update');
        /* $this->middleware('can:eliminar_persona')->only('destroy'); */
    }

    #[OA\Get(
        path: '/api/personas',
        summary: 'Listar personas (Proveedores/Entidades)',
        description: 'Obtiene una lista paginada de personas. Permite buscar por nombres, apellidos o número de documento.',
        tags: ['Personas'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'search',
                in: 'query',
                required: false,
                description: 'Término de búsqueda (nombres, apellidos, número de documento).',
                schema: new OA\Schema(type: 'string', example: 'Juan')
            ),
            new OA\Parameter(
                name: 'per_page',
                in: 'query',
                required: false,
                description: 'Cantidad de registros por página.',
                schema: new OA\Schema(type: 'integer', default: 10, example: 10)
            )
        ],
        responses: [
            new OA\Response(response: 200, description: 'Personas obtenidas correctamente'),
            new OA\Response(response: 401, description: 'No autorizado')
        ]
    )]
    public function index(Request $request)
    {
        $search = $request->input('search');
        $per_page = $request->input('per_page', 10);

        $personas = Persona::with(['tipoDocumentoIdentidad', 'departamento', 'provincia', 'distrito'])
            ->when($search, function ($query) use ($search) {
                $query->where('nombres', 'ilike', "%{$search}%")
                    ->orWhere('apellido_paterno', 'ilike', "%{$search}%")
                    ->orWhere('apellido_materno', 'ilike', "%{$search}%")
                    ->orWhere('numero_documento', 'ilike', "%{$search}%")
                    ->orWhere('correo', 'ilike', "%{$search}%");
            })
            ->orderByDesc('id')
            ->paginate($per_page);

        return response()->json([
            'personas' => PersonaCollection::make($personas),
            'total' => $personas->total(),
            'pagination' => [
                'total' => $personas->total(),
                'current_page' => $personas->currentPage(),
                'last_page' => $personas->lastPage(),
                'per_page' => $personas->perPage(),
                'total_visible' => min($personas->lastPage(), 5),
                'itemsPerPage' => $personas->perPage(),
            ],
        ], 200);
    }

    #[OA\Post(
        path: '/api/personas',
        summary: 'Registrar persona',
        description: 'Crea el registro de una persona, con los nuevos campos de contacto y ubigeo.',
        tags: ['Personas'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'application/json',
                schema: new OA\Schema(
                    type: 'object',
                    required: ['numero_documento', 'nombres'],
                    properties: [
                        new OA\Property(property: 'tipo_documento_identidad_id', type: 'integer', example: 1),
                        new OA\Property(property: 'numero_documento', type: 'string', maxLength: 20, example: '20123456789'),
                        new OA\Property(property: 'nombres', type: 'string', maxLength: 150, example: 'Comercializadora SAC'),
                        new OA\Property(property: 'apellido_paterno', type: 'string', maxLength: 100, nullable: true, example: 'Pérez'),
                        new OA\Property(property: 'apellido_materno', type: 'string', maxLength: 100, nullable: true, example: 'García'),
                        new OA\Property(property: 'numero_celular', type: 'string', maxLength: 20, nullable: true, example: '999888777'),
                        new OA\Property(property: 'correo', type: 'string', maxLength: 150, nullable: true, example: 'contacto@comercial.com'),
                        new OA\Property(property: 'direccion', type: 'string', maxLength: 255, nullable: true, example: 'Av. Las Palmeras 123'),
                        new OA\Property(property: 'departamento_id', type: 'integer', nullable: true, example: 15),
                        new OA\Property(property: 'provincia_id', type: 'integer', nullable: true, example: 1501),
                        new OA\Property(property: 'distrito_id', type: 'integer', nullable: true, example: 150101),
                        new OA\Property(property: 'estado', type: 'integer', example: 1),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Persona registrada correctamente'),
            new OA\Response(response: 422, description: 'Errores de validación')
        ]
    )]
    public function store(Request $request)
    {
        $request->validate([
            'tipo_documento_identidad_id' => ['nullable', 'integer', 'exists:tipo_documento_identidades,id'],
            'numero_documento'            => ['required', 'string', 'max:20', 'unique:personas,numero_documento'],
            'nombres'                     => ['required', 'string', 'max:150'],
            'apellido_paterno'            => ['nullable', 'string', 'max:100'],
            'apellido_materno'            => ['nullable', 'string', 'max:100'],
            'numero_celular'              => ['nullable', 'string', 'max:20'],
            'correo'                      => ['nullable', 'email', 'max:150'],
            'direccion'                   => ['nullable', 'string', 'max:255'],
            'departamento_id'             => ['nullable', 'integer', 'exists:departamentos,id'],
            'provincia_id'                => ['nullable', 'integer', 'exists:provincias,id'],
            'distrito_id'                 => ['nullable', 'integer', 'exists:distritos,id'],
            'estado'                      => ['nullable', 'integer', 'in:0,1']
        ]);

        $persona = Persona::create($request->all());

        return response()->json([
            'codigo' => 201,
            'mensaje' => 'Persona registrada correctamente',
            'persona' => PersonaResource::make($persona->load(['tipoDocumentoIdentidad', 'departamento', 'provincia', 'distrito']))
        ], 201);
    }

    #[OA\Get(
        path: '/api/personas/{id}',
        summary: 'Obtener detalle de persona',
        description: 'Obtiene la información completa de una persona por su ID.',
        tags: ['Personas'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Datos obtenidos correctamente'),
            new OA\Response(response: 404, description: 'Persona no encontrada')
        ]
    )]
    public function show($id)
    {
        $persona = Persona::with(['tipoDocumentoIdentidad', 'departamento', 'provincia', 'distrito'])->findOrFail($id);
        return PersonaResource::make($persona);
    }

    #[OA\Put(
        path: '/api/personas/{id}',
        summary: 'Actualizar persona',
        description: 'Actualiza los datos personales y de ubicación de la persona.',
        tags: ['Personas'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'application/json',
                schema: new OA\Schema(
                    type: 'object',
                    required: ['numero_documento', 'nombres'],
                    properties: [
                        new OA\Property(property: 'tipo_documento_identidad_id', type: 'integer', example: 1),
                        new OA\Property(property: 'numero_documento', type: 'string', maxLength: 20, example: '20123456789'),
                        new OA\Property(property: 'nombres', type: 'string', maxLength: 150, example: 'Comercializadora SAC'),
                        new OA\Property(property: 'apellido_paterno', type: 'string', maxLength: 100, nullable: true),
                        new OA\Property(property: 'apellido_materno', type: 'string', maxLength: 100, nullable: true),
                        new OA\Property(property: 'numero_celular', type: 'string', maxLength: 20, nullable: true),
                        new OA\Property(property: 'correo', type: 'string', maxLength: 150, nullable: true),
                        new OA\Property(property: 'direccion', type: 'string', maxLength: 255, nullable: true),
                        new OA\Property(property: 'departamento_id', type: 'integer', nullable: true),
                        new OA\Property(property: 'provincia_id', type: 'integer', nullable: true),
                        new OA\Property(property: 'distrito_id', type: 'integer', nullable: true),
                        new OA\Property(property: 'estado', type: 'integer', example: 1)
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Persona actualizada correctamente'),
            new OA\Response(response: 422, description: 'Errores de validación'),
            new OA\Response(response: 404, description: 'Persona no encontrada')
        ]
    )]
    public function update(Request $request, $id)
    {
        $persona = Persona::findOrFail($id);

        $request->validate([
            'tipo_documento_identidad_id' => ['nullable', 'integer', 'exists:tipo_documento_identidades,id'],
            'numero_documento'            => ['required', 'string', 'max:20', 'unique:personas,numero_documento,' . $persona->id],
            'nombres'                     => ['required', 'string', 'max:150'],
            'apellido_paterno'            => ['nullable', 'string', 'max:100'],
            'apellido_materno'            => ['nullable', 'string', 'max:100'],
            'numero_celular'              => ['nullable', 'string', 'max:20'],
            'correo'                      => ['nullable', 'email', 'max:150'],
            'direccion'                   => ['nullable', 'string', 'max:255'],
            'departamento_id'             => ['nullable', 'integer', 'exists:departamentos,id'],
            'provincia_id'                => ['nullable', 'integer', 'exists:provincias,id'],
            'distrito_id'                 => ['nullable', 'integer', 'exists:distritos,id'],
            'estado'                      => ['nullable', 'integer', 'in:0,1']
        ]);

        $persona->update($request->all());

        return response()->json([
            'codigo' => 200,
            'mensaje' => 'Persona actualizada correctamente',
            'persona' => PersonaResource::make($persona->load(['tipoDocumentoIdentidad', 'departamento', 'provincia', 'distrito']))
        ], 200);
    }

    #[OA\Delete(
        path: '/api/personas/{id}',
        summary: 'Eliminar persona',
        description: 'Eliminación lógica (SoftDelete) de una persona.',
        tags: ['Personas'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Persona eliminada correctamente'),
            new OA\Response(response: 404, description: 'Persona no encontrada')
        ]
    )]
    public function destroy($id)
    {
        $persona = Persona::findOrFail($id);
        $persona->delete();

        return response()->json([
            'codigo' => 200,
            'mensaje' => 'Persona eliminada correctamente'
        ], 200);
    }
}
