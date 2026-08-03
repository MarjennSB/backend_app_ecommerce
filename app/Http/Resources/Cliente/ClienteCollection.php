<?php

namespace App\Http\Resources\Cliente;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class ClienteCollection extends ResourceCollection
{
    public function toArray(Request $request): array
    {
        return [
            'data' => ClienteResource::collection($this->collection),
        ];
    }
}