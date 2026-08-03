<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;
use App\Http\Middleware\JwtVerify;

class JwtVerifyMiddlewareTest extends TestCase
{
    public function test_it_accepts_token_from_x_csrf_token_header_when_authorization_header_is_missing(): void
    {
        $middleware = new JwtVerify();
        $request = Request::create('/api/productos', 'GET', [], [], [], [
            'HTTP_X_CSRF_TOKEN' => 'test-token',
        ]);

        JWTAuth::shouldReceive('setToken')->once()->with('test-token')->andReturnSelf();
        JWTAuth::shouldReceive('authenticate')->once()->andReturn((object) ['id' => 1]);

        $response = $middleware->handle($request, function ($req) {
            return response()->json(['ok' => true], 200);
        });

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('{"ok":true}', $response->getContent());
    }
}
