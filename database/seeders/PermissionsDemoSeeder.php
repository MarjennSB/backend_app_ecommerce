<?php

namespace Database\Seeders;

use App\Models\Persona;
use App\Models\Usuario;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionsDemoSeeder extends Seeder
{

/**
     * Create the initial roles and permissions.
     *
     * @return void
     */
    public function run()
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $roleAdmin   = Role::firstOrCreate(['guard_name' => 'api', 'name' => 'Administrador']);
        $roleSeller  = Role::firstOrCreate(['guard_name' => 'api', 'name' => 'Vendedor']);
        $roleClient  = Role::firstOrCreate(['guard_name' => 'api', 'name' => 'Cliente']);

        // Define permissions sets
        $adminPermissions = [
            // Roles & users
            'registrar_rol','listar_rol','ver_rol','editar_rol','eliminar_rol',
            'registrar_usuario','listar_usuario','editar_usuario','eliminar_usuario',
            // Personas
            'registrar_persona','listar_persona','editar_persona','eliminar_persona',
            // Categorias
            'registrar_categoria','listar_categoria','ver_categoria','editar_categoria','eliminar_categoria',
            // Productos
            'registrar_producto','listar_producto','ver_producto','editar_producto','eliminar_producto',
            // Proveedores / Clientes
            'registrar_proveedor','listar_proveedor','editar_proveedor','eliminar_proveedor',
            'registrar_cliente','listar_cliente','editar_cliente','eliminar_cliente',
            // Ventas / Compras
            'registrar_venta','listar_venta','ver_venta','editar_venta','eliminar_venta',
            'registrar_compra','listar_compra','editar_compra','eliminar_compra',
            // Inventario / Transacciones
            'registrar_inventario','listar_inventario','editar_inventario','eliminar_inventario',
            'registrar_transaccion','listar_transaccion','editar_transaccion',
            // Favoritos / Carritos / Reseñas / Master
            'registrar_favorito','listar_favorito','editar_favorito',
            'registrar_carrito','listar_carrito','editar_carrito',
            'registrar_resena','listar_resena','editar_resena',
            'registrar_master','listar_master','editar_master',
        ];

        $sellerPermissions = [
            // Sellers can list and manage products they own (enforced later with policies if needed)
            'listar_producto','registrar_producto','editar_producto','eliminar_producto',
            'listar_venta','ver_venta','registrar_venta',
        ];

        $clientPermissions = [
            // Clients (end customers) - minimal global permissions. Owner-scoped actions must be enforced by policies/guards.
            'registrar_venta', // allow creating purchases
            'ver_perfil',      // semantic permission to allow client to view/update own profile (enforced by policy)
            'editar_perfil',
            // Cart and favorites
            'registrar_carrito','listar_carrito','registrar_favorito','listar_favorito',
            // Read-only access (optional - public endpoints already allow this without auth)
            'listar_producto','ver_producto','listar_categoria','ver_categoria','listar_resena'
        ];

        // Create all permissions and assign to roles accordingly
        foreach ($adminPermissions as $perm) {
            Permission::firstOrCreate(['guard_name' => 'api', 'name' => $perm])->syncRoles([$roleAdmin]);
        }

        foreach ($sellerPermissions as $perm) {
            Permission::firstOrCreate(['guard_name' => 'api', 'name' => $perm])->syncRoles([$roleSeller, $roleAdmin]);
        }

        foreach ($clientPermissions as $perm) {
            Permission::firstOrCreate(['guard_name' => 'api', 'name' => $perm])->syncRoles([$roleClient, $roleAdmin]);
        }

        // Create a demo admin user (existing behaviour)
        $persona = Persona::create([
            'tipo_documento_identidad_id' => 1,
            'numero_documento' => '76122795',
            'nombres' => 'Test',
            'apellido_paterno' => 'User',
            'apellido_materno' => 'Example',
            'numero_celular' => '967043422',
            'departamento_id' => '1',
            'provincia_id' => '2',
            'distrito_id' => '10',
            'fecha_nacimiento' => '2003-12-29',
            'genero_id' => 1,
            'estado' => 1,
        ]);

        // Admin user
        $user = Usuario::create([
            'correo' => 'test@example.com',
            'login' => 'admin',
            'password' => bcrypt('123'),
            'persona_id' => $persona->id,
            'rol_id' => $roleAdmin->id,
            'estado' => 1,
        ]);

        $user->assignRole($roleAdmin);

        // Create a demo client user for testing
        $personaClient = Persona::create([
            'tipo_documento_identidad_id' => 1,
            'numero_documento' => '76543210',
            'nombres' => 'Cliente',
            'apellido_paterno' => 'Demo',
            'apellido_materno' => 'User',
            'numero_celular' => '987654321',
            'departamento_id' => '1',
            'provincia_id' => '2',
            'distrito_id' => '10',
            'fecha_nacimiento' => '1990-01-01',
            'genero_id' => 1,
            'estado' => 1,
        ]);

        $clientUser = Usuario::create([
            'correo' => 'cliente@example.com',
            'login' => 'cliente',
            'password' => bcrypt('cliente123'),
            'persona_id' => $personaClient->id,
            'rol_id' => $roleClient->id,
            'estado' => 1,
        ]);

        $clientUser->assignRole($roleClient);

    }
}
