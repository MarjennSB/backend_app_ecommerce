<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Usuario\UsuarioCollection;
use App\Http\Resources\Usuario\UsuarioResource;
use App\Models\Usuario;
use Illuminate\Http\Request;
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
        $this->middleware('can:eliminar_usuario')->only('destroy');
    }

    #[OA\Get(
        path: '/api/usuarios',
        summary: 'Listar usuarios',
        tags: ['Usuarios'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'search',
                in: 'query',
                required: false,
                description: 'Buscar usuario por nombres, apellidos, documento o login',
                schema: new OA\Schema(type: 'string')
            ),
            new OA\Parameter(
                name: 'per_page',
                in: 'query',
                required: false,
                description: 'Cantidad de registros por página',
                schema: new OA\Schema(type: 'integer', example: 15)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Lista de usuarios obtenida correctamente'
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

        $usuarios = Usuario::with(['roles', 'tipoDocumentoIdentidad'])
            ->where(function ($q) use ($search) {
                $q->where('nombres',           'ilike', '%' . $search . '%')
                  ->orWhere('apellido_paterno', 'ilike', '%' . $search . '%')
                  ->orWhere('apellido_materno', 'ilike', '%' . $search . '%')
                  ->orWhere('numero_documento', 'ilike', '%' . $search . '%')
                  ->orWhere('login',            'ilike', '%' . $search . '%');
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
    description: 'Crea un nuevo usuario con información personal, rol y fotografía opcional.',
    tags: ['Usuarios'],
    security: [['bearerAuth' => []]],

    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(
                required: [
                    'nombres',
                    'apellido_paterno',
                    'password',
                    'estado'
                ],
                properties: [

                    new OA\Property(
                        property: 'nombres',
                        type: 'string',
                        example: 'JUAN'
                    ),

                    new OA\Property(
                        property: 'apellido_paterno',
                        type: 'string',
                        example: 'PEREZ'
                    ),

                    new OA\Property(
                        property: 'apellido_materno',
                        type: 'string',
                        nullable: true,
                        example: 'GOMEZ'
                    ),

                    new OA\Property(
                        property: 'correo',
                        type: 'string',
                        format: 'email',
                        nullable: true,
                        example: 'juan@correo.com'
                    ),

                    new OA\Property(
                        property: 'tipo_documento_identidad_id',
                        type: 'integer',
                        nullable: true,
                        example: 1
                    ),

                    new OA\Property(
                        property: 'numero_documento',
                        type: 'string',
                        nullable: true,
                        example: '12345678'
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
                        property: 'rol_id',
                        type: 'integer',
                        nullable: true,
                        example: 2
                    ),

                    new OA\Property(
                        property: 'genero_id',
                        type: 'integer',
                        nullable: true,
                        example: 1
                    ),

                    new OA\Property(
                        property: 'profile_photo_path',
                        type: 'string',
                        format: 'binary',
                        nullable: true,
                        description: 'Imagen del usuario (jpg, jpeg, png, webp máximo 2MB)'
                    ),

                    new OA\Property(
                        property: 'estado',
                        type: 'integer',
                        enum: [0,1],
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
                'nombres'                     => ['required', 'string', 'max:255'],
                'apellido_paterno'            => ['required', 'string', 'max:255'],
                'apellido_materno'            => ['nullable', 'string', 'max:255'],
                'correo'                      => ['nullable', 'string', 'email', 'max:255'],
                'tipo_documento_identidad_id' => ['nullable', 'integer', 'exists:tipo_documento_identidades,id'],
                'numero_documento'            => ['nullable', 'string', 'max:50'],
                'login'                       => ['nullable', 'string', 'max:100', 'unique:usuarios,login'],
                'password'                    => ['required', 'string', 'min:6'],
                'rol_id'                      => ['nullable', 'integer', 'exists:roles,id'],
                'genero_id'                   => ['nullable', 'integer', 'exists:generos,id'],
                'profile_photo_path'          => ['nullable','image','mimes:jpg,jpeg,png,webp','max:2048'],
                'estado'                      => ['required', 'boolean'],
            ], [
                'nombres.required'          => 'Los nombres son obligatorios.',
                'apellido_paterno.required' => 'El apellido paterno es obligatorio.',
                'correo.email'              => 'El correo no tiene un formato válido.',
                'login.unique'              => 'Ese login ya está en uso.',
                'password.required'         => 'La contraseña es obligatoria.',
                'password.min'              => 'La contraseña debe tener al menos 6 caracteres.',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['mensaje' => 'Errores de validación', 'errors' => $e->errors()], 422);
        }

        $usuario = new Usuario();
        $usuario->nombres                     = strtoupper($request->nombres);
        $usuario->apellido_paterno            = strtoupper($request->apellido_paterno);
        $usuario->apellido_materno            = $request->apellido_materno ? strtoupper($request->apellido_materno) : null;
        $usuario->correo                      = $request->correo;
        $usuario->tipo_documento_identidad_id = $request->tipo_documento_identidad_id;
        $usuario->numero_documento            = $request->numero_documento;
        $usuario->login                       = $request->login;
        $usuario->password                    = $request->password;
        $usuario->genero_id                   = $request->genero_id;
        $usuario->estado                      = $request->estado;
        
        if ($request->hasFile('profile_photo_path')) {
            $ruta = $request->file('profile_photo_path')->store('usuarios', 'public');
            $usuario->profile_photo_path = $ruta;
        }
        

        if ($request->filled('rol_id')) {
            $rol = Role::find($request->rol_id);
            if ($rol) $usuario->syncRoles([Role::findByName($rol->name, 'api')]);
        }

        $usuario->save();
        return response()->json([
            'codigo'   => 200,
            'mensaje'  => 'Usuario creado correctamente',
            'usuario'  => UsuarioResource::make($usuario->load(['roles', 'tipoDocumentoIdentidad', 'genero'])),
        ], 200);
    }

    #[OA\Post(
    path: '/api/usuarios/{id}',
    summary: 'Actualizar usuario',
    description: 'Actualiza la información personal, rol y fotografía opcional de un usuario.',
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
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(
                type: 'object',
                required: [
                    'nombres',
                    'apellido_paterno',
                    'estado'
                ],
                properties: [
                    new OA\Property(
                        property: '_method',
                        type: 'string',
                        example: 'PUT',
                        description: 'Spoofing del método para que Laravel procese la imagen correctamente'
                    ),

                    new OA\Property(
                        property: 'nombres',
                        type: 'string',
                        example: 'test'
                    ),

                    new OA\Property(
                        property: 'apellido_paterno',
                        type: 'string',
                        example: 'test'
                    ),

                    new OA\Property(
                        property: 'apellido_materno',
                        type: 'string',
                        nullable: true,
                        example: 'test'
                    ),

                    new OA\Property(
                        property: 'correo',
                        type: 'string',
                        format: 'email',
                        nullable: true,
                        example: 'jua321321@correo.com'
                    ),

                    new OA\Property(
                        property: 'tipo_documento_identidad_id',
                        type: 'integer',
                        nullable: true,
                        example: 1
                    ),

                    new OA\Property(
                        property: 'numero_documento',
                        type: 'string',
                        nullable: true,
                        example: '12345678'
                    ),

                    new OA\Property(
                        property: 'login',
                        type: 'string',
                        nullable: true,
                        example: 'test123'
                    ),

                    new OA\Property(
                        property: 'password',
                        type: 'string',
                        format: 'password',
                        nullable: true,
                        example: '123456'
                    ),

                    new OA\Property(
                        property: 'rol_id',
                        type: 'integer',
                        nullable: true,
                        example: 1
                    ),

                    new OA\Property(
                        property: 'genero_id',
                        type: 'integer',
                        nullable: true,
                        example: 1
                    ),

                    new OA\Property(
                        property: 'profile_photo_path',
                        type: 'string',
                        format: 'binary',
                        nullable: true,
                        description: 'Nueva imagen del usuario (jpg, jpeg, png, webp máximo 2MB)'
                    ),

                    new OA\Property(
                        property: 'estado',
                        type: 'integer',
                        enum: [0,1],
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
                'nombres'                     => ['required', 'string', 'max:255'],
                'apellido_paterno'            => ['required', 'string', 'max:255'],
                'apellido_materno'            => ['nullable', 'string', 'max:255'],
                'correo'                         => ['nullable', 'string', 'email', 'max:255'],
                'tipo_documento_identidad_id' => ['nullable', 'integer', 'exists:tipo_documento_identidades,id'],
                'numero_documento'            => ['nullable', 'string', 'max:50'],
                'login'                       => ['nullable', 'string', 'max:100', 'unique:usuarios,login,' . $usuario->id],
                'password'                    => ['nullable', 'string', 'min:6'],
                'rol_id'                      => ['nullable', 'integer', 'exists:roles,id'],
                'genero_id'                   => ['nullable', 'integer', 'exists:generos,id'],
                'profile_photo_path'          => ['nullable','image','mimes:jpg,jpeg,png,webp','max:2048'],
                'estado'                      => ['required', 'boolean'],
            ], [
                'nombres.required'          => 'Los nombres son obligatorios.',
                'apellido_paterno.required' => 'El apellido paterno es obligatorio.',
                'login.unique'              => 'Ese login ya está en uso.',
                'password.min'              => 'La contraseña debe tener al menos 6 caracteres.',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['mensaje' => 'Errores de validación', 'errors' => $e->errors()], 422);
        }

        $usuario->nombres                     = strtoupper($request->nombres);
        $usuario->apellido_paterno            = strtoupper($request->apellido_paterno);
        $usuario->apellido_materno            = $request->apellido_materno ? strtoupper($request->apellido_materno) : null;
        $usuario->tipo_documento_identidad_id = $request->tipo_documento_identidad_id;
        $usuario->numero_documento            = $request->numero_documento;
        $usuario->correo                      = $request->correo;
        $usuario->login                       = $request->login;
        $usuario->genero_id                   = $request->genero_id;
        
        if ($request->hasFile('profile_photo_path')) {
            if ($usuario->profile_photo_path &&
                Storage::disk('public')->exists($usuario->profile_photo_path)) {
                Storage::disk('public')->delete($usuario->profile_photo_path);
            }
            $ruta = $request->file('profile_photo_path')->store('usuarios', 'public');
            $usuario->profile_photo_path = $ruta;
        }

        $usuario->estado = $request->estado;

        if ($request->filled('password')) {
            $usuario->password = $request->password;
        }
        $usuario->save();

        if ($request->has('rol_id')) {
            if ($request->filled('rol_id')) {
                $rol = Role::find($request->rol_id);
                if ($rol) $usuario->syncRoles([Role::findByName($rol->name, 'api')]);
            } else {
                $usuario->syncRoles([]);
            }
        }

        return response()->json([
            'mensaje' => 'Usuario actualizado correctamente',
            'usuario' => UsuarioResource::make($usuario->load(['roles', 'tipoDocumentoIdentidad', 'genero'])),
        ], 200);
    }
}