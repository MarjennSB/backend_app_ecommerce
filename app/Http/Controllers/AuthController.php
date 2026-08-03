<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use OpenApi\Attributes as OA;
use PHPOpenSourceSaver\JWTAuth\JWTGuard;

class AuthController extends Controller
{

    public function __construct()
    {
        $this->middleware('jwt.verify', ['except' => ['login']]);
    }
    
    #[OA\Post(
        path: '/api/auth/login',
        summary: 'Iniciar sesión',
        tags: ['Autenticación'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['login', 'password'],
                properties: [
                    new OA\Property(property: 'login', type: 'string', example: 'admin'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: '123'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Token generado correctamente'),
            new OA\Response(response: 401, description: 'Credenciales no válidas'),
            new OA\Response(response: 500, description: 'Error al procesar el token'),
        ]
    )]
    
    public function login(Request $request)
    {
        $credentials = $request->only('login', 'password');

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
            'usuario' => $user,
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

