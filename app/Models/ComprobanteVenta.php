<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ComprobanteVenta extends Model
{
    use HasFactory, SoftDeletes;
    
    protected $table = 'comprobante_ventas';

    public function venta()
    {
        return $this->belongsTo(Venta::class, 'venta_id');
    }

    public function tipoDocumentoComprobante()
    {
        return $this->belongsTo(TipoDocumentoComprobante::class, 'tipo_documento_comprobante_id');
    }
}