<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\TipoMovimientoInventario;

class TipoMovimientoInventarioSeeder extends Seeder
{
    public function run()
    {
        $tipos = [
            ['nombre' => 'Ingreso por Compra', 'siglas' => 'COMPRA', 'estado' => 1],
            ['nombre' => 'Salida por Venta', 'siglas' => 'VENTA', 'estado' => 1],
            ['nombre' => 'Ajuste de Inventario', 'siglas' => 'AJUSTE', 'estado' => 1],
            ['nombre' => 'Devolución de Cliente', 'siglas' => 'DEVCLI', 'estado' => 1],
            ['nombre' => 'Devolución a Proveedor', 'siglas' => 'DEVPRO', 'estado' => 1],
            ['nombre' => 'Traslado de Almacén', 'siglas' => 'TRAS', 'estado' => 1],
        ];

        foreach ($tipos as $tipo) {
            TipoMovimientoInventario::create($tipo);
        }
    }
}