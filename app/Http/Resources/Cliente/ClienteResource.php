<?php

namespace App\Http\Resources\Cliente;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClienteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                          => $this->id,
            'persona_id'                  => $this->persona_id,
            'persona_nombres'             => $this->persona?->nombres,
            'persona_apellido_paterno'    => $this->persona?->apellido_paterno,
            'persona_apellido_materno'    => $this->persona?->apellido_materno,
            'estado'                        => $this->estado,
            'created_at'                    => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
