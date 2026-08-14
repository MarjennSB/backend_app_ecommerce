<?php

namespace Database\Seeders;

use App\Models\Categoria;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategoriaSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $categorias = [
            ['nombre' => 'Electrónica',      'descripcion' => 'Dispositivos electrónicos y tecnología',                         'estado' => true],
            ['nombre' => 'Computadoras',     'descripcion' => 'Laptops, computadoras y accesorios informáticos',              'estado' => true],
            ['nombre' => 'Celulares',        'descripcion' => 'Smartphones y accesorios para celulares',                    'estado' => true],
            ['nombre' => 'Audio',            'descripcion' => 'Audífonos, parlantes y equipos de sonido',                    'estado' => true],
            ['nombre' => 'Hogar',            'descripcion' => 'Productos y artículos para el hogar',                         'estado' => true],
            ['nombre' => 'Electrodomésticos','descripcion' => 'Electrodomésticos para el hogar',                              'estado' => true],
            ['nombre' => 'Ropa',              'descripcion' => 'Prendas de vestir para damas, caballeros y niños',           'estado' => true],
            ['nombre' => 'Calzado',           'descripcion' => 'Zapatillas, zapatos, sandalias y otros calzados',             'estado' => true],
            ['nombre' => 'Accesorios',        'descripcion' => 'Accesorios personales, tecnológicos y complementos',         'estado' => true],
            ['nombre' => 'Deportes',          'descripcion' => 'Artículos y accesorios para actividades deportivas',          'estado' => true],
        ];

        foreach ($categorias as $categoria) {
            $categoria['slug'] = Str::slug($categoria['nombre']);
            Categoria::create($categoria);
        }
    }
}