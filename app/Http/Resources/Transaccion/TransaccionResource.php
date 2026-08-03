<?php

namespace App\Http\Resources\Transaccion;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransaccionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                      => $this->id,
            
            // Datos del Tipo de Transacción
            'tipo_transaccion_id'     => $this->tipo_transaccion_id,
            'tipo_transaccion_nombre' => $this->tipoTransaccion?->nombre,
            'tipo_transaccion_siglas' => $this->tipoTransaccion?->siglas, // Ej: VENTA, COMPRA, INGRESO, EGRESO
            
            // El Dinero
            'monto'                   => $this->monto,
            
            // Referencias Cruzadas (Para saber de dónde salió este dinero)
            'tipo_referencia'         => $this->tipo_referencia, // 'Venta' o 'Compra'
            'referencia_id'           => $this->referencia_id,   // El ID de la tabla ventas o compras
            'motivo'                  => $this->motivo,          // Ej: "Ingreso por Venta (Comprobante: B001-000456)"
            
            // Auditoría
            'usuario_id'              => $this->usuario_id,
            // Asumiendo que tu modelo Usuario tiene campos 'nombres' y 'apellido_paterno'
            'usuario_nombre'          => $this->usuario?->nombres . ' ' . $this->usuario?->apellido_paterno,
            'estado'                  => $this->estado,
            
            // Fecha en formato texto simple
            'created_at'              => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}