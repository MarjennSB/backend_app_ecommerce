<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Departamento;

class DepartamentosSeeder extends Seeder
{
    public function run()
    {
        $departamentos = [
            ['descripcion' => 'Amazonas', 'estado' => 1],
            ['descripcion' => 'Áncash', 'estado' => 1],
            ['descripcion' => 'Apurímac', 'estado' => 1],
            ['descripcion' => 'Arequipa', 'estado' => 1],
            ['descripcion' => 'Ayacucho', 'estado' => 1],
            ['descripcion' => 'Cajamarca', 'estado' => 1],
            ['descripcion' => 'Cusco', 'estado' => 1],
            ['descripcion' => 'Huancavelica', 'estado' => 1],
            ['descripcion' => 'Huánuco', 'estado' => 1],
            ['descripcion' => 'Ica', 'estado' => 1],
            ['descripcion' => 'Junín', 'estado' => 1],
            ['descripcion' => 'La Libertad', 'estado' => 1],
            ['descripcion' => 'Lambayeque', 'estado' => 1],
            ['descripcion' => 'Lima', 'estado' => 1],
            ['descripcion' => 'Loreto', 'estado' => 1],
            ['descripcion' => 'Madre de Dios', 'estado' => 1],
            ['descripcion' => 'Moquegua', 'estado' => 1],
            ['descripcion' => 'Pasco', 'estado' => 1],
            ['descripcion' => 'Piura', 'estado' => 1],
            ['descripcion' => 'Puno', 'estado' => 1],
            ['descripcion' => 'San Martín', 'estado' => 1],
            ['descripcion' => 'Tacna', 'estado' => 1],
            ['descripcion' => 'Tumbes', 'estado' => 1],
            ['descripcion' => 'Ucayali', 'estado' => 1],
        ];

        foreach ($departamentos as $dep) {
            Departamento::create($dep);
        }
    }
}