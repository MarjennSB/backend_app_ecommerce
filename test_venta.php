<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Simulate authenticated user
$user = \App\Models\Usuario::first();
\Illuminate\Support\Facades\Auth::login($user);

$request = Illuminate\Http\Request::create('/api/ventas', 'POST', [
    "direccion_envio_id" => 1,
    "tipo_metodo_pago_id" => 1,
    "tipo_documento_comprobante_id" => 1,
    "serie_comprobante" => "B001",
    "numero_comprobante" => "00000456",
    "codigo_transaccion_pasarela" => "TXN-20260811-00001",
    "fecha_venta" => "2026-08-11",
    "costo_envio" => 15,
    "descuento_total" => 10,
    "impuestos_igv" => 0.18,
    "estado" => 1,
    "detalles" => [
        [
            "producto_id" => 1,
            "cantidad" => 22,
            "precio_unitario" => 3500,
            "porcentaje_descuento" => 10
        ]
    ]
]);

$controller = app(\App\Http\Controllers\Api\ApiVentaController::class);
try {
    $response = $controller->store($request);
    echo $response->getContent();
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
