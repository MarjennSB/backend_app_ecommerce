<?php

namespace App\Http\Resources\Inventario;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventarioResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                             => $this->id,
            
            // Datos del Producto
            'producto_id'                    => $this->producto_id,
            'producto_nombre'                => $this->producto?->nombre,
            
            // Datos del Movimiento (Ingreso, Salida, etc.)
            'tipo_movimiento_id'             => $this->tipo_movimiento_inventario_id,
            'tipo_movimiento_nombre'         => $this->tipoMovimientoInventario?->nombre,
            'tipo_movimiento_siglas'         => $this->tipoMovimientoInventario?->siglas,
            
            // Detalles de la operación
            'cantidad'                       => $this->cantidad,
            'tipo_referencia'                => $this->tipo_referencia, // Ej: 'Venta', 'Compra'
            'referencia_id'                  => $this->referencia_id,   // Ej: ID de la venta
            'motivo'                         => $this->motivo,
            
            // Auditoría
            'usuario_id'                     => $this->usuario_id,
            'usuario_nombre'                 => $this->usuario?->nombres . ' ' . $this->usuario?->apellido_paterno,
            'estado'                         => $this->estado,
            
            // La fecha exacta en la que ocurrió el movimiento
            'created_at'               => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}