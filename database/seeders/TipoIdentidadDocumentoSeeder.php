<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\TipoDocumentoIdentidad;

class TipoIdentidadDocumentoSeeder extends Seeder
{
    public function run(): void
    {
        $tipos = [
            ['nombre' => 'DNI', 'siglas' => 'DNI', 'estado' => true, 'minimo' => 8, 'maximo' => 8],
            ['nombre' => 'Carné de extranjería', 'siglas' => 'CE', 'estado' => true, 'minimo' => 9, 'maximo' => 12],
            ['nombre' => 'Pasaporte', 'siglas' => 'PAS', 'estado' => true, 'minimo' => 6, 'maximo' => 12],
            ['nombre' => 'RUC', 'siglas' => 'RUC', 'estado' => true, 'minimo' => 11, 'maximo' => 11],
        ];

        foreach ($tipos as $tipo) {
            TipoDocumentoIdentidad::create($tipo);
        }
    }
}
