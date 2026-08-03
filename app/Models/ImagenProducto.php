<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ImagenProducto extends Model
{
    use HasFactory, SoftDeletes;
    
    protected $table = 'imagen_productos';

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }
}