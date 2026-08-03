<?php

namespace App\Http\Resources\Producto;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class ProductoCollection extends ResourceCollection
{
    public function toArray(Request $request): array
    {
        return [
            'data' => ProductoResource::collection($this->collection),
        ];
    }
}