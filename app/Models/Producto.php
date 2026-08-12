<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Producto extends Model
{
    use HasFactory, SoftDeletes;
    
    protected $table = 'productos';

    public function categoria()
    {
        return $this->belongsTo(Categoria::class, 'categoria_id');
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function tipoMarca()
    {
        return $this->belongsTo(TipoMarca::class, 'tipo_marca_id');
    }

    public function imagenes()
    {
        // ✅ CORRECTO: La llave foránea en tu tabla es 'producto_id'
        return $this->hasMany(ImagenProducto::class, 'producto_id');
    }

    /**
     * Use slug for route model binding in public endpoints
     */
    public function getRouteKeyName()
    {
        return 'slug';
    }
}