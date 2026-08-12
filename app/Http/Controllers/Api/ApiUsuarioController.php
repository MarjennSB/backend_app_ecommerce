<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Usuario\UsuarioCollection;
use App\Http\Resources\Usuario\UsuarioResource;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use OpenApi\Attributes as OA;
use Illuminate\Support\Facades\Storage;

class ApiUsuarioController extends Controller
{
    public function __construct()
    {
        $this->middleware('jwt.auth');
        $this->middleware('can:listar_usuario')->only('index');
        $this->middleware('can:registrar_usuario')->only('store');
        $this->middleware('can:editar_usuario')->only('update');
        /* $this->middleware('can:eliminar_usuario')->only('destroy'); */
    }

    #[OA\Get(
        path: '/api/usuarios',
        summary: 'Listar usuarios',
        description: 'Obtiene el listado paginado de usuarios junto con sus datos personales y rol.',
        tags: ['Usuarios'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'search',
                in: 'query',
                required: false,
                description: 'Buscar por nombres, apellidos, número de documento, correo o login.',
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
            new OA\Response(
                response: 200,
                description: 'Usuarios obtenidos correctamente'
            ),
            new OA\Response(
                response: 401,
                description: 'No autenticado'
            ),
            new OA\Response(
                response: 500,
                description: 'Error interno del servidor'
            )
        ]
    )]

    public function index(Request $request)
    {
        $search   = $request->string('search');
        $per_page = $request->integer('per_page', 10);

        $usuarios = Usuario::with(['persona', 'roles'])
            ->where(function ($q) use ($search) {
                $q->where('correo', 'ilike', '%' . $search . '%')
                ->orWhere('login', 'ilike', '%' . $search . '%');
            })
            ->orderBy('id', 'desc')
            ->paginate($per_page);

        return response()->json([
            'usuarios'   => UsuarioCollection::make($usuarios),
            'pagination' => [
                'total'        => $usuarios->total(),
                'current_page' => $usuarios->currentPage(),
                'last_page'    => $usuarios->lastPage(),
                'per_page'     => $usuarios->perPage(),
            ],
        ]);
    }

    #[OA\Post(
        path: '/api/usuarios',
        summary: 'Registrar usuario',
        description: 'Crea un nuevo usuario asociado a una persona y un rol.',
        tags: ['Usuarios'],
        security: [['bearerAuth' => []]],

        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'application/json',
                schema: new OA\Schema(
                    required: [
                        'password',
                        'estado'
                    ],
                    properties: [

                        new OA\Property(
                            property: 'correo',
                            type: 'string',
                            format: 'email',
                            nullable: true,
                            example: 'juan@correo.com'
                        ),

                        new OA\Property(
                            property: 'login',
                            type: 'string',
                            nullable: true,
                            example: 'jperez'
                        ),

                        new OA\Property(
                            property: 'password',
                            type: 'string',
                            format: 'password',
                            example: '123456'
                        ),

                        new OA\Property(
                            property: 'persona_id',
                            type: 'integer',
                            nullable: true,
                            example: 1
                        ),

                        new OA\Property(
                            property: 'rol_id',
                            type: 'integer',
                            nullable: true,
                            example: 2
                        ),

                        new OA\Property(
                            property: 'estado',
                            type: 'integer',
                            enum: [0, 1],
                            example: 1,
                            description: 'Estado del usuario: 1 activo, 0 inactivo'
                        )
                    ]
                )
            )
        ),

        responses: [

            new OA\Response(
                response: 200,
                description: 'Usuario creado correctamente'
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
                'correo'     => ['nullable', 'string', 'email', 'max:150'],
                'login'      => ['nullable', 'string', 'max:100', 'unique:usuarios,login'],
                'password'   => ['required', 'string', 'min:6'],
                'rol_id'     => ['nullable', 'integer', 'exists:roles,id'],
                'persona_id' => ['nullable', 'integer', 'exists:personas,id'],
                'estado'     => ['required', 'boolean'],
            ], [
                'correo.email'      => 'El correo no tiene un formato válido.',
                'login.unique'      => 'Ese login ya está en uso.',
                'password.required' => 'La contraseña es obligatoria.',
                'password.min'      => 'La contraseña debe tener al menos 6 caracteres.',
                'rol_id.exists'     => 'El rol seleccionado no existe.',
                'persona_id.exists' => 'La persona seleccionada no existe.',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'mensaje' => 'Errores de validación',
                'errors'  => $e->errors()
            ], 422);
        }

        $usuario = new Usuario();
        $usuario->correo     = $request->correo;
        $usuario->login      = $request->login;
        $usuario->password   = Hash::make($request->password);
        $usuario->persona_id = $request->persona_id;
        $usuario->rol_id     = $request->rol_id;
        $usuario->estado     = $request->estado;

        $usuario->save();

        if ($request->rol_id) {
            $usuario->assignRole($request->rol_id);
        }

        return response()->json([
            'codigo'  => 200,
            'mensaje' => 'Usuario creado correctamente',
            'usuario' => UsuarioResource::make(
                $usuario->load(['persona', 'roles'])
            ),
        ], 200);
    }

    #[OA\Put(
        path: '/api/usuarios/{id}',
        summary: 'Actualizar usuario',
        description: 'Actualiza la información de un usuario, su persona asociada y su rol.',
        tags: ['Usuarios'],
        security: [['bearerAuth' => []]],

        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID del usuario',
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
                        'estado'
                    ],
                    properties: [

                        new OA\Property(
                            property: 'correo',
                            type: 'string',
                            format: 'email',
                            nullable: true,
                            example: 'juan@correo.com'
                        ),

                        new OA\Property(
                            property: 'login',
                            type: 'string',
                            nullable: true,
                            example: 'jperez'
                        ),

                        new OA\Property(
                            property: 'password',
                            type: 'string',
                            format: 'password',
                            nullable: true,
                            example: '123456'
                        ),

                        new OA\Property(
                            property: 'persona_id',
                            type: 'integer',
                            nullable: true,
                            example: 1
                        ),

                        new OA\Property(
                            property: 'rol_id',
                            type: 'integer',
                            nullable: true,
                            example: 2
                        ),

                        new OA\Property(
                            property: 'estado',
                            type: 'integer',
                            enum: [0, 1],
                            example: 1,
                            description: 'Estado del usuario: 1 activo, 0 inactivo'
                        )
                    ]
                )
            )
        ),

        responses: [

            new OA\Response(
                response: 200,
                description: 'Usuario actualizado correctamente'
            ),

            new OA\Response(
                response: 404,
                description: 'Usuario no encontrado'
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



    public function update(Request $request, Usuario $usuario)
    {
        try {
            $request->validate([
                'correo'     => ['nullable', 'string', 'email', 'max:150'],
                'login'      => ['nullable', 'string', 'max:100', 'unique:usuarios,login,' . $usuario->id],
                'password'   => ['nullable', 'string', 'min:6'],
                'rol_id'     => ['nullable', 'integer', 'exists:roles,id'],
                'persona_id' => ['nullable', 'integer', 'exists:personas,id'],
                'estado'     => ['required', 'boolean'],
            ], [
                'correo.email'      => 'El correo no tiene un formato válido.',
                'login.unique'      => 'Ese login ya está en uso.',
                'password.min'      => 'La contraseña debe tener al menos 6 caracteres.',
                'rol_id.exists'     => 'El rol seleccionado no existe.',
                'persona_id.exists' => 'La persona seleccionada no existe.',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'mensaje' => 'Errores de validación',
                'errors'  => $e->errors()
            ], 422);
        }

        $usuario->correo     = $request->correo;
        $usuario->login      = $request->login;
        $usuario->persona_id = $request->persona_id;
        $usuario->rol_id     = $request->rol_id;
        $usuario->estado     = $request->estado;

        if ($request->filled('password')) {
            $usuario->password = Hash::make($request->password);
        }

        $usuario->save();

        return response()->json([
            'mensaje' => 'Usuario actualizado correctamente',
            'usuario' => UsuarioResource::make(
                $usuario->load(['persona', 'roles'])
            ),
        ], 200);
    }
}