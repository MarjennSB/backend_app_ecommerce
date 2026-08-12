<?php

namespace App\Http\Resources\Carrito;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class CarritoCollection extends ResourceCollection
{
    public function toArray(Request $request): array
    {
        return [
            'data' => CarritoResource::collection($this->collection),
        ];
    }
}