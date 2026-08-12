<?php

namespace App\Http\Resources\Favorito;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class FavoritoCollection extends ResourceCollection
{
    public function toArray(Request $request): array
    {
        return [
            'data' => FavoritoResource::collection($this->collection),
        ];
    }
}