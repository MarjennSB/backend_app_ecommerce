<?php

namespace App\Http\Resources\Producto;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'usuario_id' => $this->usuario_id,
            'usuario_correo' => $this->usuario?->correo,
            'categoria_id' => $this->categoria_id,
            'categoria_nombre' => $this->categoria?->nombre,
            'marca_id' => $this->marca_id,
            'marca_nombre' => $this->marca?->nombre,
            'nombre' => $this->nombre,
            'slug' => $this->slug,
            'descripcion_corta' => $this->descripcion_corta,
            'descripcion_larga' => $this->descripcion_larga,
            'precio_venta' => $this->precio_venta,
            'precio_oferta' => $this->precio_oferta,
            'precio_compra_referencial' => $this->precio_compra_referencial,
            'es_destacado' => $this->es_destacado,
            'stock_actual' => $this->stock_actual,
            'codigo_barras' => $this->codigo_barras,
            'codigo_qr' => $this->codigo_qr,
            'fecha_vencimiento' => $this->fecha_vencimiento,
            'estado' => $this->estado,
            'imagenes' => $this->whenLoaded('imagenes', function () {
                return $this->imagenes->map(function ($imagen) {
                    return [
                        'id' => $imagen->id,
                        'ruta_imagen' => url('storage/' . $imagen->ruta_imagen),
                        'estado' => $imagen->estado,
                        'created_at' => $imagen->created_at?->format('Y-m-d H:i:s'),
                    ];
                });
            }),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}