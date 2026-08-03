<?php

use App\Http\Controllers\Api\ApiCategoriaController;
use App\Http\Controllers\Api\ApiClienteController;
use App\Http\Controllers\Api\ApiCompraController;
use App\Http\Controllers\Api\ApiInventarioController;
use App\Http\Controllers\Api\ApiPersonaController;
use App\Http\Controllers\Api\ApiProductoController;
use App\Http\Controllers\Api\ApiProveedorController;
use App\Http\Controllers\Api\ApiRoleController;
use App\Http\Controllers\Api\ApiTransaccionController;
use App\Http\Controllers\Api\ApiUsuarioController;
use App\Http\Controllers\Api\ApiVentaController;
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::group([

    //'middleware' => 'auth:api',
    'prefix' => 'auth',
    // 'middleware' => ['role:admin','permission:publish articles'],
], function ($router) {
    Route::post('login', [AuthController::class, 'login'])->name('login');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/me', [AuthController::class, 'me'])->name('me');  
    Route::post('/refresh', [AuthController::class, 'refresh'])->name('refresh');
});

Route::group([
    'middleware' => 'jwt.verify',
], function ($router) {
    Route::resource('roles', ApiRoleController::class);

    Route::prefix('categorias')->group(function () {
        Route::controller(ApiCategoriaController::class)->group(function () {
            Route::get('/', 'index');
            Route::post('/', 'store');
            Route::put('/{categoria}', 'update');
        });
    });

    Route::prefix('usuarios')->group(function () {
        Route::controller(ApiUsuarioController::class)->group(function () {
            Route::get('/',             'index');
            Route::post('/',            'store');
            Route::put('/{usuario}',    'update');
        });
    });
    
    Route::prefix('personas')->group(function () {
        Route::controller(ApiPersonaController::class)->group(function () {
            Route::get('/',             'index');
            Route::post('/',            'store');
            Route::put('/{persona}',    'update');
        });
    });

    Route::prefix('proveedores')->group(function () {
        Route::controller(ApiProveedorController::class)->group(function () {
            Route::get('/',             'index');
            Route::post('/',            'store');
            Route::put('/{proveedor}',  'update');
        });
    });

    Route::prefix('clientes')->group(function () {
        Route::controller(ApiClienteController::class)->group(function () {
            Route::get('/',             'index');
            Route::post('/',            'store');
            Route::put('/{cliente}',    'update');
        });
    });

    Route::prefix('productos')->group(function () {
        Route::controller(ApiProductoController::class)->group(function () {
            Route::get('/',             'index');
            Route::post('/',            'store');
            Route::put('/{producto}',   'update');
        });
    });

    Route::prefix('compras')->group(function () {
        Route::controller(ApiCompraController::class)->group(function () {
            Route::get('/',             'index');
            Route::post('/',            'store');
            Route::put('/{compra}',     'update');
        });
    });

    Route::prefix('ventas')->group(function () {
        Route::controller(ApiVentaController::class)->group(function () {
            Route::get('/',             'index');
            Route::post('/',            'store');
            Route::put('/{venta}',      'update');
        });
    });

    Route::prefix('inventarios')->group(function () {
        Route::controller(ApiInventarioController::class)->group(function () {
            Route::get('/',             'index');
            /* Route::post('/',            'store');
            Route::put('/{inventario}', 'update'); */
        });
    });

    Route::prefix('transacciones')->group(function () {
        Route::controller(ApiTransaccionController::class)->group(function () {
            Route::get('/',             'index');
            /* Route::post('/',            'store');
            Route::put('/{transaccion}', 'update'); */
        });
    });
});