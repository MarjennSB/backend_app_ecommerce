<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Genero;

class GeneroSeeder extends Seeder
{
    public function run(): void
    {

        $generos = [
            ['nombre' => 'Masculino', 'siglas' => 'M', 'estado' => true],
            ['nombre' => 'Femenino', 'siglas' => 'F', 'estado' => true],
        ];


        foreach ($generos as $genero) {
            Genero::create($genero);
        }
    }
}