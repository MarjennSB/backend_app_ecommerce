<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaccion extends Model
{
    use HasFactory, SoftDeletes;
    
    protected $table = 'transacciones';

    public function tipoTransaccion()
    {
        return $this->belongsTo(TipoTransaccion::class, 'tipo_transaccion_id');
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}