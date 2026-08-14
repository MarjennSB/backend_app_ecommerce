<?php

use App\Http\Controllers\Api\ApiCategoriaController;
use App\Http\Controllers\Api\ApiCheckoutController;
use App\Http\Controllers\Api\ApiCompraController;
use App\Http\Controllers\Api\ApiDireccionEnvioController;
use App\Http\Controllers\Api\ApiFavoritoController;
use App\Http\Controllers\Api\ApiInventarioController;
use App\Http\Controllers\Api\ApiMasterController;
use App\Http\Controllers\Api\ApiPersonaController;
use App\Http\Controllers\Api\ApiProductoController;
use App\Http\Controllers\Api\ApiProveedorController;
use App\Http\Controllers\Api\ApiResenaController;
use App\Http\Controllers\Api\ApiRoleController;
use App\Http\Controllers\Api\ApiTransaccionController;
use App\Http\Controllers\Api\ApiUsuarioController;
use App\Http\Controllers\Api\ApiVentaController;
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Rutas organizadas por Lógica de E-commerce.
| Se dividen en Públicas (Catálogo, Registro, Auth) y 
| Privadas/Protegidas (Perfil, Carrito, Checkout, Panel de Administración).
|
*/

// =====================================================================
// RUTAS PÚBLICAS (No requieren token)
// =====================================================================

// 1. Autenticación
Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->name('login');
    Route::post('register', [AuthController::class, 'register'])->name('register');
});

// 2. Catálogo del E-commerce (Productos, Categorías, Reseñas)
Route::prefix('productos')->group(function () {
    Route::get('/', [ApiProductoController::class, 'index']);
    Route::get('/{producto:slug}', [ApiProductoController::class, 'show']);
});

Route::prefix('categorias')->group(function () {
    Route::get('/', [ApiCategoriaController::class, 'index']);
    Route::get('/{categoria:slug}', [ApiCategoriaController::class, 'show']);
});

Route::prefix('resenas')->group(function () {
    // Ver reseñas de un producto
    Route::get('/', [ApiResenaController::class, 'index']);
});

// 3. Maestros / Formularios Públicos (Ubigeo, Tipos de Documento para Registro)
Route::prefix('master')->group(function () {
    Route::get('departamentos', [ApiMasterController::class, 'selectDepartamento']);
    Route::get('provincias', [ApiMasterController::class, 'selectProvincia']);
    Route::get('distritos', [ApiMasterController::class, 'selectDistrito']);
    Route::get('tipos-documento-identidad', [ApiMasterController::class, 'selectTipoDocumento']);
    Route::get('generos', [ApiMasterController::class, 'selectGenero']);
});


// =====================================================================
// RUTAS PRIVADAS (Requieren Autenticación / Token JWT)
// =====================================================================

Route::middleware('jwt.verify')->group(function () {

    // ---------------------------------------------------
    // A. RUTAS DEL CLIENTE FINAL (Tienda Virtual)
    // ---------------------------------------------------

    // Autenticación - Acciones de sesión
    Route::prefix('auth')->group(function () {
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('me', [AuthController::class, 'me'])->name('me');
        Route::post('refresh', [AuthController::class, 'refresh'])->name('refresh');
    });

    // Direcciones de Envío del Cliente
    Route::prefix('direcciones-envio')->group(function () {
        Route::get('/', [ApiDireccionEnvioController::class, 'index']);
        Route::post('/', [ApiDireccionEnvioController::class, 'store']);
        Route::put('/{direccionEnvio}', [ApiDireccionEnvioController::class, 'update']);
    });

    // Favoritos / Wishlist
    Route::prefix('favoritos')->group(function () {
        Route::get('/', [ApiFavoritoController::class, 'index']);
        Route::post('/', [ApiFavoritoController::class, 'store']);
        Route::put('/{favorito}', [ApiFavoritoController::class, 'update']);
    });

    // Checkout y Pedidos del Cliente
    Route::prefix('checkout')->group(function () {
        Route::get('mis-pedidos', [ApiCheckoutController::class, 'index']);
        Route::post('/', [ApiCheckoutController::class, 'store']);
    });

    // Dejar una reseña (solo usuarios logueados)
    Route::prefix('resenas')->group(function () {
        Route::post('/', [ApiResenaController::class, 'store']);
    });

    // ---------------------------------------------------
    // B. RUTAS DEL PANEL DE ADMINISTRACIÓN (Backoffice)
    // ---------------------------------------------------

    // Gestión de Usuarios y Roles
        
    Route::prefix('roles')->group(function () {
        Route::get('/', [ApiRoleController::class, 'index']);
        Route::post('/', [ApiRoleController::class, 'store']);
        Route::put('/{rol}', [ApiRoleController::class, 'update']);
    });

    Route::prefix('usuarios')->group(function () {
        Route::get('/', [ApiUsuarioController::class, 'index']);
        Route::post('/', [ApiUsuarioController::class, 'store']);
        Route::put('/{usuario}', [ApiUsuarioController::class, 'update']);
    });

    Route::prefix('personas')->group(function () {
        Route::get('/', [ApiPersonaController::class, 'index']);
        Route::post('/', [ApiPersonaController::class, 'store']);
        Route::put('/{persona}', [ApiPersonaController::class, 'update']);
    });

    Route::prefix('proveedores')->group(function () {
        Route::get('/', [ApiProveedorController::class, 'index']);
        Route::post('/', [ApiProveedorController::class, 'store']);
        Route::put('/{proveedor}', [ApiProveedorController::class, 'update']);
    });

    // Gestión del Catálogo (Admin)
    Route::prefix('productos')->group(function () {
        Route::post('/', [ApiProductoController::class, 'store']);
        Route::put('/{producto}', [ApiProductoController::class, 'update']);
    });

    Route::prefix('categorias')->group(function () {
        Route::post('/', [ApiCategoriaController::class, 'store']);
        Route::put('/{categoria}', [ApiCategoriaController::class, 'update']);
    });

    // Ventas Internas / POS (Admin)
    Route::prefix('ventas')->group(function () {
        Route::get('/', [ApiVentaController::class, 'index']);
        Route::post('/', [ApiVentaController::class, 'store']);
        Route::put('/{venta}', [ApiVentaController::class, 'update']);
    });

    // Compras y Proveedores (Admin)
    Route::prefix('compras')->group(function () {
        Route::get('/', [ApiCompraController::class, 'index']);
        Route::post('/', [ApiCompraController::class, 'store']);
        Route::put('/{compra}', [ApiCompraController::class, 'update']);
    });

    // Inventario y Transacciones (Admin)
    Route::prefix('inventarios')->group(function () {
        Route::get('/', [ApiInventarioController::class, 'index']);
    });

    // Transacciones (Admin)
    Route::prefix('transacciones')->group(function () {
        Route::get('/', [ApiTransaccionController::class, 'index']);
    });

    // Maestros Internos (Admin - Tipos de comprobante, transacciones, etc.)
    Route::prefix('master')->group(function () {
        Route::get('tipos-comprobante', [ApiMasterController::class, 'selectTipoDocumentoComprobante']);
        Route::get('tipos-metodo-pago', [ApiMasterController::class, 'selectTipoMetodoPago']);
        Route::get('tipos-movimiento-inventario', [ApiMasterController::class, 'selectTipoMovimientoInventario']);
        Route::get('tipos-transaccion', [ApiMasterController::class, 'selectTipoTransaccion']);
    });
});