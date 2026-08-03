<?php

namespace App\Http\Resources\Usuario;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class UsuarioCollection extends ResourceCollection
{
    public function toArray(Request $request): array
    {
        return [
            'data' => UsuarioResource::collection($this->collection),
        ];
    }
}