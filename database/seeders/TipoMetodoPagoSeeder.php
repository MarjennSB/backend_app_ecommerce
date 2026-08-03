<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\TipoMetodoPago;

class TipoMetodoPagoSeeder extends Seeder
{
    public function run()
    {
        $metodos = [
            ['nombre' => 'Efectivo', 'siglas' => 'EFE', 'estado' => 1],
            ['nombre' => 'Tarjeta de Crédito', 'siglas' => 'TC', 'estado' => 1],
            ['nombre' => 'Tarjeta de Débito', 'siglas' => 'TD', 'estado' => 1],
            ['nombre' => 'Transferencia Bancaria', 'siglas' => 'TRANS', 'estado' => 1],
            ['nombre' => 'Yape', 'siglas' => 'YAPE', 'estado' => 1],
            ['nombre' => 'Plin', 'siglas' => 'PLIN', 'estado' => 1],
            ['nombre' => 'Depósito Bancario', 'siglas' => 'DEP', 'estado' => 1],
        ];

        foreach ($metodos as $metodo) {
            TipoMetodoPago::create($metodo);
        }
    }
}