<?php

namespace App\Http\Resources\Proveedor;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class ProveedorCollection extends ResourceCollection
{
    public function toArray(Request $request): array
    {
        return [
            'data' => ProveedorResource::collection($this->collection),
        ];
    }
}