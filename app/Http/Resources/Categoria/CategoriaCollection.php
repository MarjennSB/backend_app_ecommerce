<?php

namespace App\Http\Resources\Categoria;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class CategoriaCollection extends ResourceCollection
{
    public function toArray(Request $request): array
    {
        return [
            'data' => CategoriaResource::collection($this->collection),
        ];
    }
}