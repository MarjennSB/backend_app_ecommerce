<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Simulate authenticated user
$user = \App\Models\Usuario::first();
\Illuminate\Support\Facades\Auth::login($user);

$request = Illuminate\Http\Request::create('/api/transacciones?fecha_inicio=2026-08-01&fecha_fin=2026-08-31&tipo_transaccion_id=1&per_page=10', 'GET');

$controller = app(\App\Http\Controllers\Api\ApiTransaccionController::class);
try {
    $response = $controller->index($request);
    echo $response->getContent();
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
