<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\TipoMetodoPago;

class TipoMetodoPagoSeeder extends Seeder
{
    public function run()
    {
        $metodos = [
            ['nombre' => 'Efectivo', 'requiere_pasarela' => false, 'estado' => 1],
            ['nombre' => 'Tarjeta de Crédito', 'requiere_pasarela' => true, 'estado' => 1],
            ['nombre' => 'Tarjeta de Débito', 'requiere_pasarela' => true, 'estado' => 1],
            ['nombre' => 'Transferencia Bancaria', 'requiere_pasarela' => false, 'estado' => 1],
            ['nombre' => 'Yape', 'requiere_pasarela' => false, 'estado' => 1],
            ['nombre' => 'Plin', 'requiere_pasarela' => false, 'estado' => 1],
            ['nombre' => 'Depósito Bancario', 'requiere_pasarela' => false, 'estado' => 1],
        ];

        foreach ($metodos as $metodo) {
            TipoMetodoPago::create($metodo);
        }
    }
}