<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$request = Illuminate\Http\Request::create('/api/compras', 'POST', [
    "proveedor_id" => 1,
    "tipo_documento_comprobante_id" => 1,
    "numero_comprobante" => "F001-00001234",
    "fecha_compra" => "2026-08-11",
    "estado" => 1,
    "detalles" => [
        [
            "producto_id" => 1,
            "cantidad" => 10,
            "costo_unitario" => 150.5
        ]
    ]
]);

$controller = app(\App\Http\Controllers\Api\ApiCompraController::class);
try {
    $response = $controller->store($request);
    echo $response->getContent();
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
