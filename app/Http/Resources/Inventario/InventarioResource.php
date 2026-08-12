<?php

namespace App\Http\Resources\Inventario;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventarioResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'producto_id' => $this->producto_id,
            'producto_nombre' => $this->producto?->nombre,
            'producto_slug' => $this->producto?->slug,
            'producto_codigo_barras' => $this->producto?->codigo_barras,

            'tipo_movimiento_inventario_id' => $this->tipo_movimiento_inventario_id,
            'tipo_movimiento_nombre' => $this->tipoMovimientoInventario?->nombre,

            'cantidad' => $this->cantidad,

            'tipo_referencia' => $this->tipo_referencia,
            'referencia_id' => $this->referencia_id,

            'motivo' => $this->motivo,

            'usuario_id' => $this->usuario_id,
            'usuario_correo' => $this->usuario?->correo,

            'estado' => $this->estado,

            'created_at' => $this->created_at,
        ];
    }
}