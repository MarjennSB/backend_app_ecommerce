<?php

namespace App\Http\Resources\Categoria;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoriaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                          => $this->id,
            'nombre'                      => $this->nombre,
            'descripcion'                 => $this->descripcion,
            'estado'                      => $this->estado,
            'created_at'                  => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
