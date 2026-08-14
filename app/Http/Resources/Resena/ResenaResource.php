<?php

namespace App\Http\Resources\Resena;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ResenaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'usuario_id' => $this->usuario_id,
            'usuario_correo' => $this->usuario?->correo,
            'producto_id' => $this->producto_id,
            'producto_nombre' => $this->producto?->nombre,
            'producto_slug' => $this->producto?->slug,
            'calificacion' => $this->calificacion,
            'comentario' => $this->comentario,
            'estado' => $this->estado,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}