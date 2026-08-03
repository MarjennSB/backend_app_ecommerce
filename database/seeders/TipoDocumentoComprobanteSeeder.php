<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\TipoDocumentoComprobante;

class TipoDocumentoComprobanteSeeder extends Seeder
{
    public function run()
    {
        $tipos = [
            ['nombre' => 'Factura', 'siglas' => 'FAC', 'estado' => 1],
            ['nombre' => 'Boleta de Venta', 'siglas' => 'BOL', 'estado' => 1],
            ['nombre' => 'Nota de Crédito', 'siglas' => 'NC', 'estado' => 1],
            ['nombre' => 'Nota de Débito', 'siglas' => 'ND', 'estado' => 1],
            ['nombre' => 'Guía de Remisión', 'siglas' => 'GR', 'estado' => 1],
        ];

        foreach ($tipos as $tipo) {
            TipoDocumentoComprobante::create($tipo);
        }
    }
}