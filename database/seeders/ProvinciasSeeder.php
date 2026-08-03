<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Departamento;
use App\Models\Provincia;

class ProvinciasSeeder extends Seeder
{
    public function run()
    {
        $provincias = [
            // Provincias para el departamento Amazonas
            'Amazonas' => [
                ['descripcion' => 'Bagua', 'estado' => 1],
                ['descripcion' => 'Bongará', 'estado' => 1],
                ['descripcion' => 'Chachapoyas', 'estado' => 1],
                ['descripcion' => 'Condorcanqui', 'estado' => 1],
                ['descripcion' => 'Luya', 'estado' => 1],
                ['descripcion' => 'Rodríguez de Mendoza', 'estado' => 1],
                ['descripcion' => 'Utcubamba', 'estado' => 1],
            ],
            // Provincias para el departamento Áncash
            'Áncash' => [
                ['descripcion' => 'Aija', 'estado' => 1],
                ['descripcion' => 'Antonio Raimondi', 'estado' => 1],
                ['descripcion' => 'Asunción', 'estado' => 1],
                ['descripcion' => 'Bolognesi', 'estado' => 1],
                ['descripcion' => 'Carhuaz', 'estado' => 1],
                ['descripcion' => 'Carlos Fermín Fitzcarrald', 'estado' => 1],
                ['descripcion' => 'Casma', 'estado' => 1],
                ['descripcion' => 'Corongo', 'estado' => 1],
                ['descripcion' => 'Huaraz', 'estado' => 1],
                ['descripcion' => 'Huari', 'estado' => 1],
                ['descripcion' => 'Huarmey', 'estado' => 1],
                ['descripcion' => 'Huaylas', 'estado' => 1],
                ['descripcion' => 'Mariscal Luzuriaga', 'estado' => 1],
                ['descripcion' => 'Ocros', 'estado' => 1],
                ['descripcion' => 'Pallasca', 'estado' => 1],
                ['descripcion' => 'Pomabamba', 'estado' => 1],
                ['descripcion' => 'Recuay', 'estado' => 1],
                ['descripcion' => 'Santa', 'estado' => 1],
                ['descripcion' => 'Sihuas', 'estado' => 1],
                ['descripcion' => 'Yungay', 'estado' => 1],
            ],
            // Provincias para el departamento Apurímac
            'Apurímac' => [
                ['descripcion' => 'Abancay', 'estado' => 1],
                ['descripcion' => 'Andahuaylas', 'estado' => 1],
                ['descripcion' => 'Antabamba', 'estado' => 1],
                ['descripcion' => 'Aymaraes', 'estado' => 1],
                ['descripcion' => 'Chincheros', 'estado' => 1],
                ['descripcion' => 'Cotabambas', 'estado' => 1],
                ['descripcion' => 'Grau', 'estado' => 1],
            ],

            'Arequipa' => [
                ['descripcion' => 'Arequipa', 'estado' => 1],
                ['descripcion' => 'Camaná', 'estado' => 1],
                ['descripcion' => 'Caravelí', 'estado' => 1],
                ['descripcion' => 'Castilla', 'estado' => 1],
                ['descripcion' => 'Caylloma', 'estado' => 1],
                ['descripcion' => 'Condesuyos', 'estado' => 1],
                ['descripcion' => 'Islay', 'estado' => 1],
                ['descripcion' => 'La Unión', 'estado' => 1],
            ],

            'Ayacucho' => [
                ['descripcion' => 'Cangallo', 'estado' => 1],
                ['descripcion' => 'Huamanga', 'estado' => 1],
                ['descripcion' => 'Huanca Sancos', 'estado' => 1],
                ['descripcion' => 'Huanta', 'estado' => 1],
                ['descripcion' => 'La Mar', 'estado' => 1],
                ['descripcion' => 'Lucanas', 'estado' => 1],
                ['descripcion' => 'Parinacochas', 'estado' => 1],
                ['descripcion' => 'Páucar del Sara Sara', 'estado' => 1],
                ['descripcion' => 'Sucre', 'estado' => 1],
                ['descripcion' => 'Víctor Fajardo', 'estado' => 1],
                ['descripcion' => 'Vilcas Huamán', 'estado' => 1],
            ],

            'Cajamarca' => [
                ['descripcion' => 'Cajabamba', 'estado' => 1],
                ['descripcion' => 'Cajamarca', 'estado' => 1],
                ['descripcion' => 'Celendín', 'estado' => 1],
                ['descripcion' => 'Chota', 'estado' => 1],
                ['descripcion' => 'Contumazá', 'estado' => 1],
                ['descripcion' => 'Cutervo', 'estado' => 1],
                ['descripcion' => 'Hualgayoc', 'estado' => 1],
                ['descripcion' => 'Jaén', 'estado' => 1],
                ['descripcion' => 'San Ignacio', 'estado' => 1],
                ['descripcion' => 'San Marcos', 'estado' => 1],
                ['descripcion' => 'San Miguel', 'estado' => 1],
                ['descripcion' => 'San Pablo', 'estado' => 1],
                ['descripcion' => 'Santa Cruz', 'estado' => 1],
            ],

            'Cusco' => [
                ['descripcion' => 'Acomayo', 'estado' => 1],
                ['descripcion' => 'Anta', 'estado' => 1],
                ['descripcion' => 'Calca', 'estado' => 1],
                ['descripcion' => 'Canas', 'estado' => 1],
                ['descripcion' => 'Canchis', 'estado' => 1],
                ['descripcion' => 'Chumbivilcas', 'estado' => 1],
                ['descripcion' => 'Cusco', 'estado' => 1],
                ['descripcion' => 'Espinar', 'estado' => 1],
                ['descripcion' => 'La Convención', 'estado' => 1],
                ['descripcion' => 'Paruro', 'estado' => 1],
                ['descripcion' => 'Paucartambo', 'estado' => 1],
                ['descripcion' => 'Quispicanchi', 'estado' => 1],
                ['descripcion' => 'Urubamba', 'estado' => 1],
            ],

            // Provincias para el departamento Huancavelica
'Huancavelica' => [
    ['descripcion' => 'Acobamba', 'estado' => 1],
    ['descripcion' => 'Angaraes', 'estado' => 1],
    ['descripcion' => 'Castrovirreyna', 'estado' => 1],
    ['descripcion' => 'Churcampa', 'estado' => 1],
    ['descripcion' => 'Huancavelica', 'estado' => 1],
    ['descripcion' => 'Huaytará', 'estado' => 1],
    ['descripcion' => 'Tayacaja', 'estado' => 1],
],

// Provincias para el departamento Huánuco
'Huánuco' => [
    ['descripcion' => 'Ambo', 'estado' => 1],
    ['descripcion' => 'Dos de Mayo', 'estado' => 1],
    ['descripcion' => 'Huacaybamba', 'estado' => 1],
    ['descripcion' => 'Huamalíes', 'estado' => 1],
    ['descripcion' => 'Huánuco', 'estado' => 1],
    ['descripcion' => 'Lauricocha', 'estado' => 1],
    ['descripcion' => 'Leoncio Prado', 'estado' => 1],
    ['descripcion' => 'Marañón', 'estado' => 1],
    ['descripcion' => 'Pachitea', 'estado' => 1],
    ['descripcion' => 'Puerto Inca', 'estado' => 1],
    ['descripcion' => 'Yarowilca', 'estado' => 1],
],

// Provincias para el departamento Ica
'Ica' => [
    ['descripcion' => 'Chincha', 'estado' => 1],
    ['descripcion' => 'Ica', 'estado' => 1],
    ['descripcion' => 'Nasca', 'estado' => 1],
    ['descripcion' => 'Palpa', 'estado' => 1],
    ['descripcion' => 'Pisco', 'estado' => 1],
],

// Provincias para el departamento Junín
'Junín' => [
    ['descripcion' => 'Chanchamayo', 'estado' => 1],
    ['descripcion' => 'Chupaca', 'estado' => 1],
    ['descripcion' => 'Concepción', 'estado' => 1],
    ['descripcion' => 'Huancayo', 'estado' => 1],
    ['descripcion' => 'Jauja', 'estado' => 1],
    ['descripcion' => 'Junín', 'estado' => 1],
    ['descripcion' => 'Satipo', 'estado' => 1],
    ['descripcion' => 'Tarma', 'estado' => 1],
    ['descripcion' => 'Yauli', 'estado' => 1],
],

// Provincias para el departamento La Libertad
'La Libertad' => [
    ['descripcion' => 'Ascope', 'estado' => 1],
    ['descripcion' => 'Bolívar', 'estado' => 1],
    ['descripcion' => 'Chepén', 'estado' => 1],
    ['descripcion' => 'Gran Chimú', 'estado' => 1],
    ['descripcion' => 'Julcán', 'estado' => 1],
    ['descripcion' => 'Otuzco', 'estado' => 1],
    ['descripcion' => 'Pacasmayo', 'estado' => 1],
    ['descripcion' => 'Pataz', 'estado' => 1],
    ['descripcion' => 'Sánchez Carrión', 'estado' => 1],
    ['descripcion' => 'Santiago de Chuco', 'estado' => 1],
    ['descripcion' => 'Trujillo', 'estado' => 1],
    ['descripcion' => 'Virú', 'estado' => 1],
],

// Provincias para el departamento Lambayeque
'Lambayeque' => [
    ['descripcion' => 'Chiclayo', 'estado' => 1],
    ['descripcion' => 'Ferreñafe', 'estado' => 1],
    ['descripcion' => 'Lambayeque', 'estado' => 1],
],

// Provincias para el departamento Lima
'Lima' => [
    ['descripcion' => 'Barranca', 'estado' => 1],
    ['descripcion' => 'Cajatambo', 'estado' => 1],
    ['descripcion' => 'Canta', 'estado' => 1],
    ['descripcion' => 'Cañete', 'estado' => 1],
    ['descripcion' => 'Huaral', 'estado' => 1],
    ['descripcion' => 'Huarochirí', 'estado' => 1],
    ['descripcion' => 'Huaura', 'estado' => 1],
    ['descripcion' => 'Lima Metropolitana', 'estado' => 1],
    ['descripcion' => 'Oyón', 'estado' => 1],
    ['descripcion' => 'Yauyos', 'estado' => 1],
    ['descripcion' => 'Callao', 'estado' => 1],
],

// Provincias para el departamento Loreto
'Loreto' => [
    ['descripcion' => 'Alto Amazonas', 'estado' => 1],
    ['descripcion' => 'Datem del Marañón', 'estado' => 1],
    ['descripcion' => 'Loreto', 'estado' => 1],
    ['descripcion' => 'Mariscal Ramón Castilla', 'estado' => 1],
    ['descripcion' => 'Maynas', 'estado' => 1],
    ['descripcion' => 'Putumayo', 'estado' => 1],
    ['descripcion' => 'Requena', 'estado' => 1],
    ['descripcion' => 'Ucayali', 'estado' => 1],
],

// Provincias para el departamento Madre de Dios
'Madre de Dios' => [
    ['descripcion' => 'Manu', 'estado' => 1],
    ['descripcion' => 'Tahuamanu', 'estado' => 1],
    ['descripcion' => 'Tambopata', 'estado' => 1],
],

// Provincias para el departamento Moquegua
'Moquegua' => [
    ['descripcion' => 'General Sánchez Cerro', 'estado' => 1],
    ['descripcion' => 'Ilo', 'estado' => 1],
    ['descripcion' => 'Mariscal Nieto', 'estado' => 1],
],

// Provincias para el departamento Pasco
'Pasco' => [
    ['descripcion' => 'Daniel Alcides Carrión', 'estado' => 1],
    ['descripcion' => 'Oxapampa', 'estado' => 1],
    ['descripcion' => 'Pasco', 'estado' => 1],
],

// Provincias para el departamento Piura
'Piura' => [
    ['descripcion' => 'Ayabaca', 'estado' => 1],
    ['descripcion' => 'Huancabamba', 'estado' => 1],
    ['descripcion' => 'Morropón', 'estado' => 1],
    ['descripcion' => 'Paita', 'estado' => 1],
    ['descripcion' => 'Piura', 'estado' => 1],
    ['descripcion' => 'Sechura', 'estado' => 1],
    ['descripcion' => 'Sullana', 'estado' => 1],
    ['descripcion' => 'Talara', 'estado' => 1],
],

// Provincias para el departamento Puno
'Puno' => [
    ['descripcion' => 'Azángaro', 'estado' => 1],
    ['descripcion' => 'Carabaya', 'estado' => 1],
    ['descripcion' => 'Chucuito', 'estado' => 1],
    ['descripcion' => 'El Collao', 'estado' => 1],
    ['descripcion' => 'Huancané', 'estado' => 1],
    ['descripcion' => 'Lampa', 'estado' => 1],
    ['descripcion' => 'Melgar', 'estado' => 1],
    ['descripcion' => 'Moho', 'estado' => 1],
    ['descripcion' => 'Puno', 'estado' => 1],
    ['descripcion' => 'San Antonio de Putina', 'estado' => 1],
    ['descripcion' => 'San Román', 'estado' => 1],
    ['descripcion' => 'Sandia', 'estado' => 1],
    ['descripcion' => 'Yunguyo', 'estado' => 1],
],

// Provincias para el departamento San Martín
'San Martín' => [
    ['descripcion' => 'Bellavista', 'estado' => 1],
    ['descripcion' => 'El Dorado', 'estado' => 1],
    ['descripcion' => 'Huallaga', 'estado' => 1],
    ['descripcion' => 'Lamas', 'estado' => 1],
    ['descripcion' => 'Mariscal Cáceres', 'estado' => 1],
    ['descripcion' => 'Moyobamba', 'estado' => 1],
    ['descripcion' => 'Picota', 'estado' => 1],
    ['descripcion' => 'Rioja', 'estado' => 1],
    ['descripcion' => 'San Martín', 'estado' => 1],
    ['descripcion' => 'Tocache', 'estado' => 1],
],

// Provincias para el departamento Tacna
'Tacna' => [
    ['descripcion' => 'Candarave', 'estado' => 1],
    ['descripcion' => 'Jorge Basadre', 'estado' => 1],
    ['descripcion' => 'Tacna', 'estado' => 1],
    ['descripcion' => 'Tarata', 'estado' => 1],
],

// Provincias para el departamento Tumbes
'Tumbes' => [
    ['descripcion' => 'Contralmirante Villar', 'estado' => 1],
    ['descripcion' => 'Tumbes', 'estado' => 1],
    ['descripcion' => 'Zarumilla', 'estado' => 1],
],

// Provincias para el departamento Ucayali
'Ucayali' => [
    ['descripcion' => 'Atalaya', 'estado' => 1],
    ['descripcion' => 'Coronel Portillo', 'estado' => 1],
    ['descripcion' => 'Padre Abad', 'estado' => 1],
    ['descripcion' => 'Purús', 'estado' => 1],
],

        ];

        foreach ($provincias as $depDescripcion => $provArray) {
            // Se busca el departamento por su descripción
            $departamento = Departamento::where('descripcion', $depDescripcion)->first();
            if ($departamento) {
                foreach ($provArray as $prov) {
                    $prov['departamento_id'] = $departamento->id;
                    Provincia::create($prov);
                }
            } else {
                // En caso de que el departamento no exista, se crea y luego se asocian las provincias
                $departamento = Departamento::create(['descripcion' => $depDescripcion, 'estado' => 1]);
                foreach ($provArray as $prov) {
                    $prov['departamento_id'] = $departamento->id;
                    Provincia::create($prov);
                }
            }
        }
    }
}