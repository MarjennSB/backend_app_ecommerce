<?php

namespace App\Http\Resources\Producto;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                         => $this->id,
            'usuario_id'                 => $this->usuario_id,
            'usuario_nombres'            => $this->usuario?->nombres,
            'usuario_apellido_paterno'   => $this->usuario?->apellido_paterno,
            'usuario_apellido_materno'   => $this->usuario?->apellido_materno,
            'categoria_id'               => $this->categoria_id,
            'categoria_nombre'           => $this->categoria?->nombre,
            'nombre'                     => $this->nombre,
            'descripcion'                => $this->descripcion,
            'precio'                     => $this->precio,
            'cantidad'                   => $this->cantidad,
            'codigo_barras'              => $this->codigo_barras,
            'codigo_qr'                  => $this->codigo_qr,
            'fecha_vencimiento'          => $this->fecha_vencimiento,
            'estado'                     => $this->estado,
            // ¡AQUÍ ESTÁ LA MAGIA PARA LAS IMÁGENES!
            'imagenes' => $this->whenLoaded('imagenes', function () {
                return $this->imagenes->map(function ($imagen) {
                    return [
                        'id'          => $imagen->id,
                        'ruta_imagen' => url('storage/' . $imagen->ruta_imagen), 
                        'estado'      => $imagen->estado,
                        'created_at'  => $imagen->created_at?->format('Y-m-d H:i:s')
                    ];
                });
            }),
            'created_at'                 => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}