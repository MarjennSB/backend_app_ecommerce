<?php

namespace App\Http\Resources\DireccionEnvio;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DireccionEnvioResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                        => $this->id,
            'usuario_id'                => $this->usuario_id,
            'usuario_correo'            => $this->usuario?->correo,
            'alias_direccion'           => $this->alias_direccion,
            'urbanizacion'              => $this->urbanizacion,
            'sector'                    => $this->sector,
            'direccion'                 => $this->direccion,
            'manzana'                   => $this->manzana,
            'lote'                      => $this->lote,
            'referencia'                => $this->referencia,
            'es_direccion_principal'    => $this->es_direccion_principal,
            'estado'                    => $this->estado,
            'created_at'                => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}

