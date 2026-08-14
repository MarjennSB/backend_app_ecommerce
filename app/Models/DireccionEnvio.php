<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DireccionEnvio extends Model
{
    use HasFactory, SoftDeletes;
    
    protected $table = 'direccion_envios';

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}