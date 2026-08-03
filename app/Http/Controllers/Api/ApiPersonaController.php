<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Persona\PersonaCollection;
use App\Http\Resources\Persona\PersonaResource;
use App\Models\Persona;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class ApiPersonaController extends Controller
{
    public function __construct()
    {
        $this->middleware('jwt.auth');
        $this->middleware('can:listar_persona')->only('index');
        $this->middleware('can:registrar_persona')->only('store');
        $this->middleware('can:editar_persona')->only('update');
        $this->middleware('can:eliminar_persona')->only('destroy');
    }

    #[OA\Get(
        path: '/api/personas',
        summary: 'Listar personas',
        description: 'Obtiene una lista paginada de personas. Permite filtrar mediante un término de búsqueda y carga de forma ansiosa (eager loading) todos los catálogos relacionados.',
        tags: ['Personas'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'search',
                in: 'query',
                required: false,
                description: 'Término de búsqueda (coincidencia parcial en nombres, apellidos o número de documento)',
                schema: new OA\Schema(
                    type: 'string',
                    example: 'PEREZ'
                )
            ),
            new OA\Parameter(
                name: 'per_page',
                in: 'query',
                required: false,
                description: 'Cantidad de registros a mostrar por página',
                schema: new OA\Schema(
                    type: 'integer',
                    default: 10
                )
            ),
            new OA\Parameter(
                name: 'page',
                in: 'query',
                required: false,
                description: 'Número de página de los resultados',
                schema: new OA\Schema(
                    type: 'integer',
                    default: 1
                )
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Listado de personas obtenido correctamente'
            ),
            new OA\Response(
                response: 401,
                description: 'No autorizado (Token inválido o expirado)'
            )
        ]
    )]

    public function index(Request $request)
    {
        // Cambio de get() a input()
        $search = $request->input('search');
        $per_page = $request->input('per_page', 10);

        // Agregamos with() para cargar los catálogos y corregimos 'nombres'
        $personas = Persona::with(['tipoDocumentoIdentidad', 'genero', 'departamento', 'provincia', 'distrito'])
            ->where(function ($query) use ($search) {
                // Agrupamos la búsqueda para que funcione bien con los orWhere
                $query->where('nombres', 'ilike', '%' . $search . '%')
                    ->orWhere('apellido_paterno', 'ilike', '%' . $search . '%')
                    ->orWhere('apellido_materno', 'ilike', '%' . $search . '%')
                    ->orWhere('numero_documento', 'ilike', '%' . $search . '%');
            })
            ->orderBy('id', 'desc')
            ->paginate($per_page);

        return response()->json([
            'personas' => PersonaCollection::make($personas),
            'total' => $personas->total(),
            'pagination' => [
                'total' => $personas->total(),
                'current_page' => $personas->currentPage(),
                'last_page' => $personas->lastPage(),
                'per_page' => $personas->perPage(),
                'total_visible' => $personas->lastPage() < 5 ? $personas->lastPage() : 5,
                'itemsPerPage' => $personas->perPage(),
            ],
        ]);
    }

    #[OA\Post(
        path: '/api/personas',
        summary: 'Registrar persona',
        description: 'Crea un nuevo registro de persona en la base de datos (puede ser cliente o proveedor).',
        tags: ['Personas'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'application/json',
                schema: new OA\Schema(
                    type: 'object',
                    required: [
                        'tipo_documento_identidad_id',
                        'numero_documento',
                        'nombres',
                        'apellido_paterno',
                        'genero_id',
                        'estado'
                    ],
                    properties: [
                        new OA\Property(
                            property: 'tipo_documento_identidad_id',
                            type: 'integer',
                            example: 1,
                            description: 'ID del tipo de documento'
                        ),
                        new OA\Property(
                            property: 'numero_documento',
                            type: 'string',
                            maxLength: 255,
                            example: '70123456'
                        ),
                        new OA\Property(
                            property: 'nombres',
                            type: 'string',
                            maxLength: 255,
                            example: 'CARLOS ENRIQUE'
                        ),
                        new OA\Property(
                            property: 'apellido_paterno',
                            type: 'string',
                            maxLength: 255,
                            example: 'MENDOZA'
                        ),
                        new OA\Property(
                            property: 'apellido_materno',
                            type: 'string',
                            nullable: true,
                            maxLength: 255,
                            example: 'CASTILLO'
                        ),
                        new OA\Property(
                            property: 'correo',
                            type: 'string',
                            format: 'email',
                            nullable: true,
                            maxLength: 255,
                            example: 'carlos@correo.com'
                        ),
                        new OA\Property(
                            property: 'numero_celular',
                            type: 'string',
                            nullable: true,
                            maxLength: 20,
                            example: '987654321'
                        ),
                        new OA\Property(
                            property: 'direccion',
                            type: 'string',
                            nullable: true,
                            maxLength: 500,
                            example: 'Av. Los Olivos 123'
                        ),
                        new OA\Property(
                            property: 'departamento_id',
                            type: 'integer',
                            nullable: true,
                            example: 15
                        ),
                        new OA\Property(
                            property: 'provincia_id',
                            type: 'integer',
                            nullable: true,
                            example: 1
                        ),
                        new OA\Property(
                            property: 'distrito_id',
                            type: 'integer',
                            nullable: true,
                            example: 14
                        ),
                        new OA\Property(
                            property: 'fecha_nacimiento',
                            type: 'string',
                            format: 'date',
                            nullable: true,
                            example: '1995-08-20'
                        ),
                        new OA\Property(
                            property: 'genero_id',
                            type: 'integer',
                            example: 1
                        ),
                        new OA\Property(
                            property: 'estado',
                            type: 'boolean',
                            example: true
                        )
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Persona creada correctamente'
            ),
            new OA\Response(
                response: 422,
                description: 'Errores de validación'
            ),
            new OA\Response(
                response: 401,
                description: 'No autorizado'
            )
        ]
    )]

    public function store(Request $request)
    {
        try {
            $request->validate([
                'tipo_documento_identidad_id' => ['required', 'integer', 'exists:tipo_documento_identidades,id'],
                'numero_documento' => ['required', 'string', 'max:255', 'unique:personas,numero_documento'],
                'nombres' => ['required', 'string', 'max:255'],
                'apellido_paterno' => ['required', 'string', 'max:255'],
                'apellido_materno' => ['nullable', 'string', 'max:255'],
                'correo' => ['nullable', 'email', 'max:255'],
                'numero_celular' => ['nullable', 'string', 'max:20'],
                'direccion' => ['nullable', 'string', 'max:500'],
                'departamento_id' => ['nullable', 'integer', 'exists:departamentos,id'],
                'provincia_id'    => ['nullable', 'integer', 'exists:provincias,id'],
                'distrito_id'     => ['nullable', 'integer', 'exists:distritos,id'],
                'fecha_nacimiento' => ['nullable', 'date'],
                'genero_id'       => ['required', 'integer', 'exists:generos,id'],
                'estado'          => ['required', 'boolean'],
            ], [
                'tipo_documento_identidad_id.required' => 'El tipo de documento es obligatorio.',
                'tipo_documento_identidad_id.exists'   => 'El tipo de documento seleccionado no es válido.',
                'numero_documento.required' => 'El número de documento es obligatorio.',
                'numero_documento.unique'   => 'Ya existe una persona con ese número de documento.',
                'nombres.required'          => 'Los nombres son obligatorios.',
                'apellido_paterno.required' => 'El apellido paterno es obligatorio.',
                'correo.email'              => 'El correo debe tener un formato válido.',
                'correo.max'                => 'El correo no debe superar los 255 caracteres.',
                'numero_celular.max'        => 'El número de celular no debe superar los 20 caracteres.',
                'departamento_id.exists'    => 'El departamento seleccionado no es válido.',
                'provincia_id.exists'       => 'La provincia seleccionada no es válida.',
                'distrito_id.exists'        => 'El distrito seleccionado no es válido.',
                'fecha_nacimiento.date'     => 'La fecha de nacimiento debe ser una fecha válida.',
                'genero_id.required'        => 'El género es obligatorio.',
                'genero_id.exists'          => 'El género seleccionado no es válido.',
                'estado.required'           => 'El estado es obligatorio.',
                'estado.boolean'            => 'El estado debe ser verdadero o falso.',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'mensaje' => 'Errores de validación',
                'errors'  => $e->errors(),
            ], 422);
        }
        $persona = new Persona();
        $persona->tipo_documento_identidad_id = $request->tipo_documento_identidad_id;
        $persona->numero_documento = $request->numero_documento;
        $persona->nombres = strtoupper($request->nombres);
        $persona->apellido_paterno = strtoupper($request->apellido_paterno);
        $persona->apellido_materno = $request->apellido_materno ? strtoupper($request->apellido_materno) : null;        $persona->correo = $request->correo;
        $persona->numero_celular = $request->numero_celular;
        $persona->direccion = $request->direccion;
        $persona->departamento_id = $request->departamento_id;
        $persona->provincia_id = $request->provincia_id;
        $persona->distrito_id = $request->distrito_id;
        $persona->fecha_nacimiento = $request->fecha_nacimiento;
        $persona->genero_id = $request->genero_id;
        $persona->estado = $request->estado;
        $persona->save();

        return response()->json([
            'codigo' => 200,
            'mensaje' => 'Persona creada correctamente',
            'persona' => PersonaResource::make($persona),
        ], 200);
    }

    #[OA\Put(
        path: '/api/personas/{id}',
        summary: 'Actualizar persona',
        description: 'Actualiza los datos personales, de contacto y ubicación de una persona existente.',
        tags: ['Personas'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID de la persona a actualizar',
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
                        'tipo_documento_identidad_id',
                        'numero_documento',
                        'nombres',
                        'apellido_paterno',
                        'genero_id',
                        'estado'
                    ],
                    properties: [
                        new OA\Property(
                            property: 'tipo_documento_identidad_id',
                            type: 'integer',
                            example: 1,
                            description: 'ID del tipo de documento'
                        ),
                        new OA\Property(
                            property: 'numero_documento',
                            type: 'string',
                            maxLength: 255,
                            example: '70123456'
                        ),
                        new OA\Property(
                            property: 'nombres',
                            type: 'string',
                            maxLength: 255,
                            example: 'CARLOS ENRIQUE'
                        ),
                        new OA\Property(
                            property: 'apellido_paterno',
                            type: 'string',
                            maxLength: 255,
                            example: 'MENDOZA'
                        ),
                        new OA\Property(
                            property: 'apellido_materno',
                            type: 'string',
                            nullable: true,
                            maxLength: 255,
                            example: 'CASTILLO'
                        ),
                        new OA\Property(
                            property: 'correo',
                            type: 'string',
                            format: 'email',
                            nullable: true,
                            maxLength: 255,
                            example: 'carlos@correo.com'
                        ),
                        new OA\Property(
                            property: 'numero_celular',
                            type: 'string',
                            nullable: true,
                            maxLength: 20,
                            example: '987654321'
                        ),
                        new OA\Property(
                            property: 'direccion',
                            type: 'string',
                            nullable: true,
                            maxLength: 500,
                            example: 'Av. Los Olivos 123'
                        ),
                        new OA\Property(
                            property: 'departamento_id',
                            type: 'integer',
                            nullable: true,
                            example: 15
                        ),
                        new OA\Property(
                            property: 'provincia_id',
                            type: 'integer',
                            nullable: true,
                            example: 1
                        ),
                        new OA\Property(
                            property: 'distrito_id',
                            type: 'integer',
                            nullable: true,
                            example: 14
                        ),
                        new OA\Property(
                            property: 'fecha_nacimiento',
                            type: 'string',
                            format: 'date',
                            nullable: true,
                            example: '1995-08-20'
                        ),
                        new OA\Property(
                            property: 'genero_id',
                            type: 'integer',
                            example: 1
                        ),
                        new OA\Property(
                            property: 'estado',
                            type: 'boolean',
                            example: true
                        )
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Persona actualizada correctamente'
            ),
            new OA\Response(
                response: 404,
                description: 'Persona no encontrada'
            ),
            new OA\Response(
                response: 422,
                description: 'Errores de validación'
            ),
            new OA\Response(
                response: 401,
                description: 'No autorizado'
            )
        ]
    )]

    public function update(Request $request, Persona $persona)
    {
        try {
            $request->validate([
                'tipo_documento_identidad_id' => ['required', 'integer', 'exists:tipo_documento_identidades,id'],
                'numero_documento' => ['required', 'string', 'max:255', 'unique:personas,numero_documento,' . $persona->id],
                'nombres' => ['required', 'string', 'max:255'],
                'apellido_paterno' => ['required', 'string', 'max:255'],
                'apellido_materno' => ['nullable', 'string', 'max:255'],
                'correo' => ['nullable', 'email', 'max:255'],
                'numero_celular' => ['nullable', 'string', 'max:20'],
                'direccion' => ['nullable', 'string', 'max:500'],
                'departamento_id'  => ['nullable', 'integer', 'exists:departamentos,id'],
                'provincia_id'     => ['nullable', 'integer', 'exists:provincias,id'],
                'distrito_id'      => ['nullable', 'integer', 'exists:distritos,id'],
                'fecha_nacimiento' => ['nullable', 'date'],
                'genero_id'        => ['required', 'integer', 'exists:generos,id'],
                'estado'           => ['required', 'boolean'],
            ], [
                'tipo_documento_identidad_id.required' => 'El tipo de documento es obligatorio.',
                'tipo_documento_identidad_id.exists'   => 'El tipo de documento seleccionado no es válido.',
                'numero_documento.required' => 'El número de documento es obligatorio.',
                'numero_documento.unique'   => 'Ya existe una persona con ese número de documento.',
                'nombres.required'          => 'Los nombres son obligatorios.',
                'apellido_paterno.required' => 'El apellido paterno es obligatorio.',
                'correo.email'              => 'El correo debe tener un formato válido.',
                'correo.max'                => 'El correo no debe superar los 255 caracteres.',
                'numero_celular.max'        => 'El número de celular no debe superar los 20 caracteres.',
                'departamento_id.exists'    => 'El departamento seleccionado no es válido.',
                'provincia_id.exists'       => 'La provincia seleccionada no es válida.',
                'distrito_id.exists'        => 'El distrito seleccionado no es válido.',
                'fecha_nacimiento.date'     => 'La fecha de nacimiento debe ser una fecha válida.',
                'genero_id.required'        => 'El género es obligatorio.',
                'genero_id.exists'          => 'El género seleccionado no es válido.',
                'estado.required'           => 'El estado es obligatorio.',
                'estado.boolean'            => 'El estado debe ser verdadero o falso.',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'mensaje' => 'Errores de validación',
                'errors'  => $e->errors(),
            ], 422);
        }

        $persona->tipo_documento_identidad_id = $request->tipo_documento_identidad_id;
        $persona->numero_documento = $request->numero_documento;
        $persona->nombres = strtoupper($request->nombres);
        $persona->apellido_paterno = strtoupper($request->apellido_paterno);
        $persona->apellido_materno = $request->apellido_materno ? strtoupper($request->apellido_materno) : null;       
        $persona->correo = $request->correo;
        $persona->numero_celular = $request->numero_celular;
        $persona->direccion = $request->direccion;
        $persona->departamento_id = $request->departamento_id;
        $persona->provincia_id = $request->provincia_id;
        $persona->distrito_id = $request->distrito_id;
        $persona->fecha_nacimiento = $request->fecha_nacimiento;
        $persona->genero_id = $request->genero_id;
        $persona->estado = $request->estado;
        $persona->save();

        return response()->json([
            'mensaje' => 'Persona actualizada correctamente',
            'persona' => PersonaResource::make($persona) // CORRECCIÓN AQUÍ 👇
        ], 200);
    }
}