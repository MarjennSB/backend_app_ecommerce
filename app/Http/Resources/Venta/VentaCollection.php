<?php

namespace App\Http\Resources\Venta;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class VentaCollection extends ResourceCollection
{
    public function toArray(Request $request): array
    {
        return [
            'data' => VentaResource::collection($this->collection),
        ];
    }
}