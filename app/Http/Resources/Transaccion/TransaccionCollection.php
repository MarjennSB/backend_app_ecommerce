<?php

namespace App\Http\Resources\Transaccion;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class TransaccionCollection extends ResourceCollection
{
    public function toArray(Request $request): array
    {
        return [
            'data' => TransaccionResource::collection($this->collection),
        ];
    }
}