<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$request = Illuminate\Http\Request::create('/api/personas', 'POST', [
    "tipo_documento_identidad_id" => 1,
    "numero_documento" => "20123456789",
    "nombres" => "Comercializadora SAC",
    "apellido_paterno" => "Perez",
    "apellido_materno" => "Garcia",
    "numero_celular" => "999888777",
    "correo" => "contacto@comercial.com",
    "direccion" => "Av. Las Palmeras 123",
    "departamento_id" => 15,
    "provincia_id" => 1501,
    "distrito_id" => 150101,
    "estado" => 1
]);

$controller = app(\App\Http\Controllers\Api\ApiPersonaController::class);
try {
    $response = $controller->store($request);
    echo $response->getContent();
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
