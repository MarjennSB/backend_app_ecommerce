<?php

namespace App\Http\Resources\Compra;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompraResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                          => $this->id,
            'proveedor_id'                => $this->proveedor_id,
            'proveedor_nombre'            => $this->proveedor?->persona?->nombres ?? 'Proveedor desconocido',
            'usuario_nombres'             => $this->usuario?->nombres,
            'tipo_documento_comprobante_id' => $this->tipo_documento_comprobante_id,
            'tipo_documento_comprobante_nombre' => $this->tipoDocumentoComprobante?->nombre,
            'numero_comprobante'          => $this->numero_comprobante,
            'costo_total'                  => $this->costo_total,
            'fecha_compra'                  => $this->fecha_compra,
            'ruta_pdf'                       => $this->ruta_pdf,
            // Aquí mapeamos los detalles
            'detalles' => $this->whenLoaded('detalles', function () {
                return $this->detalles->map(function ($detalle) {
                    return [
                        'id'             => $detalle->id,
                        'producto_id'    => $detalle->producto_id,
                        
                        // ¡AQUÍ SACAMOS EL NOMBRE DEL PRODUCTO!
                        'producto_nombre' => $detalle->producto?->nombre ?? 'Producto desconocido',                        
                        'cantidad'       => $detalle->cantidad,
                        'costo_unitario' => $detalle->costo_unitario,
                        'subtotal'       => $detalle->subtotal,
                        'estado'         => $detalle->estado,
                        'created_at'     => $detalle->created_at?->format('Y-m-d H:i:s'),
                    ];
                });
            }),
            'estado'                      => $this->estado,
            'created_at'                  => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
