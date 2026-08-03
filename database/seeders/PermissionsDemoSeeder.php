<?php

namespace Database\Seeders;

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

        $role1 = Role::create(['guard_name' => 'api' , 'name' => 'Super-Admin']);

        // create permissions USUARIOS

        Permission::create(['guard_name' => 'api', 'name' => 'registrar_rol'])->syncRoles([$role1]);
        Permission::create(['guard_name' => 'api', 'name' => 'listar_rol'])->syncRoles([$role1]);
        Permission::create(['guard_name' => 'api', 'name' => 'editar_rol'])->syncRoles([$role1]);
        Permission::create(['guard_name' => 'api', 'name' => 'eliminar_rol'])->syncRoles([$role1]);

        Permission::create(['guard_name' => 'api', 'name' => 'registrar_usuario'])->syncRoles([$role1]);
        Permission::create(['guard_name' => 'api', 'name' => 'listar_usuario'])->syncRoles([$role1]);
        Permission::create(['guard_name' => 'api', 'name' => 'editar_usuario'])->syncRoles([$role1]);
        Permission::create(['guard_name' => 'api', 'name' => 'eliminar_usuario'])->syncRoles([$role1]);

        Permission::create(['guard_name' => 'api', 'name' => 'registrar_categoria'])->syncRoles([$role1]);
        Permission::create(['guard_name' => 'api', 'name' => 'listar_categoria'])->syncRoles([$role1]);
        Permission::create(['guard_name' => 'api', 'name' => 'editar_categoria'])->syncRoles([$role1]);
        Permission::create(['guard_name' => 'api', 'name' => 'eliminar_categoria'])->syncRoles([$role1]);

        Permission::create(['guard_name' => 'api', 'name' => 'registrar_persona'])->syncRoles([$role1]);
        Permission::create(['guard_name' => 'api', 'name' => 'listar_persona'])->syncRoles([$role1]);
        Permission::create(['guard_name' => 'api', 'name' => 'editar_persona'])->syncRoles([$role1]);
        Permission::create(['guard_name' => 'api', 'name' => 'eliminar_persona'])->syncRoles([$role1]);

        Permission::create(['guard_name' => 'api', 'name' => 'registrar_proveedor'])->syncRoles([$role1]);
        Permission::create(['guard_name' => 'api', 'name' => 'listar_proveedor'])->syncRoles([$role1]);
        Permission::create(['guard_name' => 'api', 'name' => 'editar_proveedor'])->syncRoles([$role1]);
        Permission::create(['guard_name' => 'api', 'name' => 'eliminar_proveedor'])->syncRoles([$role1]);

        Permission::create(['guard_name' => 'api', 'name' => 'registrar_cliente'])->syncRoles([$role1]);
        Permission::create(['guard_name' => 'api', 'name' => 'listar_cliente'])->syncRoles([$role1]);
        Permission::create(['guard_name' => 'api', 'name' => 'editar_cliente'])->syncRoles([$role1]);
        Permission::create(['guard_name' => 'api', 'name' => 'eliminar_cliente'])->syncRoles([$role1]);

        Permission::create(['guard_name' => 'api', 'name' => 'registrar_producto'])->syncRoles([$role1]);
        Permission::create(['guard_name' => 'api', 'name' => 'listar_producto'])->syncRoles([$role1]);
        Permission::create(['guard_name' => 'api', 'name' => 'editar_producto'])->syncRoles([$role1]);
        Permission::create(['guard_name' => 'api', 'name' => 'eliminar_producto'])->syncRoles([$role1]);

        Permission::create(['guard_name' => 'api', 'name' => 'registrar_venta'])->syncRoles([$role1]);
        Permission::create(['guard_name' => 'api', 'name' => 'listar_venta'])->syncRoles([$role1]);
        Permission::create(['guard_name' => 'api', 'name' => 'editar_venta'])->syncRoles([$role1]);
        Permission::create(['guard_name' => 'api', 'name' => 'eliminar_venta'])->syncRoles([$role1]);

        Permission::create(['guard_name' => 'api', 'name' => 'registrar_compra'])->syncRoles([$role1]);
        Permission::create(['guard_name' => 'api', 'name' => 'listar_compra'])->syncRoles([$role1]);
        Permission::create(['guard_name' => 'api', 'name' => 'editar_compra'])->syncRoles([$role1]);
        Permission::create(['guard_name' => 'api', 'name' => 'eliminar_compra'])->syncRoles([$role1]);

        Permission::create(['guard_name' => 'api', 'name' => 'registrar_inventario'])->syncRoles([$role1]);
        Permission::create(['guard_name' => 'api', 'name' => 'listar_inventario'])->syncRoles([$role1]);
        Permission::create(['guard_name' => 'api', 'name' => 'editar_inventario'])->syncRoles([$role1]);
        Permission::create(['guard_name' => 'api', 'name' => 'eliminar_inventario'])->syncRoles([$role1]);

    $user = \App\Models\Usuario::factory()->create([
            'nombres' => 'Test',
            'apellido_paterno' => 'User',
            'apellido_materno' => 'Example',
            'correo' => 'test@example.com',
            'tipo_documento_identidad_id' => 1,
            'numero_documento' => '76122795',
            'genero_id' => 1,
            'rol_id' => 1,
            'login' => 'admin',
            'password' => bcrypt('123'),
            'estado' => true,
        ]);
        $user->assignRole($role1);
    }   
}
