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
            'id' => $this->id,
            'tipo_transaccion_id' => $this->tipo_transaccion_id,
            'tipo_transaccion_nombre' => $this->tipoTransaccion?->nombre,
            'tipo_referencia' => $this->tipo_referencia,
            'referencia_id' => $this->referencia_id,
            'monto' => $this->monto,
            'motivo' => $this->motivo,
            'usuario_id' => $this->usuario_id,
            'usuario_correo' => $this->usuario?->correo,
            'estado' => $this->estado,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}