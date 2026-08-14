<?php

namespace App\Http\Resources\Venta;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VentaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'usuario_id' => $this->usuario_id,
            'tipo_metodo_pago_id' => $this->tipo_metodo_pago_id,
            'tipo_metodo_pago_nombre' => $this->tipoMetodoPago?->nombre,
            'codigo_transaccion_pasarela' => $this->codigo_transaccion_pasarela,
            'subtotal' => $this->subtotal,
            'descuento_total' => $this->descuento_total,
            'costo_envio' => $this->costo_envio,
            'impuestos_igv' => $this->impuestos_igv,
            'monto_total' => $this->monto_total,
            'estado_venta' => $this->estado_venta,
            'fecha_venta' => $this->fecha_venta,
            'estado' => $this->estado,

            'detalles' => $this->detalles->map(function ($detalle) {
                return [
                    'id' => $detalle->id,
                    'venta_id' => $detalle->venta_id,
                    'producto_id' => $detalle->producto_id,
                    'producto_nombre' => $detalle->producto?->nombre,
                    'producto_slug' => $detalle->producto?->slug,
                    'producto_codigo_barras' => $detalle->producto?->codigo_barras,
                    'cantidad' => $detalle->cantidad,
                    'precio_unitario' => $detalle->precio_unitario,
                    'porcentaje_descuento' => $detalle->porcentaje_descuento,
                    'subtotal' => $detalle->subtotal,
                    'estado' => $detalle->estado,
                    'created_at' => $detalle->created_at,
                ];
            }),
            'comprobante_id' => $this->comprobanteVenta?->id,
            'comprobante_tipo_documento_id' => $this->comprobanteVenta?->tipo_documento_comprobante_id,
            'comprobante_tipo_documento_nombre' => $this->comprobanteVenta?->tipoDocumentoComprobante?->nombre,
            'comprobante_serie' => $this->comprobanteVenta?->serie_comprobante,
            'comprobante_numero' => $this->comprobanteVenta?->numero_comprobante,
            'comprobante_ruta_pdf_xml' => $this->comprobanteVenta?->ruta_pdf_xml,
            'comprobante_estado' => $this->comprobanteVenta?->estado_comprobante,
            'comprobante_fecha_emision' => $this->comprobanteVenta?->fecha_emision,
            'created_at' => $this->created_at,
        ];
    }
}