<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DireccionEnvio\DireccionEnvioCollection;
use App\Http\Resources\DireccionEnvio\DireccionEnvioResource;
use App\Models\DireccionEnvio;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;


class ApiDireccionEnvioController extends Controller
{
    public function __construct()
        {
            $this->middleware('jwt.auth');
            $this->middleware('can:listar_direccionenvio')->only('index');
            $this->middleware('can:registrar_direccionenvio')->only('store');
            $this->middleware('can:editar_direccionenvio')->only('update');
            /* $this->middleware('can:eliminar_direccionenvio')->only('destroy'); */
        }


    #[OA\Get(
        path: '/api/direcciones-envio',
        summary: 'Listar direcciones de envío',
        tags: ['Direcciones de Envío'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'search',
                in: 'query',
                required: false,
                description: 'Buscar dirección por alias, dirección, urbanización, sector o referencia',
                schema: new OA\Schema(
                    type: 'string'
                )
            ),
            new OA\Parameter(
                name: 'per_page',
                in: 'query',
                required: false,
                description: 'Cantidad de registros por página',
                schema: new OA\Schema(
                    type: 'integer',
                    example: 15
                )
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Lista de direcciones de envío obtenida correctamente'
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

        $direcciones = DireccionEnvio::with(['usuario'])
            ->where(function ($q) use ($search) {
                $q->where('alias_direccion', 'like', '%' . $search . '%')
                    ->orWhere('urbanizacion', 'like', '%' . $search . '%')
                    ->orWhere('sector', 'like', '%' . $search . '%')
                    ->orWhere('direccion', 'like', '%' . $search . '%')
                    ->orWhere('referencia', 'like', '%' . $search . '%');
            })
            ->orderBy('id', 'desc')
            ->paginate($per_page);

        return response()->json([
            'direcciones' => DireccionEnvioCollection::make($direcciones),
            'pagination'  => [
                'total'        => $direcciones->total(),
                'current_page' => $direcciones->currentPage(),
                'last_page'    => $direcciones->lastPage(),
                'per_page'     => $direcciones->perPage(),
            ],
        ]);
    }


