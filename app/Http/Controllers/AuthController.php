<?php

namespace App\Http\Controllers;

use App\Http\Resources\Usuario\UsuarioResource;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;
use PHPOpenSourceSaver\JWTAuth\JWTGuard;
use Spatie\Permission\Models\Role;

class AuthController extends Controller
{

    public function __construct()
    {
        $this->middleware('jwt.verify', ['except' => ['login', 'register']]);
    }
    
    #[OA\Post(
        path: '/api/auth/login',
        summary: 'Iniciar sesión',
        tags: ['Autenticación'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['correo', 'password'],
                properties: [
                    new OA\Property(property: 'correo', type: 'string', example: 'admin@correo.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: '123'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Token generado correctamente'),
            new OA\Response(response: 401, description: 'Credenciales no válidas'),
            new OA\Response(response: 500, description: 'Error al procesar el token')
        ]
    )]
    
    public function login(Request $request)
    {
        $credentials = $request->only('correo', 'password');

        try {
            if (! $token = $this->guard()->attempt($credentials)) {
                return response()->json(['error' => 'Credenciales no validas'], 401);
            }
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'linea' => $e->getLine(),
                'archivo' => $e->getFile(),
            ], 500);
        }

        return $this->respondWithToken($token);
    }

    #[OA\Post(
        path: '/api/auth/register',
        summary: 'Registrar nuevo cliente',
        description: 'Endpoint público para que los clientes se registren en la tienda virtual completando todos sus datos. Automáticamente asigna el rol de USUARIO EXTERNO y devuelve el token de sesión.',
        tags: ['Autenticación'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['nombres', 'apellido_paterno', 'correo', 'password'],
                properties: [
                    new OA\Property(property: 'tipo_documento_identidad_id', type: 'integer', nullable: true, example: 1),
                    new OA\Property(property: 'numero_documento', type: 'string', nullable: true, example: '12345678'),
                    new OA\Property(property: 'nombres', type: 'string', example: 'Juan Carlos'),
                    new OA\Property(property: 'apellido_paterno', type: 'string', example: 'Perez'),
                    new OA\Property(property: 'apellido_materno', type: 'string', nullable: true, example: 'Gomez'),
                    new OA\Property(property: 'numero_celular', type: 'string', nullable: true, example: '999888777'),
                    new OA\Property(property: 'departamento_id', type: 'integer', nullable: true, example: 15),
                    new OA\Property(property: 'provincia_id', type: 'integer', nullable: true, example: 1501),
                    new OA\Property(property: 'distrito_id', type: 'integer', nullable: true, example: 150101),
                    new OA\Property(property: 'fecha_nacimiento', type: 'string', format: 'date', nullable: true, example: '1990-05-15'),
                    new OA\Property(property: 'genero_id', type: 'integer', nullable: true, example: 1),
                    new OA\Property(property: 'correo', type: 'string', format: 'email', example: 'juan@correo.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: '123456'),
                    new OA\Property(property: 'acepto_termino_condiciones', type: 'integer', enum: [0, 1], example: 1),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Usuario registrado y autenticado correctamente'
            ),
            new OA\Response(
                response: 422,
                description: 'Errores de validación'
            )
        ]
    )]
    public function register(Request $request)
    {
        try {
            $request->validate([
                'tipo_documento_identidad_id' => ['nullable', 'integer', 'exists:tipo_documento_identidades,id'],
                'numero_documento'            => ['nullable', 'string', 'max:20', 'unique:usuarios,numero_documento'],
                'nombres'                     => ['required', 'string', 'max:150'],
                'apellido_paterno'            => ['required', 'string', 'max:100'],
                'apellido_materno'            => ['nullable', 'string', 'max:100'],
                'numero_celular'              => ['nullable', 'string', 'max:20'],
                'departamento_id'             => ['nullable', 'integer', 'exists:departamentos,id'],
                'provincia_id'                => ['nullable', 'integer', 'exists:provincias,id'],
                'distrito_id'                 => ['nullable', 'integer', 'exists:distritos,id'],
                'fecha_nacimiento'            => ['nullable', 'date'],
                'genero_id'                   => ['nullable', 'integer', 'exists:generos,id'],
                'correo'                      => ['required', 'string', 'email', 'max:150', 'unique:usuarios,correo'],
                'password'                    => ['required', 'string', 'min:6'],
                'acepto_termino_condiciones'  => ['nullable', 'boolean'],
            ], [
                'numero_documento.unique' => 'El número de documento ya está registrado.',
                'nombres.required'        => 'El nombre es obligatorio.',
                'apellido_paterno.required'=> 'El apellido paterno es obligatorio.',
                'correo.required'         => 'El correo es obligatorio.',
                'correo.email'            => 'El formato del correo es inválido.',
                'correo.unique'           => 'Este correo ya está registrado.',
                'password.required'       => 'La contraseña es obligatoria.',
                'password.min'            => 'La contraseña debe tener al menos 6 caracteres.',
            ]);

            // Custom validation for numero_documento length
            if ($request->filled('tipo_documento_identidad_id') && $request->filled('numero_documento')) {
                $tipoDoc = \App\Models\TipoDocumentoIdentidad::find($request->tipo_documento_identidad_id);
                if ($tipoDoc) {
                    $length = strlen($request->numero_documento);
                    if ($tipoDoc->minimo === $tipoDoc->maximo && $length !== $tipoDoc->maximo) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'numero_documento' => ["El número de documento para {$tipoDoc->nombre} debe tener exactamente {$tipoDoc->maximo} caracteres."]
                        ]);
                    } elseif ($length < $tipoDoc->minimo || $length > $tipoDoc->maximo) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'numero_documento' => ["El número de documento para {$tipoDoc->nombre} debe tener entre {$tipoDoc->minimo} y {$tipoDoc->maximo} caracteres."]
                        ]);
                    }
                }
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'mensaje' => 'Errores de validación',
                'errors'  => $e->errors()
            ], 422);
        }

        $usuario = new \App\Models\Usuario();
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
        $usuario->password                    = \Illuminate\Support\Facades\Hash::make($request->password);
        $usuario->acepto_termino_condiciones  = $request->acepto_termino_condiciones ?? false;
        $usuario->estado                      = 1;
        
        // Asignar el rol automáticamente
        $role = Role::firstOrCreate(['name' => 'USUARIO EXTERNO', 'guard_name' => 'api']);
        $usuario->rol_id = $role->id;
        
        $usuario->save();

        $usuario->assignRole($role);

        // Iniciar sesión automáticamente
        $credentials = $request->only('correo', 'password');
        $token = $this->guard()->attempt($credentials);

        return response()->json([
            'mensaje'      => 'Registro completado exitosamente.',
            'usuario'      => UsuarioResource::make($usuario),
            'access_token' => $token,
            'token_type'   => 'bearer',
            'expires_in'   => $this->guard()->getTTL() * 60,
        ], 200);
    }

    #[OA\Get(
        path: '/api/auth/me',
        summary: 'Obtener usuario autenticado',
        tags: ['Autenticación'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Información del usuario'
            ),
            new OA\Response(
                response: 401,
                description: 'No autorizado'
            ),
        ]
    )]

    public function me()
    {
        $user = $this->guard()->user();
        return response()->json([
            'estado' => true,
            'mensaje' => 'Se obtuvo la informacion exitosamente',
            'usuario' => UsuarioResource::make($user),
        ]);
    }

    #[OA\Post(
        path: '/api/auth/logout',
        summary: 'Cerrar sesión',
        description: 'Invalida el token JWT del usuario autenticado.',
        tags: ['Autenticación'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Sesión cerrada correctamente'
            ),
            new OA\Response(
                response: 401,
                description: 'Token inválido o no autorizado'
            ),
        ]
    )]

    public function logout()
    {
        $this->guard()->logout();

        return response()->json(['message' => 'Se cerro satisfactoriamente la sesion']);
    }

    #[OA\Post(
        path: '/api/auth/refresh',
        summary: 'Renovar token',
        description: 'Genera un nuevo token JWT para el usuario autenticado.',
        tags: ['Autenticación'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Nuevo token generado correctamente'
            ),
            new OA\Response(
                response: 401,
                description: 'Token inválido o expirado'
            ),
        ]
    )]

    public function refresh()
    {
        return $this->respondWithToken($this->guard()->refresh());
    }

    protected function respondWithToken(string $token)
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => $this->guard()->getTTL() * 60,
        ]);
    }

    private function guard(): JWTGuard
    {
        /** @var JWTGuard $guard */
        $guard = auth('api');

        return $guard;
    }
}
