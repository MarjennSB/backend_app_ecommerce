<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            TipoIdentidadDocumentoSeeder::class,
            GeneroSeeder::class,

            DepartamentosSeeder::class,
            ProvinciasSeeder::class,
            DistritosSeeder::class,

            TipoDocumentoComprobanteSeeder::class,
            TipoMovimientoInventarioSeeder::class,
            TipoTransaccionSeeder::class,
            TipoMetodoPagoSeeder::class,

            PermissionsDemoSeeder::class,
            CategoriaSeeder::class,
            TipoMarcaSeeder::class,
        ]);
    }
}
