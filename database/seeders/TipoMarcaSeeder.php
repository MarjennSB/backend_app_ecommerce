<?php

namespace Database\Seeders;

use App\Models\TipoMarca;
use Illuminate\Database\Seeder;

class TipoMarcaSeeder extends Seeder
{
    public function run(): void
    {
        $marcas = [['nombre' => 'Samsung', 'siglas' => 'SAM', 'estado' => 1], 
                    ['nombre' => 'Apple', 'siglas' => 'APL', 'estado' => 1], 
                    ['nombre' => 'LG', 'siglas' => 'LG', 'estado' => 1], 
                    ['nombre' => 'Sony', 'siglas' => 'SNY', 'estado' => 1], 
                    ['nombre' => 'HP', 'siglas' => 'HP', 'estado' => 1], 
                    ['nombre' => 'Lenovo', 'siglas' => 'LNV', 'estado' => 1], 
                    ['nombre' => 'Dell', 'siglas' => 'DEL', 'estado' => 1], 
                    ['nombre' => 'Asus', 'siglas' => 'ASU', 'estado' => 1], 
                    ['nombre' => 'Acer', 'siglas' => 'ACR', 'estado' => 1], 
                    ['nombre' => 'Xiaomi', 'siglas' => 'XIA', 'estado' => 1]];

        foreach ($marcas as $marca) {
            TipoMarca::create($marca);
        }
    }
}
