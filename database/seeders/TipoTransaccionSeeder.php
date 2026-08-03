<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\TipoTransaccion;

class TipoTransaccionSeeder extends Seeder
{
    public function run()
    {
        $tipos = [
            ['nombre' => 'Venta', 'siglas' => 'VEN', 'estado' => 1],
            ['nombre' => 'Compra', 'siglas' => 'COM', 'estado' => 1],
            ['nombre' => 'Pago', 'siglas' => 'PAG', 'estado' => 1],
            ['nombre' => 'Cobro', 'siglas' => 'COB', 'estado' => 1],
            ['nombre' => 'Devolución de Venta', 'siglas' => 'DEVVEN', 'estado' => 1],
            ['nombre' => 'Devolución de Compra', 'siglas' => 'DEVCOM', 'estado' => 1],
        ];

        foreach ($tipos as $tipo) {
            TipoTransaccion::create($tipo);
        }
    }
}