    #[OA\Post(
        path: '/api/direcciones-envio',
        summary: 'Registrar dirección de envío',
        description: 'Crea una nueva dirección de envío asociada a un usuario.',
        tags: ['Direcciones de Envío'],
        security: [['bearerAuth' => []]],

        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'application/json',
            schema: new OA\Schema(
                required: [
                    'usuario_id',
                    'direccion',
                    'es_direccion_principal',
                    'estado'
                ],
                properties: [

                    new OA\Property(
                        property: 'usuario_id',
                        type: 'integer',
                        example: 1,
                        description: 'ID del usuario propietario de la dirección'
                    ),

                    new OA\Property(
                        property: 'alias_direccion',
                        type: 'string',
                        nullable: true,
                        example: 'Casa',
                        description: 'Alias o nombre identificador de la dirección'
                    ),

                    new OA\Property(
                        property: 'urbanizacion',
                        type: 'string',
                        nullable: true,
                        example: 'Urb. Las Palmas',
                        description: 'Urbanización'
                    ),

                    new OA\Property(
                        property: 'sector',
                        type: 'string',
                        nullable: true,
                        example: 'Sector 2',
                        description: 'Sector'
                    ),

                    new OA\Property(
                        property: 'direccion',
                        type: 'string',
                        example: 'Av. Los Olivos 123',
                        description: 'Dirección principal de envío'
                    ),

                    new OA\Property(
                        property: 'manzana',
                        type: 'string',
                        nullable: true,
                        example: 'A',
                        description: 'Manzana'
                    ),

                    new OA\Property(
                        property: 'lote',
                        type: 'string',
                        nullable: true,
                        example: '15',
                        description: 'Lote'
                    ),

                    new OA\Property(
                        property: 'referencia',
                        type: 'string',
                        nullable: true,
                        example: 'Frente al parque principal',
                        description: 'Referencia para facilitar la ubicación'
                    ),

                    new OA\Property(
                        property: 'es_direccion_principal',
                        type: 'integer',
                        enum: [0, 1],
                        example: 1,
                        description: 'Indica si es la dirección principal: 1 sí, 0 no'
                    ),

                    new OA\Property(
                        property: 'estado',
                        type: 'integer',
                        enum: [0, 1],
                        example: 1,
                        description: 'Estado de la dirección: 1 activo, 0 inactivo'
                    )
                ]
            )
        )
    ),

        responses: [

            new OA\Response(
                response: 200,
                description: 'Dirección de envío creada correctamente'
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
                'usuario_id'             => ['required', 'integer', 'exists:usuarios,id'],
                'alias_direccion'        => ['nullable', 'string', 'max:50'],
                'urbanizacion'           => ['nullable', 'string', 'max:150'],
                'sector'                 => ['nullable', 'string', 'max:100'],
                'direccion'              => ['required', 'string', 'max:255'],
                'manzana'                => ['nullable', 'string', 'max:20'],
                'lote'                   => ['nullable', 'string', 'max:20'],
                'referencia'             => ['nullable', 'string', 'max:255'],
                'es_direccion_principal' => ['required', 'boolean'],
                'estado'                 => ['required', 'boolean'],
            ], [
                'usuario_id.required'              => 'El usuario es obligatorio.',
                'usuario_id.exists'                => 'El usuario seleccionado no existe.',
                'alias_direccion.max'              => 'El alias no puede superar los 50 caracteres.',
                'urbanizacion.max'                 => 'La urbanización no puede superar los 150 caracteres.',
                'sector.max'                       => 'El sector no puede superar los 100 caracteres.',
                'direccion.required'               => 'La dirección es obligatoria.',
                'direccion.max'                    => 'La dirección no puede superar los 255 caracteres.',
                'manzana.max'                      => 'La manzana no puede superar los 20 caracteres.',
                'lote.max'                         => 'El lote no puede superar los 20 caracteres.',
                'referencia.max'                   => 'La referencia no puede superar los 255 caracteres.',
                'es_direccion_principal.required'  => 'Debe indicar si la dirección es principal.',
                'estado.required'                  => 'El estado es obligatorio.',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'mensaje' => 'Errores de validación',
                'errors'  => $e->errors()
            ], 422);
        }

        $direccion = new DireccionEnvio();

        $direccion->usuario_id             = $request->usuario_id;
        $direccion->alias_direccion        = $request->alias_direccion;
        $direccion->urbanizacion           = $request->urbanizacion;
        $direccion->sector                 = $request->sector;
        $direccion->direccion              = $request->direccion;
        $direccion->manzana                = $request->manzana;
        $direccion->lote                   = $request->lote;
        $direccion->referencia             = $request->referencia;
        $direccion->es_direccion_principal = $request->es_direccion_principal;
        $direccion->estado                 = $request->estado;

        $direccion->save();

        return response()->json([
            'codigo'     => 200,
            'mensaje'    => 'Dirección de envío creada correctamente',
            'direccion'  => DireccionEnvioResource::make(
                $direccion->load(['usuario'])
            ),
        ], 200);
    }


    #[OA\Put(
        path: '/api/direcciones-envio/{direccionEnvio}',
        summary: 'Actualizar dirección de envío',
        description: 'Actualiza una dirección de envío existente asociada a un usuario.',
        tags: ['Direcciones de Envío'],
        security: [['bearerAuth' => []]],

        parameters: [
            new OA\Parameter(
                name: 'direccionEnvio',
                in: 'path',
                required: true,
                description: 'ID de la dirección de envío',
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
                    required: [
                        'usuario_id',
                        'direccion',
                        'es_direccion_principal',
                        'estado'
                    ],
                    properties: [

                        new OA\Property(
                            property: 'usuario_id',
                            type: 'integer',
                            example: 1,
                            description: 'ID del usuario propietario de la dirección'
                        ),

                        new OA\Property(
                            property: 'alias_direccion',
                            type: 'string',
                            nullable: true,
                            example: 'Casa',
                            description: 'Alias o nombre identificador de la dirección'
                        ),

                        new OA\Property(
                            property: 'urbanizacion',
                            type: 'string',
                            nullable: true,
                            example: 'Urb. Las Palmas',
                            description: 'Urbanización'
                        ),

                        new OA\Property(
                            property: 'sector',
                            type: 'string',
                            nullable: true,
                            example: 'Sector 2',
                            description: 'Sector'
                        ),

                        new OA\Property(
                            property: 'direccion',
                            type: 'string',
                            example: 'Av. Los Olivos 123',
                            description: 'Dirección principal de envío'
                        ),

                        new OA\Property(
                            property: 'manzana',
                            type: 'string',
                            nullable: true,
                            example: 'A',
                            description: 'Manzana'
                        ),

                        new OA\Property(
                            property: 'lote',
                            type: 'string',
                            nullable: true,
                            example: '15',
                            description: 'Lote'
                        ),

                        new OA\Property(
                            property: 'referencia',
                            type: 'string',
                            nullable: true,
                            example: 'Frente al parque principal',
                            description: 'Referencia para facilitar la ubicación'
                        ),

                        new OA\Property(
                            property: 'es_direccion_principal',
                            type: 'integer',
                            enum: [0, 1],
                            example: 1,
                            description: 'Indica si es la dirección principal: 1 sí, 0 no'
                        ),

                        new OA\Property(
                            property: 'estado',
                            type: 'integer',
                            enum: [0, 1],
                            example: 1,
                            description: 'Estado de la dirección: 1 activo, 0 inactivo'
                        )
                    ]
                )
            )
        ),

        responses: [

            new OA\Response(
                response: 200,
                description: 'Dirección de envío actualizada correctamente'
            ),

            new OA\Response(
                response: 404,
                description: 'Dirección de envío no encontrada'
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

    public function update(Request $request, DireccionEnvio $direccionEnvio)
    {
        try {
            $request->validate([
                'usuario_id'             => ['required', 'integer', 'exists:usuarios,id'],
                'alias_direccion'        => ['nullable', 'string', 'max:50'],
                'urbanizacion'           => ['nullable', 'string', 'max:150'],
                'sector'                 => ['nullable', 'string', 'max:100'],
                'direccion'              => ['required', 'string', 'max:255'],
                'manzana'                => ['nullable', 'string', 'max:20'],
                'lote'                   => ['nullable', 'string', 'max:20'],
                'referencia'             => ['nullable', 'string', 'max:255'],
                'es_direccion_principal' => ['required', 'boolean'],
                'estado'                 => ['required', 'boolean'],
            ], [
                'usuario_id.required'              => 'El usuario es obligatorio.',
                'usuario_id.exists'                => 'El usuario seleccionado no existe.',
                'alias_direccion.max'              => 'El alias no puede superar los 50 caracteres.',
                'urbanizacion.max'                 => 'La urbanización no puede superar los 150 caracteres.',
                'sector.max'                       => 'El sector no puede superar los 100 caracteres.',
                'direccion.required'               => 'La dirección es obligatoria.',
                'direccion.max'                    => 'La dirección no puede superar los 255 caracteres.',
                'manzana.max'                      => 'La manzana no puede superar los 20 caracteres.',
                'lote.max'                         => 'El lote no puede superar los 20 caracteres.',
                'referencia.max'                   => 'La referencia no puede superar los 255 caracteres.',
                'es_direccion_principal.required'  => 'Debe indicar si la dirección es principal.',
                'estado.required'                  => 'El estado es obligatorio.',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'mensaje' => 'Errores de validación',
                'errors'  => $e->errors()
            ], 422);
        }

        $direccionEnvio->usuario_id             = $request->usuario_id;
        $direccionEnvio->alias_direccion        = $request->alias_direccion;
        $direccionEnvio->urbanizacion           = $request->urbanizacion;
        $direccionEnvio->sector                 = $request->sector;
        $direccionEnvio->direccion              = $request->direccion;
        $direccionEnvio->manzana                = $request->manzana;
        $direccionEnvio->lote                   = $request->lote;
        $direccionEnvio->referencia             = $request->referencia;
        $direccionEnvio->es_direccion_principal = $request->es_direccion_principal;
        $direccionEnvio->estado                 = $request->estado;

        $direccionEnvio->save();

        return response()->json([
            'mensaje'   => 'Dirección de envío actualizada correctamente',
            'direccion' => DireccionEnvioResource::make(
                $direccionEnvio->load(['usuario'])
            ),
        ], 200);
    }
}