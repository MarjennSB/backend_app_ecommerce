<?php

namespace App\Http\Resources\Venta;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VentaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                                => $this->id,
            
            // Relación con Cliente (Asumiendo que pasa por Persona igual que Proveedor)
            'cliente_id'                        => $this->cliente_id,
            'cliente_nombre'                    => $this->cliente?->persona?->nombres . ' ' . $this->cliente?->persona?->apellido_paterno,
            
            'usuario_id'                        => $this->usuario_id,
            'usuario_nombres'                   => $this->usuario?->nombres,
            
            'tipo_documento_comprobante_id'     => $this->tipo_documento_comprobante_id,
            'tipo_documento_comprobante_nombre' => $this->tipoDocumentoComprobante?->nombre,
            
            'numero_comprobante'                => $this->numero_comprobante,
            
            // Ya no lo calculamos aquí, lo devolvemos tal cual porque el backend lo guardará calculado
            'precio_total'                      => $this->precio_total, 
            
            // Fecha como texto simple (como me pediste)
            'fecha_venta'                       => $this->fecha_venta,
            
            // Nueva tabla: Método de Pago
            'tipo_metodo_pago_id'               => $this->tipo_metodo_pago_id,
            'tipo_metodo_pago_nombre'           => $this->tipoMetodoPago?->nombre,
            
            'ruta_pdf'                          => $this->ruta_pdf ? url('storage/' . $this->ruta_pdf) : null,
            
            // Mapeo anidado de los detalles de la venta
            'detalles' => $this->whenLoaded('detalles', function () {
                return $this->detalles->map(function ($detalle) {
                    return [
                        'id'              => $detalle->id,
                        'producto_id'     => $detalle->producto_id,
                        'producto_nombre' => $detalle->producto?->nombre ?? 'Producto desconocido',
                        'cantidad'        => $detalle->cantidad,
                        'precio_unitario' => $detalle->precio_unitario, // Nota: aquí se llama precio_unitario (en compras era costo_unitario)
                        'subtotal'        => $detalle->subtotal,
                        'estado'          => $detalle->estado,
                        'created_at'      => $detalle->created_at?->format('Y-m-d H:i:s'),
                    ];
                });
            }),
            
            'estado'                            => $this->estado,
            'created_at'                        => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}