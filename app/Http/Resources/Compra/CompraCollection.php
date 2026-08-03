<?php

namespace App\Http\Resources\Compra;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class CompraCollection extends ResourceCollection
{
    public function toArray(Request $request): array
    {
        return [
            'data' => CompraResource::collection($this->collection),
        ];
    }
}