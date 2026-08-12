<?php

use App\Http\Controllers\Api\ApiCarritoController;
use App\Http\Controllers\Api\ApiCategoriaController;
use App\Http\Controllers\Api\ApiClienteController;
use App\Http\Controllers\Api\ApiCompraController;
use App\Http\Controllers\Api\ApiDireccionEnvioController;
use App\Http\Controllers\Api\ApiFavoritoController;
use App\Http\Controllers\Api\ApiInventarioController;
use App\Http\Controllers\Api\ApiPersonaController;
use App\Http\Controllers\Api\ApiProductoController;
use App\Http\Controllers\Api\ApiProveedorController;
use App\Http\Controllers\Api\ApiRoleController;
use App\Http\Controllers\Api\ApiTransaccionController;
use App\Http\Controllers\Api\ApiUsuarioController;
use App\Http\Controllers\Api\ApiResenaController;
use App\Http\Controllers\Api\ApiVentaController;
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Organized for clarity and Swagger documentation. Public routes are
| listed first (safe to expose in API docs). Protected routes are
| grouped under the jwt.verify middleware.
|
*/

// ----------------------
// Auth (public)
// ----------------------
Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->name('login');
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('me', [AuthController::class, 'me'])->name('me');
    Route::post('refresh', [AuthController::class, 'refresh'])->name('refresh');
});

// ----------------------
// Public endpoints (to expose in Swagger)
// Keep these endpoints minimal and read-only where possible
// ----------------------

// Products - public listing (index) is intentionally public for the storefront
Route::prefix('productos')->group(function () {
    Route::get('/', [ApiProductoController::class, 'index']);
    // Public product detail by slug
    Route::get('/{producto:slug}', [ApiProductoController::class, 'show']);
});

// Categories - public listing
Route::prefix('categorias')->group(function () {
    Route::get('/', [ApiCategoriaController::class, 'index']);
    // Public category detail by slug
    Route::get('/{categoria:slug}', [ApiCategoriaController::class, 'show']);
});

// Reviews - public listing
Route::prefix('resenas')->group(function () {
    Route::get('/', [ApiResenaController::class, 'index']);
});

// Personas - public registration (store is public so customers can register)
Route::post('personas', [ApiPersonaController::class, 'store']);


// ----------------------
// Protected endpoints (require authentication)
// ----------------------
Route::middleware('jwt.verify')->group(function () {

    // Roles (admin)
    Route::resource('roles', ApiRoleController::class)->only(['index','store','show','update','destroy']);

    // Users & people
    Route::prefix('usuarios')->group(function () {
        Route::get('/', [ApiUsuarioController::class, 'index']);
        Route::post('/', [ApiUsuarioController::class, 'store']);
        Route::put('/{usuario}', [ApiUsuarioController::class, 'update']);
    });

    Route::prefix('personas')->group(function () {
        Route::get('/', [ApiPersonaController::class, 'index']);
        Route::put('/{persona}', [ApiPersonaController::class, 'update']);
    });

    // Product management (admin)
    Route::prefix('productos')->group(function () {
        Route::post('/', [ApiProductoController::class, 'store']);
        Route::put('/{producto}', [ApiProductoController::class, 'update']);
        Route::delete('/{producto}', [ApiProductoController::class, 'destroy']);
    });

    // Category management (admin)
    Route::prefix('categorias')->group(function () {
        Route::post('/', [ApiCategoriaController::class, 'store']);
        Route::put('/{categoria}', [ApiCategoriaController::class, 'update']);
        Route::delete('/{categoria}', [ApiCategoriaController::class, 'destroy']);
    });

    // Favorites, cart and shipping addresses (user)
    Route::prefix('favoritos')->group(function () {
        Route::get('/', [ApiFavoritoController::class, 'index']);
        Route::post('/', [ApiFavoritoController::class, 'store']);
        Route::put('/{favorito}', [ApiFavoritoController::class, 'update']);
    });

    Route::prefix('carritos')->group(function () {
        Route::get('/', [ApiCarritoController::class, 'index']);
        Route::post('/', [ApiCarritoController::class, 'store']);
        Route::put('/{detalleCarrito}', [ApiCarritoController::class, 'update']);
    });

    /* Route::prefix('direcciones-envio')->group(function () {
        Route::get('/', [ApiDireccionEnvioController::class, 'index']);
        Route::post('/', [ApiDireccionEnvioController::class, 'store']);
        Route::put('/{direccionEnvio}', [ApiDireccionEnvioController::class, 'update']);
    }); */

    // Sales (ventas)
    Route::prefix('ventas')->group(function () {
        Route::get('/', [ApiVentaController::class, 'index']);
        Route::get('/{venta}', [ApiVentaController::class, 'show']);
        Route::post('/', [ApiVentaController::class, 'store']);
        Route::put('/{venta}', [ApiVentaController::class, 'update']);
    });

    // Purchases, providers, customers
    Route::prefix('compras')->group(function () {
        Route::get('/', [ApiCompraController::class, 'index']);
        Route::post('/', [ApiCompraController::class, 'store']);
        Route::put('/{compra}', [ApiCompraController::class, 'update']);
    });

    Route::prefix('proveedores')->group(function () {
        Route::get('/', [ApiProveedorController::class, 'index']);
        Route::post('/', [ApiProveedorController::class, 'store']);
        Route::put('/{proveedor}', [ApiProveedorController::class, 'update']);
    });

    Route::prefix('clientes')->group(function () {
        Route::get('/', [ApiClienteController::class, 'index']);
        Route::post('/', [ApiClienteController::class, 'store']);
        Route::put('/{cliente}', [ApiClienteController::class, 'update']);
    });

    // Inventory and transactions (admin/system)
    Route::prefix('inventarios')->group(function () {
        Route::get('/', [ApiInventarioController::class, 'index']);
    });

    Route::prefix('transacciones')->group(function () {
        Route::get('/', [ApiTransaccionController::class, 'index']);
    });

});