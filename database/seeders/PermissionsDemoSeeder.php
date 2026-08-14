<?php

namespace Database\Seeders;

use App\Models\Usuario;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use HasRoles;

class PermissionsDemoSeeder extends Seeder
{
  /**
   * Run the database seeds.
   *
   * @return void
   */
  public function run()
  {
    // Limpiar caché de permisos
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

    //// PRIMERA PARTE
    $role1 = Role::firstOrCreate(['guard_name' => 'api', 'name' => 'ADMINISTRADOR']);
    $role2 = Role::firstOrCreate(['guard_name' => 'api', 'name' => 'USUARIO EXTERNO']);

    // create permissions

        //ROLES
        Permission::create(['guard_name' => 'api', 'name' => 'listar_rol', 'description' => 'Lista de Roles'])->syncRoles([$role1]);
        Permission::create(['guard_name' => 'api', 'name' => 'registrar_rol', 'description' => 'Registrar Roles'])->syncRoles([$role1]);
        Permission::create(['guard_name' => 'api', 'name' => 'editar_rol', 'description' => 'Editar Roles'])->syncRoles([$role1]);
        Permission::create(['guard_name' => 'api', 'name' => 'eliminar_rol', 'description' => 'Eliminar Roles'])->syncRoles([$role1]);

        //USUARIOS
        Permission::create(['guard_name' => 'api', 'name' => 'listar_usuario', 'description' => 'Lista de Usuarios'])->syncRoles([$role1]);
        Permission::create(['guard_name' => 'api', 'name' => 'registrar_usuario', 'description' => 'Registrar Usuarios'])->syncRoles([$role1]);
        Permission::create(['guard_name' => 'api', 'name' => 'editar_usuario', 'description' => 'Editar Usuarios'])->syncRoles([$role1]);
        Permission::create(['guard_name' => 'api', 'name' => 'eliminar_usuario', 'description' => 'Eliminar Usuarios'])->syncRoles([$role1]);

        //CATEGORIA
        Permission::create(['guard_name' => 'api', 'name' => 'listar_categoria', 'description' => 'Lista de Categorias'])->syncRoles([$role1, $role2]);
        Permission::create(['guard_name' => 'api', 'name' => 'registrar_categoria', 'description' => 'Registrar Categorias'])->syncRoles([$role1]);
        Permission::create(['guard_name' => 'api', 'name' => 'editar_categoria', 'description' => 'Editar Categorias'])->syncRoles([$role1]);
        Permission::create(['guard_name' => 'api', 'name' => 'ver_categoria', 'description' => 'Ver Categoria'])->syncRoles([$role1, $role2]);
        Permission::create(['guard_name' => 'api', 'name' => 'eliminar_categoria', 'description' => 'Eliminar Categorias'])->syncRoles([$role1]);

        //PRODUCTO
        Permission::create(['guard_name' => 'api', 'name' => 'listar_producto', 'description' => 'Lista de Productos'])->syncRoles([$role1, $role2]);
        Permission::create(['guard_name' => 'api', 'name' => 'registrar_producto', 'description' => 'Registrar Productos'])->syncRoles([$role1]);
        Permission::create(['guard_name' => 'api', 'name' => 'editar_producto', 'description' => 'Editar Productos'])->syncRoles([$role1]);
        Permission::create(['guard_name' => 'api', 'name' => 'eliminar_producto', 'description' => 'Eliminar Productos'])->syncRoles([$role1]);

        //COMPRA
        Permission::create(['guard_name' => 'api', 'name' => 'listar_compra', 'description' => 'Lista de Compras'])->syncRoles([$role1]);
        Permission::create(['guard_name' => 'api', 'name' => 'registrar_compra', 'description' => 'Registrar Compras'])->syncRoles([$role1]);
        Permission::create(['guard_name' => 'api', 'name' => 'editar_compra', 'description' => 'Editar Compras'])->syncRoles([$role1]);
        Permission::create(['guard_name' => 'api', 'name' => 'eliminar_compra', 'description' => 'Eliminar Compras'])->syncRoles([$role1]);

        //PROVEEDOR
        Permission::create(['guard_name' => 'api', 'name' => 'listar_proveedor', 'description' => 'Lista de Proveedores'])->syncRoles([$role1]);
        Permission::create(['guard_name' => 'api', 'name' => 'registrar_proveedor', 'description' => 'Registrar Proveedores'])->syncRoles([$role1]);
        Permission::create(['guard_name' => 'api', 'name' => 'editar_proveedor', 'description' => 'Editar Proveedores'])->syncRoles([$role1]);
        Permission::create(['guard_name' => 'api', 'name' => 'eliminar_proveedor', 'description' => 'Eliminar Proveedores'])->syncRoles([$role1]);

        //PERSONA
        Permission::create(['guard_name' => 'api', 'name' => 'listar_persona', 'description' => 'Lista de Personas'])->syncRoles([$role1]);
        Permission::create(['guard_name' => 'api', 'name' => 'registrar_persona', 'description' => 'Registrar Personas'])->syncRoles([$role1]);
        Permission::create(['guard_name' => 'api', 'name' => 'editar_persona', 'description' => 'Editar Personas'])->syncRoles([$role1]);
        Permission::create(['guard_name' => 'api', 'name' => 'eliminar_persona', 'description' => 'Eliminar Personas'])->syncRoles([$role1]);
        
        //VENTA
        Permission::create(['guard_name' => 'api', 'name' => 'listar_venta', 'description' => 'Lista de Ventas'])->syncRoles([$role1, $role2]);
        Permission::create(['guard_name' => 'api', 'name' => 'registrar_venta', 'description' => 'Registrar Ventas'])->syncRoles([$role1, $role2]);
        Permission::create(['guard_name' => 'api', 'name' => 'editar_venta', 'description' => 'Editar Ventas'])->syncRoles([$role1]);
        Permission::create(['guard_name' => 'api', 'name' => 'eliminar_venta', 'description' => 'Eliminar Ventas'])->syncRoles([$role1]);

        //CLIENTE
        Permission::create(['guard_name' => 'api', 'name' => 'listar_cliente', 'description' => 'Lista de Clientes'])->syncRoles([$role1]);
        Permission::create(['guard_name' => 'api', 'name' => 'registrar_cliente', 'description' => 'Registrar Clientes'])->syncRoles([$role1]);
        Permission::create(['guard_name' => 'api', 'name' => 'editar_cliente', 'description' => 'Editar Clientes'])->syncRoles([$role1]);
        Permission::create(['guard_name' => 'api', 'name' => 'eliminar_cliente', 'description' => 'Eliminar Clientes'])->syncRoles([$role1]);

        //INVENTARIO
        Permission::create(['guard_name' => 'api', 'name' => 'listar_inventario', 'description' => 'Lista de Inventarios'])->syncRoles([$role1]);
        Permission::create(['guard_name' => 'api', 'name' => 'registrar_inventario', 'description' => 'Registrar Inventarios'])->syncRoles([$role1]);
        Permission::create(['guard_name' => 'api', 'name' => 'editar_inventario', 'description' => 'Editar Inventarios'])->syncRoles([$role1]);
        Permission::create(['guard_name' => 'api', 'name' => 'eliminar_inventario', 'description' => 'Eliminar Inventarios'])->syncRoles([$role1]);

        //FAVORITO
        Permission::create(['guard_name' => 'api', 'name' => 'listar_favorito', 'description' => 'Lista de Favoritos'])->syncRoles([$role1, $role2]);
        Permission::create(['guard_name' => 'api', 'name' => 'registrar_favorito', 'description' => 'Registrar Favoritos'])->syncRoles([$role1, $role2]);
        Permission::create(['guard_name' => 'api', 'name' => 'eliminar_favorito',   'description' => 'Eliminar Favoritos'])->syncRoles([$role1, $role2]);

        //RESENA
        Permission::create(['guard_name' => 'api', 'name' => 'listar_resena', 'description' => 'Lista de Reseñas'])->syncRoles([$role1, $role2]);
        Permission::create(['guard_name' => 'api', 'name' => 'registrar_resena', 'description' => 'Registrar Reseñas'])->syncRoles([$role1, $role2]);
        Permission::create(['guard_name' => 'api', 'name' => 'editar_resena',   'description' => 'Editar Reseñas'])->syncRoles([$role1, $role2]);
        Permission::create(['guard_name' => 'api', 'name' => 'eliminar_resena',   'description' => 'Eliminar Reseñas'])->syncRoles([$role1, $role2]);

        //DIRECCION ENVIO
        Permission::create(['guard_name' => 'api', 'name' => 'listar_direccionenvio', 'description' => 'Lista de Direcciones de Envio'])->syncRoles([$role1, $role2]);
        Permission::create(['guard_name' => 'api', 'name' => 'registrar_direccionenvio', 'description' => 'Registrar Direcciones de Envio'])->syncRoles([$role1, $role2]);
        Permission::create(['guard_name' => 'api', 'name' => 'editar_direccionenvio',   'description' => 'Editar Direcciones de Envio'])->syncRoles([$role1, $role2]);
        Permission::create(['guard_name' => 'api', 'name' => 'eliminar_direccionenvio',   'description' => 'Eliminar Direcciones de Envio'])->syncRoles([$role1, $role2]);

        //TRANSACCION
        Permission::create(['guard_name' => 'api', 'name' => 'listar_transaccion', 'description' => 'Lista de Transacciones'])->syncRoles([$role1]);
        Permission::create(['guard_name' => 'api', 'name' => 'registrar_transaccion', 'description' => 'Registrar Transacciones'])->syncRoles([$role1]);
        Permission::create(['guard_name' => 'api', 'name' => 'editar_transaccion', 'description' => 'Editar Transacciones'])->syncRoles([$role1]);
        Permission::create(['guard_name' => 'api', 'name' => 'eliminar_transaccion', 'description' => 'Eliminar Transacciones'])->syncRoles([$role1]);

        //MASTER
        Permission::create(['guard_name' => 'api', 'name' => 'listar_master', 'description' => 'Lista de Datos Maestros'])->syncRoles([$role1]);

        // CREAR USUARIO ADMINISTRADOR POR DEFECTO
        $adminUser = Usuario::firstOrCreate(
            ['correo' => 'admin@correo.com'],
            [
                'tipo_documento_identidad_id' => 1,
                'numero_documento' => '12345678',
                'nombres' => 'Administrador',
                'apellido_paterno' => 'Sistema',
                'apellido_materno' => 'Admin',
                'numero_celular' => '999888777',
                'departamento_id' => 1,
                'provincia_id' => 15,
                'distrito_id' => 1,
                'fecha_nacimiento' => '1990-01-01',
                'genero_id' => 1,
                'password' => Hash::make('123456'),
                'acepto_termino_condiciones' => 1,
                'estado' => 1
            ]
        );
        $adminUser->assignRole($role1);
  }
};
