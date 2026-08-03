<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'Backend App Ecommerce API',
    description: 'Documentación de la API del Ecommerce'
)]

#[OA\Server(
    url: 'http://127.0.0.1:8000',
    description: 'Servidor Local'
)]

#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'JWT',
    description: 'Ingrese el token JWT con el formato: Bearer {token}'
)]

abstract class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;
}