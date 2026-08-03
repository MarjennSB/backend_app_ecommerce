<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Inventario extends Model
{
    use HasFactory, SoftDeletes;
    
    protected $table = 'inventarios';

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }

    public function tipoMovimientoInventario()
    {
        // Explicitamos la llave foránea porque en plural (inventarios_id) Laravel podría no adivinarla correctamente
        return $this->belongsTo(TipoMovimientoInventario::class, 'tipo_movimiento_inventario_id');
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}