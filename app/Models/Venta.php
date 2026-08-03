<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Venta extends Model
{
    use HasFactory, SoftDeletes;
    
    protected $table = 'ventas';

    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function tipoDocumentoComprobante()
    {
        return $this->belongsTo(TipoDocumentoComprobante::class, 'tipo_documento_comprobante_id');
    }

    public function tipoMetodoPago()
    {
        return $this->belongsTo(TipoMetodoPago::class, 'tipo_metodo_pago_id');
    }

    public function detalles()
    {
        return $this->hasMany(DetalleVenta::class, 'venta_id');
    }

}