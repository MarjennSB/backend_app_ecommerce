<?php

namespace App\Http\Resources\Compra;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompraResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'usuario_id' => $this->usuario_id,
            'usuario_correo' => $this->usuario?->correo,
            'proveedor_id' => $this->proveedor_id,
            'proveedor_nombre' => $this->proveedor?->persona?->nombres,
            'proveedor_documento' => $this->proveedor?->persona?->numero_documento,
            'tipo_documento_comprobante_id' => $this->tipo_documento_comprobante_id,
            'tipo_documento_comprobante_nombre' => $this->tipoDocumentoComprobante?->nombre,
            'numero_comprobante' => $this->numero_comprobante,
            'costo_total' => $this->costo_total,
            'fecha_compra' => $this->fecha_compra,
            'ruta_pdf' => $this->ruta_pdf,
            'estado' => $this->estado,

            'detalles' => $this->detalles->map(function ($detalle) {
                return [
                    'id' => $detalle->id,
                    'compra_id' => $detalle->compra_id,
                    'producto_id' => $detalle->producto_id,
                    'producto_nombre' => $detalle->producto?->nombre,
                    'producto_slug' => $detalle->producto?->slug,
                    'producto_codigo_barras' => $detalle->producto?->codigo_barras,
                    'cantidad' => $detalle->cantidad,
                    'costo_unitario' => $detalle->costo_unitario,
                    'subtotal' => $detalle->subtotal,
                    'estado' => $detalle->estado,
                    'created_at' => $detalle->created_at,
                ];
            }),

            'created_at' => $this->created_at,
        ];
    }
}