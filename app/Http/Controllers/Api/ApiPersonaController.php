<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Persona\PersonaCollection;
use App\Http\Resources\Persona\PersonaResource;
use App\Http\Resources\Usuario\UsuarioResource;
use App\Models\Persona;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;
use App\Models\Usuario;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class ApiPersonaController extends Controller
{
    public function __construct()
    {
        // Make 'store' (registration) public; other actions require authentication
        $this->middleware('jwt.auth')->except('store');
        $this->middleware('role:Administrador,api')->only('index');
        $this->middleware('can:listar_persona')->only('index');
        $this->middleware('can:editar_persona')->only('update');
        /* $this->middleware('can:eliminar_persona')->only('destroy'); */
    }

    #[OA\Get(
        path: '/api/personas',
        summary: 'Listar usuarios',
        description: 'Obtiene una lista paginada de personas con usuario asociado. Requiere token JWT de un usuario con rol Administrador.',
        tags: ['Personas'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'search',
                in: 'query',
                required: false,
                description: 'Busca por nombres, apellidos, número de documento, correo o login del usuario.',
                schema: new OA\Schema(
                    type: 'string',
                    example: 'Juan'
                )
            ),
            new OA\Parameter(
                name: 'per_page',
                in: 'query',
                required: false,
                description: 'Cantidad de registros por página.',
                schema: new OA\Schema(
                    type: 'integer',
                    default: 10,
                    example: 10
                )
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Usuarios obtenidos correctamente',
                content: new OA\JsonContent(
                    example: [
                        'usuarios' => [],
                        'total' => 0,
                        'pagination' => [
                            'total' => 0,
                            'current_page' => 1,
                            'last_page' => 1,
                            'per_page' => 10,
                            'total_visible' => 1,
                            'itemsPerPage' => 10
                        ]
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Token JWT inválido o no enviado'
            ),
            new OA\Response(
                response: 403,
                description: 'El usuario autenticado no tiene rol Administrador o no cuenta con el permiso listar_persona'
            ),
            new OA\Response(
                response: 500,
                description: 'Error interno del servidor'
            )
        ]
    )]

    public function index(Request $request)
    {
        $search = $request->input('search');
        $per_page = $request->input('per_page', 10);

        $personas = Persona::with(['usuario.rol', 'tipoDocumentoIdentidad', 'genero', 'departamento', 'provincia', 'distrito'])
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('nombres', 'ilike', "%{$search}%")
                        ->orWhere('apellido_paterno', 'ilike', "%{$search}%")
                        ->orWhere('apellido_materno', 'ilike', "%{$search}%")
                        ->orWhere('numero_documento', 'ilike', "%{$search}%")
                        ->orWhereHas('usuario', function ($qUsuario) use ($search) {
                            $qUsuario->where('correo', 'ilike', "%{$search}%")
                                ->orWhere('login', 'ilike', "%{$search}%");
                        });
                });
            })
            ->whereHas('usuario')
            ->orderByDesc('id')
            ->paginate($per_page);

        return response()->json([
            'usuarios' => PersonaCollection::collection($personas),
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
        summary: 'Registrar persona (tienda — crea también Usuario con rol Cliente)',
        description: 'Registra una nueva persona junto con su usuario. El rol no es enviado por el cliente: cuando el registro proviene del ecommerce, el sistema asigna automáticamente el rol Cliente. Permite registrar los datos personales y las credenciales de acceso.',
        tags: ['Personas'],
        security: [],
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
                        'apellido_materno',
                        'numero_celular',
                        'fecha_nacimiento',
                        'genero_id',
                        'correo',
                        'login',
                        'password',
                        'password_confirmation',
                    ],
                    properties: [
                        new OA\Property(property: 'tipo_documento_identidad_id', type: 'integer', example: 1, description: 'ID del tipo de documento de identidad.'),
                        new OA\Property(property: 'numero_documento', type: 'string', maxLength: 20, example: '12345678', description: 'Número de documento de identidad.'),
                        new OA\Property(property: 'nombres', type: 'string', maxLength: 150, example: 'Juan Carlos'),
                        new OA\Property(property: 'apellido_paterno', type: 'string', maxLength: 100, example: 'Pérez'),
                        new OA\Property(property: 'apellido_materno', type: 'string', maxLength: 100, example: 'García'),
                        new OA\Property(property: 'numero_celular', type: 'string', maxLength: 20, example: '987654321'),
                        new OA\Property(property: 'provincia_id', type: 'integer', nullable: true, example: 1),
                        new OA\Property(property: 'departamento_id', type: 'integer', nullable: true, example: 1),
                        new OA\Property(property: 'distrito_id', type: 'integer', nullable: true, example: 1),
                        new OA\Property(property: 'fecha_nacimiento', type: 'string', format: 'date', example: '1995-05-20'),
                        new OA\Property(property: 'genero_id', type: 'integer', example: 1),
                        new OA\Property(property: 'profile_photo_path', type: 'string', nullable: true, example: 'profiles/usuario.jpg'),
                        new OA\Property(property: 'correo', type: 'string', format: 'email', maxLength: 150, example: 'cliente@example.com', description: 'Correo electrónico utilizado para el acceso y comunicación.'),
                        new OA\Property(property: 'login', type: 'string', maxLength: 100, example: 'juanperez', description: 'Nombre de usuario para iniciar sesión.'),
                        new OA\Property(property: 'password', type: 'string', format: 'password', minLength: 8, example: 'Password123'),
                        new OA\Property(property: 'password_confirmation', type: 'string', format: 'password', minLength: 8, example: 'Password123', description: 'Confirmación de la contraseña.'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Usuario registrado correctamente'
            ),
            new OA\Response(
                response: 422,
                description: 'Errores de validación'
            ),
            new OA\Response(
                response: 500,
                description: 'Error interno del servidor'
            )
        ]
    )]

    public function store(Request $request)
    {
        try {
            $request->validate([
                // =========================
                // DATOS DE PERSONA
                // =========================
                'tipo_documento_identidad_id' => ['required', 'integer', 'exists:tipo_documento_identidades,id'],
                'numero_documento' => ['required', 'string', 'max:20'],
                'nombres' => ['required', 'string', 'max:150'],
                'apellido_paterno' => ['required', 'string', 'max:100'],
                'apellido_materno' => ['nullable', 'string', 'max:100'],
                'numero_celular' => ['nullable', 'string', 'max:20'],
                'departamento_id' => ['nullable', 'integer', 'exists:departamentos,id'],
                'provincia_id' => ['nullable', 'integer', 'exists:provincias,id'],
                'distrito_id' => ['nullable', 'integer', 'exists:distritos,id'],
                'fecha_nacimiento' => ['nullable', 'date'],
                'genero_id' => ['nullable', 'integer', 'exists:generos,id'],
                'profile_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],

                // =========================
                // DATOS DE USUARIO
                // =========================
                'correo' => ['required', 'email', 'max:150', 'unique:usuarios,correo'],
                'login' => ['required', 'string', 'max:100', 'unique:usuarios,login'],
                'password' => ['required', 'string', 'min:6', 'max:255', 'confirmed'],
                                'estado' => ['nullable', 'boolean'],

                // Solo será utilizado si el registro lo realiza
                // un usuario autenticado con permisos administrativos.
                'rol_id' => ['nullable', 'integer', 'exists:roles,id'],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'mensaje' => 'Errores de validación',
                'errors' => $e->errors(),
            ], 422);
        }

        DB::beginTransaction();

        try {
            // =====================================================
            // 1. DETERMINAR EL ROL
            // =====================================================

            if (Auth::check()) {

                // Registro realizado desde el sistema administrativo.
                // El rol viene indicado por el administrador.
                if (!$request->rol_id) {
                    throw new \RuntimeException(
                        'Debe especificar el rol del usuario.'
                    );
                }

                $role = Role::findOrFail($request->rol_id);
                $rolId = $role->id;

            } else {

                // Registro público desde el e-commerce.
                // El usuario NO puede elegir su rol. Aseguramos la existencia del rol Cliente.
                $role = Role::firstOrCreate([
                    'name' => 'Cliente',
                    'guard_name' => config('auth.defaults.guard', 'api'),
                ]);
                $rolId = $role->id;
            }

            // =====================================================
            // 2. CREAR PERSONA
            // =====================================================

            $persona = new Persona();

            $persona->tipo_documento_identidad_id = $request->tipo_documento_identidad_id;
            $persona->numero_documento = $request->numero_documento;
            $persona->nombres = $request->nombres;
            $persona->apellido_paterno = $request->apellido_paterno;
            $persona->apellido_materno = $request->apellido_materno;
            $persona->numero_celular = $request->numero_celular;

            $persona->departamento_id = $request->departamento_id;
            $persona->provincia_id = $request->provincia_id;
            $persona->distrito_id = $request->distrito_id;

            $persona->fecha_nacimiento = $request->fecha_nacimiento;
            $persona->genero_id = $request->genero_id;

            $persona->estado = $request->has('estado') ? $request->estado : true;

            // Foto de perfil
            if ($request->hasFile('profile_photo')) {
                $persona->profile_photo_path = $request
                    ->file('profile_photo')
                    ->store('personas/profile', 'public');
            }

            $persona->save();

            // =====================================================
            // 3. CREAR USUARIO RELACIONADO
            // =====================================================

            $usuario = new Usuario();

            $usuario->correo = $request->correo;
            $usuario->login = $request->login;
            $usuario->password = Hash::make($request->password);
            $usuario->persona_id = $persona->id;
            $usuario->rol_id = $rolId;
            $usuario->estado = $persona->estado;

            $usuario->save();

            // Asignar rol Spatie al usuario (por si la app usa permisos)
            try {
                $usuario->assignRole($role);
            } catch (\Exception $e) {
                logger()->warning('No se pudo asignar rol al usuario: ' . $e->getMessage());
            }

            // =====================================================
            // 4. CARGAR RELACIONES
            // =====================================================

            $persona->load([
                'usuario.rol',
                'tipoDocumentoIdentidad',
                'genero',
                'departamento',
                'provincia',
                'distrito',
            ]);

            DB::commit();

            // =====================================================
            // 5. RESPONSE
            // =====================================================

            return response()->json([
                'codigo' => 200,
                'mensaje' => 'Usuario registrado correctamente',
                'usuario' => PersonaResource::make($persona),
            ], 200);

        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'mensaje' => 'Error crítico al registrar el usuario.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    #[OA\Put(
        path: '/api/personas/{id}',
        summary: 'Actualizar usuario y datos personales',
        description: 'Actualiza los datos de una persona y su usuario asociado en una sola operación. Permite actualizar las credenciales de acceso y cambiar opcionalmente la contraseña. El rol del usuario no puede modificarse mediante este endpoint.',
        tags: ['Personas'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID de la persona asociada al usuario que se desea actualizar.',
                schema: new OA\Schema(type: 'integer', example: 1)
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
                        'apellido_materno',
                        'numero_celular',
                        'fecha_nacimiento',
                        'genero_id',
                        'correo',
                        'login',
                        'estado'
                    ],
                    properties: [
                        new OA\Property(
                            property: 'tipo_documento_identidad_id',
                            type: 'integer',
                            example: 1,
                            description: 'ID del tipo de documento de identidad.'
                        ),
                        new OA\Property(
                            property: 'numero_documento',
                            type: 'string',
                            maxLength: 20,
                            example: '12345678',
                            description: 'Número de documento de identidad. Debe ser único.'
                        ),
                        new OA\Property(
                            property: 'nombres',
                            type: 'string',
                            maxLength: 150,
                            example: 'Juan Carlos'
                        ),
                        new OA\Property(
                            property: 'apellido_paterno',
                            type: 'string',
                            maxLength: 100,
                            example: 'Pérez'
                        ),
                        new OA\Property(
                            property: 'apellido_materno',
                            type: 'string',
                            maxLength: 100,
                            example: 'García'
                        ),
                        new OA\Property(
                            property: 'numero_celular',
                            type: 'string',
                            maxLength: 20,
                            example: '987654321'
                        ),
                        new OA\Property(
                            property: 'provincia_id',
                            type: 'integer',
                            nullable: true,
                            example: 1
                        ),
                        new OA\Property(
                            property: 'departamento_id',
                            type: 'integer',
                            nullable: true,
                            example: 1
                        ),
                        new OA\Property(
                            property: 'distrito_id',
                            type: 'integer',
                            nullable: true,
                            example: 1
                        ),
                        new OA\Property(
                            property: 'fecha_nacimiento',
                            type: 'string',
                            format: 'date',
                            example: '1995-05-20'
                        ),
                        new OA\Property(
                            property: 'genero_id',
                            type: 'integer',
                            example: 1
                        ),
                        new OA\Property(
                            property: 'profile_photo_path',
                            type: 'string',
                            nullable: true,
                            maxLength: 2048,
                            example: 'profiles/usuario.jpg'
                        ),
                        new OA\Property(
                            property: 'correo',
                            type: 'string',
                            format: 'email',
                            maxLength: 150,
                            example: 'cliente@example.com',
                            description: 'Correo electrónico del usuario. Debe ser único.'
                        ),
                        new OA\Property(
                            property: 'login',
                            type: 'string',
                            maxLength: 100,
                            example: 'juanperez',
                            description: 'Nombre de usuario para iniciar sesión. Debe ser único.'
                        ),
                        new OA\Property(
                            property: 'password',
                            type: 'string',
                            format: 'password',
                            minLength: 8,
                            nullable: true,
                            example: 'NuevaPassword123',
                            description: 'Nueva contraseña. Es opcional; si no se envía, se conserva la contraseña actual.'
                        ),
                        new OA\Property(
                            property: 'password_confirmation',
                            type: 'string',
                            format: 'password',
                            minLength: 8,
                            nullable: true,
                            example: 'NuevaPassword123',
                            description: 'Confirmación de la nueva contraseña.'
                        ),
                        new OA\Property(
                            property: 'estado',
                            type: 'integer',
                            enum: [0, 1],
                            example: 1,
                            description: 'Estado de la persona y usuario: 1 activo, 0 inactivo.'
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
                response: 422,
                description: 'Errores de validación'
            ),
            new OA\Response(
                response: 404,
                description: 'Persona o usuario no encontrado'
            ),
            new OA\Response(
                response: 500,
                description: 'Error interno del servidor'
            )
        ]
    )]

    public function update(Request $request, Persona $persona)
    {
        try {
            $request->validate([
                'tipo_documento_identidad_id' => ['required', 'integer', 'exists:tipo_documento_identidades,id'],
                'numero_documento'             => ['required', 'string', 'max:20', 'unique:personas,numero_documento,' . $persona->id],
                'nombres'                      => ['required', 'string', 'max:150'],
                'apellido_paterno'             => ['required', 'string', 'max:100'],
                'apellido_materno'             => ['required', 'string', 'max:100'],
                'numero_celular'              => ['required', 'string', 'max:20'],
                'provincia_id'                => ['nullable', 'integer', 'exists:provincias,id'],
                'departamento_id'             => ['nullable', 'integer', 'exists:departamentos,id'],
                'distrito_id'                 => ['nullable', 'integer', 'exists:distritos,id'],
                'fecha_nacimiento'            => ['required', 'date'],
                'genero_id'                   => ['required', 'integer', 'exists:generos,id'],
                'profile_photo_path'          => ['nullable', 'string', 'max:2048'],
                'correo'                      => ['required', 'email', 'max:150', 'unique:usuarios,correo,' . optional($persona->usuario)->id],
                'login'                       => ['required', 'string', 'max:100', 'unique:usuarios,login,' . optional($persona->usuario)->id],
                'password'                    => ['nullable', 'string', 'min:8', 'confirmed'],
                'estado'                      => ['required', 'boolean'],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['mensaje' => 'Errores de validación', 'errors' => $e->errors()], 422);
        }

        DB::beginTransaction();

        try {
            $persona->tipo_documento_identidad_id = $request->tipo_documento_identidad_id;
            $persona->numero_documento = $request->numero_documento;
            $persona->nombres = $request->nombres;
            $persona->apellido_paterno = $request->apellido_paterno;
            $persona->apellido_materno = $request->apellido_materno;
            $persona->numero_celular = $request->numero_celular;
            $persona->provincia_id = $request->provincia_id;
            $persona->departamento_id = $request->departamento_id;
            $persona->distrito_id = $request->distrito_id;
            $persona->fecha_nacimiento = $request->fecha_nacimiento;
            $persona->genero_id = $request->genero_id;
            $persona->profile_photo_path = $request->profile_photo_path;
            $persona->estado = $request->estado;
            $persona->save();

            $usuario = $persona->usuario;

            if (!$usuario) {
                throw new \RuntimeException('La persona no tiene un usuario asociado.');
            }

            $usuario->correo = $request->correo;
            $usuario->login = $request->login;
            $usuario->estado = $request->estado;

            if ($request->filled('password')) {
                $usuario->password = Hash::make($request->password);
            }

            $usuario->save();

            DB::commit();

            return response()->json([
                'codigo' => 200,
                'mensaje' => 'Usuario actualizado correctamente',
                'usuario' => UsuarioResource::make(
                    $persona->load([
                        'usuario',
                        'tipoDocumentoIdentidad',
                        'genero',
                        'departamento',
                        'provincia',
                        'distrito'
                    ])
                ),
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'mensaje' => 'Error crítico al actualizar el usuario.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
