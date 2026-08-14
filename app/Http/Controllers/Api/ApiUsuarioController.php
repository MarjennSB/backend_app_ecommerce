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
        description: 'Obtiene el listado paginado de usuarios con sus datos completos y roles.',
        tags: ['Usuarios'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'search',
                in: 'query',
                required: false,
                description: 'Buscar por nombres, apellidos, documento o correo.',
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
            new OA\Response(response: 200, description: 'Usuarios obtenidos correctamente'),
            new OA\Response(response: 401, description: 'No autenticado'),
            new OA\Response(response: 500, description: 'Error interno del servidor')
        ]
    )]
    public function index(Request $request)
    {
        $search   = $request->string('search');
        $per_page = $request->integer('per_page', 10);

        $usuarios = Usuario::with([
            'tipoDocumentoIdentidad', 
            'genero', 
            'departamento', 
            'provincia', 
            'distrito', 
            'roles'
        ])
            ->where(function ($q) use ($search) {
                $q->where('nombres', 'like', '%' . $search . '%')
                  ->orWhere('apellido_paterno', 'like', '%' . $search . '%')
                  ->orWhere('apellido_materno', 'like', '%' . $search . '%')
                  ->orWhere('numero_documento', 'like', '%' . $search . '%')
                  ->orWhere('correo', 'like', '%' . $search . '%');
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
        description: 'Crea un nuevo usuario con todos sus datos y un rol.',
        tags: ['Usuarios'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'application/json',
                schema: new OA\Schema(
                    required: ['password', 'estado'],
                    properties: [
                        new OA\Property(property: 'tipo_documento_identidad_id', type: 'integer', nullable: true, example: 1),
                        new OA\Property(property: 'numero_documento', type: 'string', nullable: true, example: '12345678'),
                        new OA\Property(property: 'nombres', type: 'string', nullable: true, example: 'Juan Carlos'),
                        new OA\Property(property: 'apellido_paterno', type: 'string', nullable: true, example: 'Perez'),
                        new OA\Property(property: 'apellido_materno', type: 'string', nullable: true, example: 'Gomez'),
                        new OA\Property(property: 'numero_celular', type: 'string', nullable: true, example: '999888777'),
                        new OA\Property(property: 'departamento_id', type: 'integer', nullable: true, example: 15),
                        new OA\Property(property: 'provincia_id', type: 'integer', nullable: true, example: 1501),
                        new OA\Property(property: 'distrito_id', type: 'integer', nullable: true, example: 150101),
                        new OA\Property(property: 'fecha_nacimiento', type: 'string', format: 'date', nullable: true, example: '1990-05-15'),
                        new OA\Property(property: 'genero_id', type: 'integer', nullable: true, example: 1),
                        new OA\Property(property: 'correo', type: 'string', format: 'email', nullable: true, example: 'juan@correo.com'),
                        new OA\Property(property: 'password', type: 'string', format: 'password', example: '123456'),
                        new OA\Property(property: 'rol_id', type: 'integer', nullable: true, example: 2),
                        new OA\Property(property: 'acepto_termino_condiciones', type: 'integer', enum: [0, 1], example: 1),
                        new OA\Property(property: 'estado', type: 'integer', enum: [0, 1], example: 1, description: '1 activo, 0 inactivo')
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Usuario creado correctamente'),
            new OA\Response(response: 422, description: 'Errores de validación'),
            new OA\Response(response: 401, description: 'No autorizado')
        ]
    )]
    public function store(Request $request)
    {
        try {
            $request->validate([
                'tipo_documento_identidad_id' => ['nullable', 'integer', 'exists:tipo_documento_identidades,id'],
                'numero_documento'            => ['nullable', 'string', 'max:20', 'unique:usuarios,numero_documento'],
                'nombres'                     => ['nullable', 'string', 'max:150'],
                'apellido_paterno'            => ['nullable', 'string', 'max:100'],
                'apellido_materno'            => ['nullable', 'string', 'max:100'],
                'numero_celular'              => ['nullable', 'string', 'max:20'],
                'departamento_id'             => ['nullable', 'integer', 'exists:departamentos,id'],
                'provincia_id'                => ['nullable', 'integer', 'exists:provincias,id'],
                'distrito_id'                 => ['nullable', 'integer', 'exists:distritos,id'],
                'fecha_nacimiento'            => ['nullable', 'date'],
                'genero_id'                   => ['nullable', 'integer', 'exists:generos,id'],
                'correo'                      => ['nullable', 'string', 'email', 'max:150', 'unique:usuarios,correo'],
                'password'                    => ['required', 'string', 'min:6'],
                'rol_id'                      => ['nullable', 'integer', 'exists:roles,id'],
                'acepto_termino_condiciones'  => ['nullable', 'boolean'],
                'estado'                      => ['required', 'boolean'],
            ], [
                'numero_documento.unique' => 'El número de documento ya está registrado.',
                'correo.email'            => 'El correo no tiene un formato válido.',
                'correo.unique'           => 'El correo ya está registrado.',
                'password.required'       => 'La contraseña es obligatoria.',
                'password.min'            => 'La contraseña debe tener al menos 6 caracteres.',
                'rol_id.exists'           => 'El rol seleccionado no existe.',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'mensaje' => 'Errores de validación',
                'errors'  => $e->errors()
            ], 422);
        }

        $usuario = new Usuario();
        $usuario->tipo_documento_identidad_id = $request->tipo_documento_identidad_id;
        $usuario->numero_documento            = $request->numero_documento;
        $usuario->nombres                     = $request->nombres;
        $usuario->apellido_paterno            = $request->apellido_paterno;
        $usuario->apellido_materno            = $request->apellido_materno;
        $usuario->numero_celular              = $request->numero_celular;
        $usuario->departamento_id             = $request->departamento_id;
        $usuario->provincia_id                = $request->provincia_id;
        $usuario->distrito_id                 = $request->distrito_id;
        $usuario->fecha_nacimiento            = $request->fecha_nacimiento;
        $usuario->genero_id                   = $request->genero_id;
        $usuario->correo                      = $request->correo;
        $usuario->password                    = Hash::make($request->password);
        $usuario->rol_id                      = $request->rol_id;
        $usuario->acepto_termino_condiciones  = $request->acepto_termino_condiciones ?? false;
        $usuario->estado                      = $request->estado;

        $usuario->save();

        if ($request->rol_id) {
            $usuario->assignRole($request->rol_id);
        }

        return response()->json([
            'codigo'  => 200,
            'mensaje' => 'Usuario creado correctamente',
            'usuario' => UsuarioResource::make($usuario),
        ], 200);
    }

    #[OA\Put(
        path: '/api/usuarios/{id}',
        summary: 'Actualizar usuario',
        description: 'Actualiza la información completa de un usuario y su rol.',
        tags: ['Usuarios'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID del usuario',
                schema: new OA\Schema(type: 'integer', example: 1)
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'application/json',
                schema: new OA\Schema(
                    required: ['estado'],
                    properties: [
                        new OA\Property(property: 'tipo_documento_identidad_id', type: 'integer', nullable: true, example: 1),
                        new OA\Property(property: 'numero_documento', type: 'string', nullable: true, example: '12345678'),
                        new OA\Property(property: 'nombres', type: 'string', nullable: true, example: 'Juan Carlos'),
                        new OA\Property(property: 'apellido_paterno', type: 'string', nullable: true, example: 'Perez'),
                        new OA\Property(property: 'apellido_materno', type: 'string', nullable: true, example: 'Gomez'),
                        new OA\Property(property: 'numero_celular', type: 'string', nullable: true, example: '999888777'),
                        new OA\Property(property: 'departamento_id', type: 'integer', nullable: true, example: 15),
                        new OA\Property(property: 'provincia_id', type: 'integer', nullable: true, example: 1501),
                        new OA\Property(property: 'distrito_id', type: 'integer', nullable: true, example: 150101),
                        new OA\Property(property: 'fecha_nacimiento', type: 'string', format: 'date', nullable: true, example: '1990-05-15'),
                        new OA\Property(property: 'genero_id', type: 'integer', nullable: true, example: 1),
                        new OA\Property(property: 'correo', type: 'string', format: 'email', nullable: true, example: 'juan@correo.com'),
                        new OA\Property(property: 'password', type: 'string', format: 'password', nullable: true, example: '123456'),
                        new OA\Property(property: 'rol_id', type: 'integer', nullable: true, example: 2),
                        new OA\Property(property: 'acepto_termino_condiciones', type: 'integer', enum: [0, 1], example: 1),
                        new OA\Property(property: 'estado', type: 'integer', enum: [0, 1], example: 1, description: '1 activo, 0 inactivo')
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Usuario actualizado correctamente'),
            new OA\Response(response: 404, description: 'Usuario no encontrado'),
            new OA\Response(response: 422, description: 'Errores de validación'),
            new OA\Response(response: 401, description: 'No autorizado')
        ]
    )]
    public function update(Request $request, Usuario $usuario)
    {
        try {
            $request->validate([
                'tipo_documento_identidad_id' => ['nullable', 'integer', 'exists:tipo_documento_identidades,id'],
                'numero_documento'            => ['nullable', 'string', 'max:20', 'unique:usuarios,numero_documento,' . $usuario->id],
                'nombres'                     => ['nullable', 'string', 'max:150'],
                'apellido_paterno'            => ['nullable', 'string', 'max:100'],
                'apellido_materno'            => ['nullable', 'string', 'max:100'],
                'numero_celular'              => ['nullable', 'string', 'max:20'],
                'departamento_id'             => ['nullable', 'integer', 'exists:departamentos,id'],
                'provincia_id'                => ['nullable', 'integer', 'exists:provincias,id'],
                'distrito_id'                 => ['nullable', 'integer', 'exists:distritos,id'],
                'fecha_nacimiento'            => ['nullable', 'date'],
                'genero_id'                   => ['nullable', 'integer', 'exists:generos,id'],
                'correo'                      => ['nullable', 'string', 'email', 'max:150', 'unique:usuarios,correo,' . $usuario->id],
                'password'                    => ['nullable', 'string', 'min:6'],
                'rol_id'                      => ['nullable', 'integer', 'exists:roles,id'],
                'acepto_termino_condiciones'  => ['nullable', 'boolean'],
                'estado'                      => ['required', 'boolean'],
            ], [
                'numero_documento.unique' => 'El número de documento ya está registrado.',
                'correo.email'            => 'El correo no tiene un formato válido.',
                'correo.unique'           => 'El correo ya está registrado.',
                'password.min'            => 'La contraseña debe tener al menos 6 caracteres.',
                'rol_id.exists'           => 'El rol seleccionado no existe.',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'mensaje' => 'Errores de validación',
                'errors'  => $e->errors()
            ], 422);
        }

        $usuario->tipo_documento_identidad_id = $request->tipo_documento_identidad_id;
        $usuario->numero_documento            = $request->numero_documento;
        $usuario->nombres                     = $request->nombres;
        $usuario->apellido_paterno            = $request->apellido_paterno;
        $usuario->apellido_materno            = $request->apellido_materno;
        $usuario->numero_celular              = $request->numero_celular;
        $usuario->departamento_id             = $request->departamento_id;
        $usuario->provincia_id                = $request->provincia_id;
        $usuario->distrito_id                 = $request->distrito_id;
        $usuario->fecha_nacimiento            = $request->fecha_nacimiento;
        $usuario->genero_id                   = $request->genero_id;
        $usuario->correo                      = $request->correo;
        $usuario->rol_id                      = $request->rol_id;
        $usuario->acepto_termino_condiciones  = $request->acepto_termino_condiciones ?? false;
        $usuario->estado                      = $request->estado;

        if ($request->filled('password')) {
            $usuario->password = Hash::make($request->password);
        }

        $usuario->save();

        if ($request->rol_id) {
            $usuario->syncRoles([$request->rol_id]);
        }

        return response()->json([
            'mensaje' => 'Usuario actualizado correctamente',
            'usuario' => UsuarioResource::make($usuario),
        ], 200);
    }
}