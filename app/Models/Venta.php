<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Venta extends Model
{
    use HasFactory, SoftDeletes;
    
    protected $table = 'ventas';

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function direccionEnvio()
    {
        return $this->belongsTo(DireccionEnvio::class, 'direccion_envio_id');
    }


    public function tipoMetodoPago()
    {
        return $this->belongsTo(TipoMetodoPago::class, 'tipo_metodo_pago_id');
    }

    public function detalles()
    {
        return $this->hasMany(DetalleVenta::class, 'venta_id');
    }

    public function comprobanteVenta()
    {
        return $this->hasOne(ComprobanteVenta::class, 'venta_id');
    }
}