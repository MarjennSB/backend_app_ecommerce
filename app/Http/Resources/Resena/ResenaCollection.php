<?php

namespace App\Http\Resources\Resena;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class ResenaCollection extends ResourceCollection
{
    public function toArray(Request $request): array
    {
        return [
            'data' => ResenaResource::collection($this->collection),
        ];
    }
}